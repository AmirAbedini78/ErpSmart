<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;
$dryRunRoot = null;

function dry_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function dry_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function dry_json(string $root, string $path): ?array
{
    $decoded = json_decode(dry_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function dry_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = dry_flatten($nested);
        }
        return implode(' ', $parts);
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    return (string) $value;
}

function dry_delete_directory_if_unique(?string $path, string $expectedBase): void
{
    if (! $path || ! is_dir($path)) {
        return;
    }
    $realPath = realpath($path);
    $realBase = realpath($expectedBase);
    if (! $realPath || ! $realBase || ! str_starts_with($realPath, $realBase.DIRECTORY_SEPARATOR)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($realPath);
}

require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BuilderDefinition;
use App\Models\BuilderDefinitionVersion;
use App\Models\BuilderPreviewRun;
use App\Services\Builder\BuilderPublishDryRunGenerator;
use Illuminate\Support\Facades\Schema;

$generatorPath = 'app/Services/Builder/BuilderPublishDryRunGenerator.php';
$controllerPath = 'app/Http/Controllers/Builder/BuilderDefinitionController.php';
$routesPath = 'routes/api.php';
$apiPath = 'modules/Builder/resources/js/services/builderApi.js';
$uiPath = 'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue';
$viewPath = 'modules/Builder/resources/js/views/BuilderDefinitionView.vue';
$docPath = 'docs/ai/03-architecture/builder-publish-dry-run-file-generator.md';
$contractPath = 'docs/ai/05-rag/contracts/builder-publish-dry-run-contract.json';
$historyPath = 'docs/ai/04-docops/history/2026-07-02-builder-publish-dry-run-file-generator.md';

foreach ([$generatorPath, $docPath, $contractPath, $historyPath] as $file) {
    dry_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    $contractPath,
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-studio-api-map.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
    'docs/ai/05-rag/contracts/builder-module-dependency-impact-map.json',
] as $jsonFile) {
    dry_check($checks, $jsonFile.' valid JSON', dry_json($root, $jsonFile) !== null, json_last_error_msg());
}

$generator = dry_read($root, $generatorPath);
$controller = dry_read($root, $controllerPath);
$routes = dry_read($root, $routesPath);
$api = dry_read($root, $apiPath);
$ui = dry_read($root, $uiPath).dry_read($root, $viewPath);
$manifestText = dry_flatten(dry_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$apiMapText = dry_flatten(dry_json($root, 'docs/ai/05-rag/contracts/builder-studio-api-map.json') ?? []);
$safetyText = dry_flatten(dry_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);

dry_check($checks, 'generator references storage/app/builder-publish-dry-runs', str_contains($generator, 'storage/app/builder-publish-dry-runs'));
dry_check($checks, 'generator does not write modules/', ! preg_match('/File::put\([^;]*modules\//', $generator));
dry_check($checks, 'generator does not write database/migrations/', ! preg_match('/File::put\([^;]*database\/migrations/', $generator));
dry_check($checks, 'generator does not run migrations', ! preg_match('/migrate|Artisan::call|Schema::create|Schema::table/i', $generator));
dry_check($checks, 'generator does not create versions or preview runs', ! preg_match('/BuilderDefinitionVersion|BuilderPreviewRun|createVersion|previewRuns\(\)->create|versions\(\)->create/', $generator));
dry_check($checks, 'controller has publishDryRun method', preg_match('/function\s+publishDryRun\s*\(/', $controller) === 1);
dry_check($checks, 'route registration includes publish-dry-run', str_contains($routes, 'publish-dry-run'));
dry_check($checks, 'builderApi has generatePublishDryRun', str_contains($api, 'function generatePublishDryRun'));
dry_check($checks, 'UI contains Generate Publish Dry Run', str_contains($ui, 'Generate Publish Dry Run'));
dry_check($checks, 'UI has storage-only dry-run safety notice', str_contains($ui, 'storage/app/builder-publish-dry-runs') && str_contains($ui, 'No runtime modules, migrations, tables, routes, or publish actions are performed.'));
dry_check($checks, 'UI contains no Publish button/action', ! preg_match('/text=["\']Publish["\']|runPublish|publishDefinition|@publish(?!-readiness)/i', $ui.$api));
dry_check($checks, 'RAG manifest mentions publish dry-run', str_contains($manifestText, 'BuilderPublishDryRunGenerator') && str_contains($manifestText, 'builder-publish-dry-runs'));
dry_check($checks, 'API map includes publish-dry-run endpoint', str_contains($apiMapText, '/api/builder/definitions/{id}/publish-dry-run'));
dry_check($checks, 'safety boundaries forbid copying dry-run artifacts into runtime paths', str_contains($safetyText, 'copy publish dry-run artifacts into runtime paths'));

try {
    foreach (['builder_definitions', 'builder_definition_versions', 'builder_preview_runs'] as $table) {
        dry_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }
    if (array_filter($checks, static fn (array $check): bool => $check[1] === false && str_contains($check[0], ' table exists'))) {
        throw new RuntimeException('Builder tables are missing; dry-run smoke cannot continue.');
    }

    $definition = json_decode(dry_read($root, 'docs/ai/05-rag/examples/definition-driven-custom-module.json'), true);
    if (! is_array($definition)) {
        throw new RuntimeException('Unable to read definition-driven-custom-module.json');
    }
    $suffix = bin2hex(random_bytes(4));
    $moduleName = 'DryRunSmoke'.$suffix;
    $route = 'dry-run-smoke-'.$suffix;
    $definition['module']['name'] = $moduleName;
    $definition['module']['namespace'] = 'Modules\\'.$moduleName;
    $definition['module']['singularLabel'] = 'DryRunSmoke';
    $definition['module']['pluralLabel'] = $moduleName;
    $definition['module']['table'] = 'dry_run_smoke_'.$suffix;
    $definition['module']['routeName'] = $route;
    $definition['module']['resourceName'] = $route;
    $definition['resource']['modelClass'] = 'Modules\\'.$moduleName.'\\Models\\DryRunSmoke';
    $definition['relations'] = [['name' => 'owner', 'type' => 'belongsTo', 'targetModule' => 'Users', 'targetModel' => 'User', 'targetResource' => 'users', 'foreignKey' => 'user_id']];
    $definition['formLayout'] = ['enabled' => true, 'sections' => [['id' => 'main', 'fields' => [['field' => 'title']]]]];
    $definition['automation'] = ['enabled' => true, 'workflows' => [['id' => 'workflow_1', 'trigger' => ['type' => 'record_created'], 'actions' => [['type' => 'send_notification']]]]];

    $createdDefinition = BuilderDefinition::create([
        'name' => 'Dry Run Smoke '.$suffix,
        'slug' => 'dry-run-smoke-'.$suffix,
        'module_name' => $moduleName,
        'entity_name' => 'DryRunSmoke',
        'resource_name' => $route,
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definition,
        'checksum' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ]);

    $versionCountBefore = BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count();
    $previewCountBefore = BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count();
    $report = $app->make(BuilderPublishDryRunGenerator::class)->generate($createdDefinition->fresh());
    $dryRunRoot = $root.'/'.$report['dry_run_root'];
    $manifestPath = $dryRunRoot.'/manifest/publish-dry-run-manifest.json';
    $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath) ?: '', true) : null;

    dry_check($checks, 'report writes_performed is 0', ($report['writes_performed'] ?? null) === 0);
    dry_check($checks, 'report runtime_writes_performed is 0', ($report['runtime_writes_performed'] ?? null) === 0);
    dry_check($checks, 'report publish_executed is false', ($report['publish_executed'] ?? null) === false);
    dry_check($checks, 'report runtime_module_effect none', ($report['runtime_module_effect'] ?? null) === 'none');
    dry_check($checks, 'dry_run_artifacts_written greater than 0', ($report['dry_run_artifacts_written'] ?? 0) > 0);
    dry_check($checks, 'dry_run_root under storage/app/builder-publish-dry-runs', str_starts_with((string) ($report['dry_run_root'] ?? ''), 'storage/app/builder-publish-dry-runs/'));
    dry_check($checks, 'manifest exists', is_file($manifestPath));
    dry_check($checks, 'manifest JSON is valid', is_array($manifest), json_last_error_msg());
    dry_check($checks, 'manifest safety flags are safe', is_array($manifest) && ($manifest['safety']['sandbox_only'] ?? false) === true && ($manifest['safety']['runtime_paths_touched'] ?? true) === false);

    $generatedContents = '';
    foreach (($report['files'] ?? []) as $file) {
        if (($file['type'] ?? '') !== 'manifest' && is_file($root.'/'.$file['dry_run_path'])) {
            $generatedContents .= file_get_contents($root.'/'.$file['dry_run_path']);
        }
    }
    dry_check($checks, 'generated files contain DRY RUN ONLY', str_contains($generatedContents, 'DRY RUN ONLY - NOT RUNTIME CODE'));
    dry_check($checks, 'analyzer/dry-run does not create preview runs', BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count() === $previewCountBefore);
    dry_check($checks, 'analyzer/dry-run does not create versions', BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count() === $versionCountBefore);
    dry_check($checks, 'no real runtime smoke module created', ! is_dir($root.'/modules/'.$moduleName));
} catch (Throwable $e) {
    dry_check($checks, 'runtime dry-run smoke completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        BuilderPreviewRun::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinitionVersion::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();
        dry_check($checks, 'temporary DB records cleaned', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }

    dry_delete_directory_if_unique($dryRunRoot, $root.'/storage/app/builder-publish-dry-runs');
    dry_check($checks, 'unique dry-run directory cleaned or absent', ! $dryRunRoot || ! is_dir($dryRunRoot));
}

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
dry_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));
$changedPaths = array_filter(array_map(static fn (string $line): string => trim(substr($line, 3)), preg_split('/\R/', trim($statusOutput))));

foreach ([
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
    'app/Services/Builder/BuilderPreviewService.php',
    'app/Services/Builder/BuilderDefinitionValidator.php',
    'app/Models/',
    'database/migrations/',
    'modules/Warehouse/',
    'modules/Core/',
    'modules/SaaS/',
    'modules/Updater/',
    'modules/Installer/',
    'resources/js/app.js',
    'vendor/',
    'node_modules/',
    'public/build/',
] as $forbiddenPath) {
    $changed = str_ends_with($forbiddenPath, '/')
        ? array_filter($changedPaths, static fn (string $path): bool => str_starts_with($path, $forbiddenPath))
        : in_array($forbiddenPath, $changedPaths, true);
    dry_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}
foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    dry_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);
echo $failed === [] ? "PASS\n" : "FAIL\n";
exit($failed === [] ? 0 : 1);
