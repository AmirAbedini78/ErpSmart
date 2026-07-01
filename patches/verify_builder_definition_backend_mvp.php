<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function builder_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function builder_run(string $root, string $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);

    if (! is_resource($process)) {
        return [1, 'Unable to start command'];
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

function builder_files_under(string $path): array
{
    if (! is_dir($path)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $files[] = $item->getPathname();
        }
    }

    sort($files);

    return $files;
}

$requiredFiles = [
    'database/migrations/2026_07_01_000001_create_builder_definitions_table.php',
    'database/migrations/2026_07_01_000002_create_builder_definition_versions_table.php',
    'database/migrations/2026_07_01_000003_create_builder_preview_runs_table.php',
    'app/Models/BuilderDefinition.php',
    'app/Models/BuilderDefinitionVersion.php',
    'app/Models/BuilderPreviewRun.php',
    'app/Http/Controllers/Builder/BuilderDefinitionController.php',
    'app/Http/Requests/Builder/StoreBuilderDefinitionRequest.php',
    'app/Http/Requests/Builder/UpdateBuilderDefinitionRequest.php',
    'app/Services/Builder/BuilderDefinitionValidator.php',
    'app/Services/Builder/BuilderPreviewService.php',
    'app/Services/Builder/BuilderDefinitionVersionService.php',
    'docs/ai/03-architecture/builder-definition-backend-mvp.md',
    'docs/ai/04-docops/history/2026-07-01-builder-definition-backend-mvp.md',
];

foreach ($requiredFiles as $file) {
    builder_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach (array_filter($requiredFiles, fn (string $file): bool => str_ends_with($file, '.php')) as $file) {
    [$code, $output] = builder_run($root, 'php -l '.escapeshellarg($file));
    builder_check($checks, $file.' PHP syntax is valid', $code === 0, trim($output));
}

$routeFile = file_get_contents($root.'/routes/api.php') ?: '';
builder_check($checks, 'API route registration exists', str_contains($routeFile, 'builder') && str_contains($routeFile, 'BuilderDefinitionController'));
builder_check($checks, 'routes are admin scoped', str_contains($routeFile, "'admin'") || str_contains($routeFile, '"admin"'));
builder_check($checks, 'no publish route is registered', ! str_contains($routeFile, '/publish') && ! str_contains($routeFile, '->publish'));

require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$validator = $app->make(App\Services\Builder\BuilderDefinitionValidator::class);
$samplePath = $root.'/docs/ai/05-rag/examples/definition-driven-custom-module.json';
$sample = json_decode(file_get_contents($samplePath) ?: '', true);
$report = $validator->validate($sample);
builder_check($checks, 'schema validator validates definition-driven custom module', ($report['valid'] ?? false) === true, json_encode($report));

$previewServiceSource = file_get_contents($root.'/app/Services/Builder/BuilderPreviewService.php') ?: '';
builder_check($checks, 'preview service calls preview mode', str_contains($previewServiceSource, "'--preview' => true"));
builder_check($checks, 'preview service does not call write mode', ! str_contains($previewServiceSource, '--write'));
builder_check($checks, 'preview path restricted to storage/app/module-builder-preview', str_contains($previewServiceSource, "storage_path('app/module-builder-preview"));
builder_check($checks, 'preview service does not publish', ! preg_match('/publish/i', str_replace('preview_writes', '', $previewServiceSource)));

[$routeCode, $routeOutput] = builder_run($root, 'php artisan route:list --path=builder');
$routeListWorks = $routeCode === 0;
builder_check($checks, 'route:list works for builder path', $routeListWorks, trim($routeOutput));
if ($routeListWorks) {
    builder_check($checks, 'route list contains builder definitions index', str_contains($routeOutput, 'builder/definitions'));
    builder_check($checks, 'route list contains builder validate route', str_contains($routeOutput, 'validate'));
    builder_check($checks, 'route list contains builder preview route', str_contains($routeOutput, 'preview'));
}

$migrationRepository = $app->make('migration.repository');
if (method_exists($migrationRepository, 'repositoryExists') && $migrationRepository->repositoryExists()) {
    $ran = $migrationRepository->getRan();
    $builderMigrationsRan = array_values(array_filter($ran, fn (string $migration): bool => str_contains($migration, 'create_builder_')));
    builder_check($checks, 'database migrations are not executed automatically', $builderMigrationsRan === [], implode(', ', $builderMigrationsRan));
} else {
    builder_check($checks, 'database migrations are not executed automatically', true, 'migration repository not installed');
}

builder_check($checks, 'no real generated CustomRecords module exists', ! is_dir($root.'/modules/CustomRecords'));
builder_check($checks, 'no real generated Inventory module exists', ! is_dir($root.'/modules/Inventory'));

$previewBase = $root.'/storage/app/module-builder-preview';
$previewFiles = builder_files_under($previewBase);
$onlyPreviewFilesUnderPreviewBase = true;
$realPreviewBase = realpath($previewBase) ?: $previewBase;
foreach ($previewFiles as $file) {
    $realFile = realpath($file) ?: $file;
    if (! str_starts_with($realFile, $realPreviewBase)) {
        $onlyPreviewFilesUnderPreviewBase = false;
        break;
    }
}
builder_check($checks, 'existing preview files are under storage/app/module-builder-preview', $onlyPreviewFilesUnderPreviewBase);

[$statusCode, $statusOutput] = builder_run($root, 'git -c safe.directory='.escapeshellarg($root).' status --short');
builder_check($checks, 'git status command succeeds', $statusCode === 0, trim($statusOutput));

$forbiddenChanged = [];
foreach (explode("\n", trim($statusOutput)) as $line) {
    if ($line === '') {
        continue;
    }

    $path = trim(substr($line, 3));
    if (
        str_starts_with($path, 'modules/Warehouse/')
        || str_starts_with($path, 'modules/Core/')
        || str_starts_with($path, 'vendor/')
        || str_starts_with($path, 'node_modules/')
        || str_starts_with($path, 'public/build/')
        || in_array($path, ['package.json', 'package-lock.json', 'composer.json', 'composer.lock'], true)
    ) {
        $forbiddenChanged[] = $line;
    }
}

builder_check($checks, 'no Warehouse/Core files changed', ! array_filter($forbiddenChanged, fn (string $line): bool => str_contains($line, 'modules/Warehouse/') || str_contains($line, 'modules/Core/')));
builder_check($checks, 'no package/composer/vendor/build files changed', ! array_filter($forbiddenChanged, fn (string $line): bool => preg_match('#(vendor/|node_modules/|public/build/|package\.json|package-lock\.json|composer\.json|composer\.lock)#', $line) === 1));
builder_check($checks, 'app/Console command not modified', ! str_contains($statusOutput, 'app/Console/Commands/ErpsmartMakeModuleCommand.php'));

$failed = array_filter($checks, fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
