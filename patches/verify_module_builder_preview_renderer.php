<?php

$root = dirname(__DIR__);
$definition = 'docs/ai/05-rag/examples/warehouse-like-module-definition.json';
$previewRoot = $root.'/storage/app/module-builder-preview/Inventory';
$checks = [];

function record_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function run_from_root(string $root, string $command): array
{
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $root);

    if (! is_resource($process)) {
        return [1, 'Unable to start command: '.$command];
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

function remove_directory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

function files_under(string $path): array
{
    if (! is_dir($path)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $files[] = $item->getPathname();
        }
    }

    sort($files);

    return $files;
}

remove_directory($previewRoot);

[$dryRunCode, $dryRunOutput] = run_from_root(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($definition).' --dry-run'
);

record_check($checks, 'dry-run command exits successfully', $dryRunCode === 0);
record_check($checks, 'dry-run still prints heading', str_contains($dryRunOutput, 'ERPSMART Module Builder Dry Run'));
record_check($checks, 'dry-run still writes zero files', str_contains($dryRunOutput, 'Writes performed: 0'));

[$noModeCode, $noModeOutput] = run_from_root(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($definition)
);

record_check($checks, 'command refuses missing dry-run/preview mode', $noModeCode !== 0 && str_contains($noModeOutput, 'Refusing to run without --dry-run or --preview'));

[$writeCode, $writeOutput] = run_from_root(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($definition).' --write'
);

record_check($checks, 'command refuses future write mode', $writeCode !== 0 && str_contains($writeOutput, 'Refusing --write'));

[$previewCode, $previewOutput] = run_from_root(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($definition).' --preview'
);

record_check($checks, 'preview command exits successfully', $previewCode === 0);
record_check($checks, 'preview prints heading', str_contains($previewOutput, 'ERPSMART Module Builder Preview'));
record_check($checks, 'preview prints real runtime writes as zero', str_contains($previewOutput, 'Real runtime writes performed: 0'));
record_check($checks, 'preview prints preview write count', preg_match('/Preview writes performed: \d+/', $previewOutput) === 1);
record_check($checks, 'preview directory exists', is_dir($previewRoot), 'storage/app/module-builder-preview/Inventory');

$expectedFiles = [
    'modules/Inventory/module.json',
    'modules/Inventory/bootstrap/module.php',
    'modules/Inventory/app/Providers/InventoryServiceProvider.php',
    'modules/Inventory/app/Providers/RouteServiceProvider.php',
    'modules/Inventory/app/Models/Item.php',
    'modules/Inventory/app/Resources/Item.php',
    'modules/Inventory/app/Resources/ItemTable.php',
    'modules/Inventory/app/Http/Resources/ItemResource.php',
    'modules/Inventory/app/Policies/ItemPolicy.php',
    'modules/Inventory/database/migrations/create_items_table.php',
    'modules/Inventory/routes/api.php',
    'modules/Inventory/routes/web.php',
    'modules/Inventory/resources/js/app.js',
    'modules/Inventory/resources/js/routes.js',
    'modules/Inventory/resources/js/views/ItemsIndex.vue',
    'modules/Inventory/resources/js/views/ItemsCreate.vue',
    'modules/Inventory/resources/js/views/ItemsEdit.vue',
    'modules/Inventory/resources/js/views/ItemsView.vue',
    'modules/Inventory/resources/js/components/ItemFloatingModal.vue',
    'patches/verify_inventory_item_contract.php',
    'docs/ai/04-docops/history/YYYY-MM-DD-inventory-item-generated.md',
];

foreach ($expectedFiles as $file) {
    record_check($checks, 'preview file exists: '.$file, is_file($previewRoot.'/'.$file));
}

$previewFiles = files_under($previewRoot);
$realPreviewRoot = realpath($previewRoot) ?: $previewRoot;
$onlyUnderPreview = $previewFiles !== [];

foreach ($previewFiles as $file) {
    $realFile = realpath($file) ?: $file;
    if (! str_starts_with($realFile, $realPreviewRoot.DIRECTORY_SEPARATOR)) {
        $onlyUnderPreview = false;
        break;
    }
}

record_check($checks, 'preview files are only under storage/app/module-builder-preview/Inventory', $onlyUnderPreview);
record_check($checks, 'real modules/Inventory directory was not created', ! is_dir($root.'/modules/Inventory'));
record_check($checks, 'real generated verifier was not created', ! is_file($root.'/patches/verify_inventory_item_contract.php'));
record_check($checks, 'real runtime migration was not created', ! is_dir($root.'/modules/Inventory/database/migrations'));

$phpPreviewFiles = array_values(array_filter($previewFiles, fn (string $file): bool => str_ends_with($file, '.php')));
$phpSyntaxOk = true;
$phpSyntaxErrors = [];

foreach ($phpPreviewFiles as $file) {
    [$code, $output] = run_from_root($root, 'php -l '.escapeshellarg($file));

    if ($code !== 0) {
        $phpSyntaxOk = false;
        $phpSyntaxErrors[] = basename($file).': '.trim($output);
    }
}

record_check($checks, 'generated preview PHP files have no syntax errors', $phpSyntaxOk, implode('; ', $phpSyntaxErrors));

$itemResource = file_get_contents($previewRoot.'/modules/Inventory/app/Http/Resources/ItemResource.php') ?: '';
$itemResourceClass = file_get_contents($previewRoot.'/modules/Inventory/app/Resources/Item.php') ?: '';
$itemsView = file_get_contents($previewRoot.'/modules/Inventory/resources/js/views/ItemsView.vue') ?: '';

record_check($checks, 'preview ItemResource extends Core JsonResource', str_contains($itemResource, 'use Modules\\Core\\Resource\\JsonResource;') && str_contains($itemResource, 'extends JsonResource'));
record_check($checks, 'preview ItemResource calls withCommonData()', str_contains($itemResource, 'withCommonData('));
record_check($checks, 'preview Item Resource uses StandardDetailPage Panel metadata', str_contains($itemResourceClass, 'Panel::make') && str_contains($itemResourceClass, 'getDetailPage()'));
record_check($checks, 'preview Item Resource uses StandardDetailPage Tab metadata', str_contains($itemResourceClass, 'Tab::make') && str_contains($itemResourceClass, 'activities-tab-panel') && str_contains($itemResourceClass, 'notes-tab-panel'));
record_check($checks, 'preview ItemsView consumes resourceInformation.value.detailPage', str_contains($itemsView, 'resourceInformation.value.detailPage'));

[$statusCode, $statusOutput] = run_from_root(
    $root,
    'git -c safe.directory='.escapeshellarg($root).' status --porcelain --untracked-files=all'
);
$changedFiles = array_filter(array_map(
    fn (string $line): string => trim(substr($line, 3)),
    explode(PHP_EOL, trim($statusOutput))
));

$forbiddenPatterns = [
    '#^modules/Warehouse/#',
    '#^modules/Core/#',
    '#^modules/Activities/#',
    '#^modules/Notes/#',
    '#^database/migrations/#',
    '#^vendor/#',
    '#^node_modules/#',
    '#^public/build/#',
    '#^package\.json$#',
    '#^package-lock\.json$#',
    '#^composer\.json$#',
    '#^composer\.lock$#',
];

$forbiddenChanged = [];

foreach ($changedFiles as $file) {
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $file) === 1) {
            $forbiddenChanged[] = $file;
            break;
        }
    }
}

record_check($checks, 'git status read succeeds', $statusCode === 0);
record_check($checks, 'no forbidden runtime/source paths changed', $forbiddenChanged === [], implode(', ', array_unique($forbiddenChanged)));

$passed = array_reduce($checks, fn (bool $carry, array $check): bool => $carry && $check[1], true);

echo $passed ? 'PASS'.PHP_EOL : 'FAIL'.PHP_EOL;

exit($passed ? 0 : 1);
