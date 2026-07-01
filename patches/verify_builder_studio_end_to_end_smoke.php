<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;
$smokeModuleName = 'SmokeRecords'.date('YmdHis');
$smokePreviewPath = null;
$smokeTempDefinitionPath = null;

function smoke_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function smoke_run(string $root, string $command): array
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

function smoke_definition(string $root, string $moduleName): array
{
    $path = $root.'/docs/ai/05-rag/examples/definition-driven-custom-module.json';
    $definition = json_decode(file_get_contents($path) ?: '', true);

    if (! is_array($definition)) {
        throw new RuntimeException('Unable to load definition-driven-custom-module.json');
    }

    $singular = str_ends_with($moduleName, 's') ? substr($moduleName, 0, -1) : $moduleName;
    $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $moduleName));
    $route = str_replace('_', '-', $table);

    $definition['module']['name'] = $moduleName;
    $definition['module']['namespace'] = 'Modules\\'.$moduleName;
    $definition['module']['singularLabel'] = $singular;
    $definition['module']['pluralLabel'] = $moduleName;
    $definition['module']['table'] = $table;
    $definition['module']['routeName'] = $route;
    $definition['module']['resourceName'] = $route;
    $definition['resource']['modelClass'] = 'Modules\\'.$moduleName.'\\Models\\'.$singular;
    $definition['table']['defaultView']['name'] = $moduleName;
    $definition['table']['defaultView']['flag'] = 'all-'.$route;
    $definition['verifier']['path'] = 'patches/verify_'.$route.'_contract.php';

    foreach ([
        'documents',
        'calls',
        'emails',
        'emailSending',
        'tasks',
        'workflow',
        'approvals',
        'notifications',
        'timeline',
        'softDeletes',
        'formLayout',
        'stepperForm',
        'sections',
        'conditionalVisibility',
    ] as $capability) {
        $definition['capabilities'][$capability] = false;
    }

    return $definition;
}

function smoke_checksum(array $definition): string
{
    return hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function smoke_delete_directory_if_unique(?string $path, string $expectedBase): void
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
use App\Models\BuilderPreviewRun;
use App\Services\Builder\BuilderDefinitionValidator;
use App\Services\Builder\BuilderDefinitionVersionService;
use App\Services\Builder\BuilderPreviewService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

try {
    foreach ([
        'builder_definitions',
        'builder_definition_versions',
        'builder_preview_runs',
    ] as $table) {
        smoke_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }

    if (array_filter($checks, fn (array $check): bool => $check[1] === false)) {
        throw new RuntimeException('Builder tables are missing; smoke cannot continue without running migrations.');
    }

    $definitionJson = smoke_definition($root, $smokeModuleName);
    $checksum = smoke_checksum($definitionJson);

    $createdDefinition = BuilderDefinition::create([
        'name' => $smokeModuleName.' Smoke',
        'slug' => strtolower($smokeModuleName).'-smoke-'.bin2hex(random_bytes(3)),
        'module_name' => $definitionJson['module']['name'],
        'entity_name' => $definitionJson['module']['singularLabel'],
        'resource_name' => $definitionJson['module']['resourceName'],
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definitionJson,
        'checksum' => $checksum,
    ]);

    smoke_check($checks, 'temporary BuilderDefinition created', $createdDefinition->exists);
    smoke_check($checks, 'definition extracts module name', $createdDefinition->module_name === $smokeModuleName);
    smoke_check($checks, 'definition checksum stored', $createdDefinition->checksum === $checksum);

    $versions = $app->make(BuilderDefinitionVersionService::class);
    $version = $versions->createVersion($createdDefinition);
    smoke_check($checks, 'version 1 created', $version->version === 1 && $version->builder_definition_id === $createdDefinition->getKey());

    $validator = $app->make(BuilderDefinitionValidator::class);
    $report = $validator->validate($createdDefinition->definition_json);
    smoke_check($checks, 'validation succeeds', ($report['valid'] ?? false) === true, json_encode($report));
    smoke_check($checks, 'validation has no unexpected errors', ($report['errors'] ?? []) === []);
    smoke_check($checks, 'validation warnings are expected or empty', ($report['warnings'] ?? []) === []);

    $createdDefinition->transitionTo(
        ($report['valid'] ?? false) ? BuilderDefinition::STATUS_VALIDATED : BuilderDefinition::STATUS_VALIDATION_FAILED,
        ['last_validation_report_json' => $report]
    );
    $versions->updateLatestValidationReport($createdDefinition->fresh(), $report);

    $preview = $app->make(BuilderPreviewService::class);
    $run = $preview->preview($createdDefinition->fresh());
    $createdDefinition = $createdDefinition->fresh();
    $smokePreviewPath = $run->preview_path;
    $smokeTempDefinitionPath = storage_path('app/builder-definitions/'.$createdDefinition->getKey());

    smoke_check($checks, 'BuilderPreviewRun exists', $run instanceof BuilderPreviewRun && $run->exists);
    smoke_check($checks, 'preview status is previewed', $run->status === 'previewed', (string) $run->error_text);
    smoke_check($checks, 'preview run output_text recorded', is_string($run->output_text) && $run->output_text !== '');
    smoke_check($checks, 'preview output confirms zero runtime writes', str_contains((string) $run->output_text, 'Real runtime writes performed: 0'));
    smoke_check($checks, 'preview path is recorded', is_string($run->preview_path) && $run->preview_path !== '');
    smoke_check($checks, 'preview path under storage/app/module-builder-preview', str_starts_with((string) $run->preview_path, storage_path('app/module-builder-preview')));
    smoke_check($checks, 'preview manifest records runtime writes 0', data_get($run->manifest_json, 'real_runtime_writes_performed') === 0);
    smoke_check($checks, 'definition status previewed', $createdDefinition->status === BuilderDefinition::STATUS_PREVIEWED);
    smoke_check($checks, 'definition last preview manifest stored', is_array($createdDefinition->last_preview_manifest_json));
    smoke_check($checks, 'no real runtime smoke module created', ! is_dir($root.'/modules/'.$smokeModuleName));

    Artisan::call('route:list', ['--path' => 'builder']);
    $routes = Artisan::output();
    foreach ([
        'builder/definitions',
        'builder/definitions/{builderDefinition}',
        'validate',
        'preview',
    ] as $routeNeedle) {
        smoke_check($checks, 'route:list contains '.$routeNeedle, str_contains($routes, $routeNeedle));
    }

    $controller = file_get_contents($root.'/app/Http/Controllers/Builder/BuilderDefinitionController.php') ?: '';
    smoke_check($checks, 'validate response includes validation_report', str_contains($controller, "'validation_report' => \$report"));
    smoke_check($checks, 'preview response includes output_text', str_contains($controller, "'output_text' => \$run->output_text"));
    smoke_check($checks, 'no publish route in controller', ! preg_match('/function\s+publish|->publish|publishDefinition/i', $controller));

    $api = file_get_contents($root.'/modules/Builder/resources/js/services/builderApi.js') ?: '';
    foreach (['listDefinitions', 'createDefinition', 'getDefinition', 'updateDefinition', 'validateDefinition', 'previewDefinition'] as $method) {
        smoke_check($checks, 'builderApi method '.$method.' exists', str_contains($api, 'function '.$method));
    }

    $ui = (file_get_contents($root.'/modules/Builder/resources/js/views/BuilderDefinitionView.vue') ?: '').
        (file_get_contents($root.'/modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue') ?: '');
    smoke_check($checks, 'UI contains save action', str_contains($ui, 'Save'));
    smoke_check($checks, 'UI contains validate action', str_contains($ui, 'Validate'));
    smoke_check($checks, 'UI contains preview action', str_contains($ui, 'Preview'));
    smoke_check($checks, 'UI contains error alert', str_contains($ui, 'apiError'));
    smoke_check($checks, 'UI contains no publish action', ! preg_match('/text=["\']Publish["\']|publishDefinition|runPublish|@publish/i', $ui));

    foreach ([
        'docs/ai/03-architecture/builder-studio-end-to-end-smoke.md',
        'docs/ai/05-rag/contracts/builder-studio-smoke-contract.json',
        'docs/ai/04-docops/history/2026-07-01-builder-studio-end-to-end-smoke.md',
    ] as $file) {
        smoke_check($checks, $file.' exists', is_file($root.'/'.$file));
    }

    foreach ([
        'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
        'docs/ai/05-rag/contracts/builder-studio-api-map.json',
        'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
        'docs/ai/05-rag/contracts/builder-studio-smoke-contract.json',
    ] as $jsonFile) {
        $decoded = json_decode(file_get_contents($root.'/'.$jsonFile) ?: '', true);
        smoke_check($checks, $jsonFile.' valid JSON', is_array($decoded), json_last_error_msg());
    }

    [$statusCode, $statusOutput] = smoke_run($root, 'git -c safe.directory='.escapeshellarg($root).' --no-pager status --short');
    smoke_check($checks, 'git status command succeeds', $statusCode === 0, trim($statusOutput));

    $changedPaths = array_filter(array_map(
        fn (string $line): string => trim(substr($line, 3)),
        preg_split('/\R/', trim($statusOutput))
    ));

    $forbiddenPrefixes = [
        'modules/Warehouse/',
        'modules/Core/',
        'modules/SaaS/',
        'modules/Updater/',
        'modules/Installer/',
        'database/migrations/',
        'vendor/',
        'node_modules/',
        'public/build/',
    ];

    foreach ($forbiddenPrefixes as $prefix) {
        smoke_check($checks, 'no '.$prefix.' files changed', ! array_filter($changedPaths, fn (string $path): bool => str_starts_with($path, $prefix)));
    }

    foreach ([
        'app/Console/Commands/ErpsmartMakeModuleCommand.php',
        'package.json',
        'package-lock.json',
        'composer.json',
        'composer.lock',
    ] as $path) {
        smoke_check($checks, 'no '.$path.' changed', ! in_array($path, $changedPaths, true));
    }

    smoke_check($checks, 'no SaaS/license/update code changed', ! preg_match('/SaaS|License|Licensing|Updater|Installer/i', $statusOutput));
} catch (Throwable $e) {
    smoke_check($checks, 'smoke execution completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        $createdDefinition->previewRuns()->delete();
        $createdDefinition->versions()->delete();
        $createdDefinition->delete();
        smoke_check($checks, 'temporary DB records cleaned', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }

    smoke_delete_directory_if_unique($smokeTempDefinitionPath, storage_path('app/builder-definitions'));
    smoke_delete_directory_if_unique($smokePreviewPath, storage_path('app/module-builder-preview'));
    smoke_check($checks, 'unique smoke preview directory cleaned or absent', ! $smokePreviewPath || ! is_dir($smokePreviewPath));
    smoke_check($checks, 'unique smoke temp definition directory cleaned or absent', ! $smokeTempDefinitionPath || ! is_dir($smokeTempDefinitionPath));
}

$failed = array_filter($checks, fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
