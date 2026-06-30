<?php

$root = dirname(__DIR__);

function check(string $name, bool $result): bool
{
    printf('%-78s : %s%s', $name, $result ? 'true' : 'false', PHP_EOL);

    return $result;
}

function read_json(string $path): ?array
{
    if (! is_file($path)) {
        return null;
    }

    $json = json_decode(file_get_contents($path) ?: '', true);

    return is_array($json) ? $json : null;
}

function has_path(array $data, string $path): bool
{
    $cursor = $data;

    foreach (explode('.', $path) as $segment) {
        if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
            return false;
        }

        $cursor = $cursor[$segment];
    }

    return true;
}

function run_command(string $root, string $command): array
{
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open('cd '.escapeshellarg($root).' && '.$command, $descriptorSpec, $pipes);

    if (! is_resource($process)) {
        return ['code' => 127, 'output' => 'failed to start process'];
    }

    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'output' => $stdout.$stderr];
}

function git_changed_files(string $root): array
{
    $output = shell_exec('cd '.escapeshellarg($root).' && git status --porcelain --untracked-files=all 2>/dev/null');

    if (! is_string($output) || trim($output) === '') {
        return [];
    }

    return array_values(array_filter(array_map(function (string $line): string {
        $path = trim(substr($line, 3));

        if (str_contains($path, ' -> ')) {
            $parts = explode(' -> ', $path);
            $path = end($parts);
        }

        return trim($path, "\" \t\n\r\0\x0B");
    }, explode(PHP_EOL, trim($output)))));
}

$commandPath = $root.'/app/Console/Commands/ErpsmartMakeModuleCommand.php';
$samplePath = $root.'/docs/ai/05-rag/examples/warehouse-like-module-definition.json';
$historyPath = $root.'/docs/ai/04-docops/history/2026-06-30-module-builder-dry-run-command.md';
$sample = read_json($samplePath);

$requiredSamplePaths = [
    'schemaVersion',
    'module.name',
    'module.namespace',
    'module.table',
    'module.routeName',
    'module.resourceName',
    'resource.modelClass',
    'fields',
    'capabilities.tableable',
    'permissions.view',
    'frontend.routes',
    'verifier.generate',
];

$sampleHasRequiredKeys = is_array($sample);
if (is_array($sample)) {
    foreach ($requiredSamplePaths as $path) {
        $sampleHasRequiredKeys = $sampleHasRequiredKeys && has_path($sample, $path);
    }
}

$withoutDryRun = run_command($root, 'php artisan erpsmart:make-module --definition=docs/ai/05-rag/examples/warehouse-like-module-definition.json');
$withDryRun = run_command($root, 'php artisan erpsmart:make-module --definition=docs/ai/05-rag/examples/warehouse-like-module-definition.json --dry-run');

$generatedPaths = [
    $root.'/modules/Inventory',
    $root.'/patches/verify_inventory_item_contract.php',
    $root.'/docs/ai/04-docops/history/YYYY-MM-DD-inventory-item-generated.md',
];

$generatedFilesCreated = array_values(array_filter($generatedPaths, fn (string $path): bool => file_exists($path)));

$changedFiles = git_changed_files($root);
$allowedChangedFiles = [
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
    'docs/ai/05-rag/examples/warehouse-like-module-definition.json',
    'patches/verify_module_builder_dry_run_command.php',
    'docs/ai/04-docops/history/2026-06-30-module-builder-dry-run-command.md',
    'docs/ai/03-architecture/module-builder-mvp-schema.md',
    'docs/ai/05-rag/contracts/module-builder-mvp-schema.json',
    'patches/verify_module_builder_mvp_schema.php',
    'docs/ai/04-docops/history/2026-06-30-module-builder-mvp-schema-and-dry-run.md',
];

$unexpectedChangedFiles = array_values(array_filter($changedFiles, fn (string $file): bool => ! in_array($file, $allowedChangedFiles, true)));
$unsafeWarehouseChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_starts_with($file, 'modules/Warehouse/')));
$unsafeMigrationChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_contains($file, '/database/migrations/')));
$unsafeVendorNodeBuildChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_starts_with($file, 'vendor/') || str_starts_with($file, 'node_modules/') || str_starts_with($file, 'public/build/')));
$unsafeManifestChanges = array_values(array_filter($changedFiles, fn (string $file): bool => in_array($file, ['composer.json', 'composer.lock', 'package.json', 'package-lock.json'], true)));

$output = $withDryRun['output'];

$failed = false;

echo 'ERPSMART Module Builder Dry-Run Command Verifier'.PHP_EOL.PHP_EOL;

$failed = ! check('command_file_exists', is_file($commandPath)) || $failed;
$failed = ! check('history_note_exists', is_file($historyPath)) || $failed;
$failed = ! check('command_refuses_non_dry_run', $withoutDryRun['code'] !== 0 && str_contains($withoutDryRun['output'], 'Refusing to run without --dry-run')) || $failed;
$failed = ! check('sample_definition_exists', is_file($samplePath)) || $failed;
$failed = ! check('sample_definition_valid_json', is_array($sample)) || $failed;
$failed = ! check('sample_definition_has_required_keys', $sampleHasRequiredKeys) || $failed;
$failed = ! check('dry_run_command_exit_success', $withDryRun['code'] === 0) || $failed;
$failed = ! check('output_has_title', str_contains($output, 'ERPSMART Module Builder Dry Run')) || $failed;
$failed = ! check('output_has_writes_zero', str_contains($output, 'Writes performed: 0')) || $failed;
$failed = ! check('output_has_backend_files', str_contains($output, 'Backend files:') && str_contains($output, 'modules/Inventory/app/Resources/Item.php')) || $failed;
$failed = ! check('output_has_frontend_files', str_contains($output, 'Frontend files:') && str_contains($output, 'modules/Inventory/resources/js/views/ItemsView.vue')) || $failed;
$failed = ! check('output_has_docs_verifier_files', str_contains($output, 'Docs/verifier files:') && str_contains($output, 'patches/verify_inventory_item_contract.php')) || $failed;
$failed = ! check('no_generated_module_files_created', $generatedFilesCreated === []) || $failed;
$failed = ! check('no_unexpected_changed_files', $unexpectedChangedFiles === []) || $failed;
$failed = ! check('no_warehouse_runtime_files_changed', $unsafeWarehouseChanges === []) || $failed;
$failed = ! check('no_migrations_changed', $unsafeMigrationChanges === []) || $failed;
$failed = ! check('no_vendor_node_build_files_changed', $unsafeVendorNodeBuildChanges === []) || $failed;
$failed = ! check('no_package_or_composer_files_changed', $unsafeManifestChanges === []) || $failed;

if ($withDryRun['code'] !== 0 || ! str_contains($output, 'ERPSMART Module Builder Dry Run')) {
    echo PHP_EOL.'Dry-run output:'.PHP_EOL.$output.PHP_EOL;
}

if ($generatedFilesCreated !== []) {
    echo PHP_EOL.'Generated files unexpectedly found:'.PHP_EOL;
    foreach ($generatedFilesCreated as $path) {
        echo '- '.$path.PHP_EOL;
    }
}

if ($unexpectedChangedFiles !== []) {
    echo PHP_EOL.'Unexpected changed files:'.PHP_EOL;
    foreach ($unexpectedChangedFiles as $file) {
        echo '- '.$file.PHP_EOL;
    }
}

echo PHP_EOL.($failed ? 'FAIL' : 'PASS').PHP_EOL;

exit($failed ? 1 : 0);
