<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;

function lifecycle_mvp_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function lifecycle_mvp_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function lifecycle_mvp_json(string $root, string $path): ?array
{
    $decoded = json_decode(lifecycle_mvp_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function lifecycle_mvp_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];

        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = lifecycle_mvp_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Builder\BuilderDefinitionController;
use App\Models\BuilderDefinition;
use App\Models\BuilderPreviewRun;
use App\Models\BuilderDefinitionVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

foreach ([
    'docs/ai/03-architecture/builder-definition-lifecycle-mvp.md',
    'docs/ai/04-docops/history/2026-07-02-builder-definition-lifecycle-mvp.md',
] as $file) {
    lifecycle_mvp_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    'docs/ai/05-rag/contracts/builder-definition-lifecycle-api-map.json',
    'docs/ai/05-rag/contracts/builder-lifecycle-state-machine.json',
    'docs/ai/05-rag/contracts/builder-module-removal-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-studio-api-map.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
] as $jsonFile) {
    lifecycle_mvp_check($checks, $jsonFile.' valid JSON', lifecycle_mvp_json($root, $jsonFile) !== null, json_last_error_msg());
}

$routesSource = lifecycle_mvp_read($root, 'routes/api.php');
$controllerSource = lifecycle_mvp_read($root, 'app/Http/Controllers/Builder/BuilderDefinitionController.php');
$modelSource = lifecycle_mvp_read($root, 'app/Models/BuilderDefinition.php');
$apiSource = lifecycle_mvp_read($root, 'modules/Builder/resources/js/services/builderApi.js');
$indexSource = lifecycle_mvp_read($root, 'modules/Builder/resources/js/views/BuilderDefinitionsIndex.vue');
$viewSource = lifecycle_mvp_read($root, 'modules/Builder/resources/js/views/BuilderDefinitionView.vue');
$summarySource = lifecycle_mvp_read($root, 'modules/Builder/resources/js/components/BuilderDefinitionSummary.vue');
$manifestText = lifecycle_mvp_flatten(lifecycle_mvp_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$safetyText = lifecycle_mvp_flatten(lifecycle_mvp_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);
$lifecycleApiText = lifecycle_mvp_flatten(lifecycle_mvp_json($root, 'docs/ai/05-rag/contracts/builder-definition-lifecycle-api-map.json') ?? []);
$stateMachineText = lifecycle_mvp_flatten(lifecycle_mvp_json($root, 'docs/ai/05-rag/contracts/builder-lifecycle-state-machine.json') ?? []);
$removalText = lifecycle_mvp_flatten(lifecycle_mvp_json($root, 'docs/ai/05-rag/contracts/builder-module-removal-safety-contract.json') ?? []);

lifecycle_mvp_check($checks, 'route registration includes archive', str_contains($routesSource, 'archive'));
lifecycle_mvp_check($checks, 'route registration includes restore', str_contains($routesSource, 'restore'));
lifecycle_mvp_check($checks, 'route registration includes destroy', str_contains($routesSource, "'destroy'") || str_contains($routesSource, '"destroy"'));
lifecycle_mvp_check($checks, 'controller has archive method', preg_match('/function\s+archive\s*\(/', $controllerSource) === 1);
lifecycle_mvp_check($checks, 'controller has restore method', preg_match('/function\s+restore\s*\(/', $controllerSource) === 1);
lifecycle_mvp_check($checks, 'controller has destroy method', preg_match('/function\s+destroy\s*\(/', $controllerSource) === 1);
lifecycle_mvp_check($checks, 'controller uses DB transaction for delete', str_contains($controllerSource, 'DB::transaction'));
lifecycle_mvp_check($checks, 'controller deletes versions relation', str_contains($controllerSource, 'versions()->delete()'));
lifecycle_mvp_check($checks, 'controller deletes previewRuns relation', str_contains($controllerSource, 'previewRuns()->delete()'));
lifecycle_mvp_check($checks, 'model has unpublished status guard', str_contains($modelSource, 'UNPUBLISHED_STATUSES') && str_contains($modelSource, 'canBeDeletedAsDraft'));

foreach (['archiveDefinition', 'restoreDefinition', 'deleteDefinition'] as $method) {
    lifecycle_mvp_check($checks, 'builderApi has '.$method, str_contains($apiSource, 'function '.$method));
}

foreach (['Active', 'Archived', 'All'] as $filter) {
    lifecycle_mvp_check($checks, 'index UI has '.$filter.' filter', str_contains($indexSource, 'text="'.$filter.'"'));
}

lifecycle_mvp_check($checks, 'UI has archive action', str_contains($indexSource.$viewSource, 'Archive') && str_contains($indexSource.$viewSource, 'archiveDraft'));
lifecycle_mvp_check($checks, 'UI has restore action', str_contains($indexSource.$viewSource, 'Restore') && str_contains($indexSource.$viewSource, 'restoreDraft'));
lifecycle_mvp_check($checks, 'UI has delete draft action', str_contains($indexSource.$viewSource, 'Delete draft'));
lifecycle_mvp_check($checks, 'UI contains control-plane-only delete warning', str_contains($indexSource.$viewSource, 'This deletes only the Builder draft/control-plane records. It does not delete runtime modules or database tables.'));
lifecycle_mvp_check($checks, 'UI shows archived notice', str_contains($viewSource, 'This Builder definition is archived.'));
lifecycle_mvp_check($checks, 'no publish button exists', ! preg_match('/text=["\']Publish["\']|runPublish|publishDefinition|@publish/i', $indexSource.$viewSource.$summarySource));
lifecycle_mvp_check($checks, 'no uninstall button exists', ! preg_match('/text=["\']Uninstall["\']|runUninstall|uninstallModule|@uninstall/i', $indexSource.$viewSource.$summarySource));
lifecycle_mvp_check($checks, 'no runtime delete button exists', ! preg_match('/text=["\']Delete runtime|text=["\']Runtime delete|deleteRuntime|runRuntimeDelete|@delete-runtime/i', $indexSource.$viewSource.$summarySource));

lifecycle_mvp_check($checks, 'RAG manifest mentions lifecycle MVP', str_contains($manifestText, 'builder-definition-lifecycle-mvp.md') && str_contains($manifestText, 'control-plane'));
lifecycle_mvp_check($checks, 'safety boundaries allow draft delete', str_contains($safetyText, 'delete unpublished Builder draft/control-plane records'));
lifecycle_mvp_check($checks, 'safety boundaries forbid runtime delete', str_contains($safetyText, 'delete runtime modules') && str_contains($safetyText, 'delete runtime files'));
lifecycle_mvp_check($checks, 'lifecycle API map marks runtime_module_effect none', substr_count($lifecycleApiText, 'runtime_module_effect none') >= 3);
lifecycle_mvp_check($checks, 'lifecycle API map marks publish_related false', substr_count($lifecycleApiText, 'publish_related false') >= 3);
lifecycle_mvp_check($checks, 'state machine marks archive supported in current MVP', str_contains($stateMachineText, 'archive') && str_contains($stateMachineText, 'current_mvp true'));
lifecycle_mvp_check($checks, 'state machine keeps publish future', str_contains($stateMachineText, 'publish_ready') && str_contains($stateMachineText, 'current_mvp false'));
lifecycle_mvp_check($checks, 'removal contract marks delete draft current supported', str_contains($removalText, 'delete_draft_definition') && str_contains($removalText, 'current_supported_control_plane_only'));
lifecycle_mvp_check($checks, 'removal contract keeps destructive delete forbidden', str_contains($removalText, 'destructive_delete') && str_contains($removalText, 'allowed_in_current_mvp false'));

try {
    foreach (['builder_definitions', 'builder_definition_versions', 'builder_preview_runs'] as $table) {
        lifecycle_mvp_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }

    if (array_filter($checks, static fn (array $check): bool => $check[1] === false && str_contains($check[0], ' table exists'))) {
        throw new RuntimeException('Builder tables are missing; lifecycle smoke cannot continue.');
    }

    $definitionJson = [
        'schemaVersion' => 1,
        'module' => [
            'name' => 'LifecycleSmokeRecords',
            'singularLabel' => 'LifecycleSmokeRecord',
            'resourceName' => 'lifecycle-smoke-records',
        ],
    ];

    $createdDefinition = BuilderDefinition::create([
        'name' => 'Lifecycle Smoke '.bin2hex(random_bytes(3)),
        'slug' => 'lifecycle-smoke-'.bin2hex(random_bytes(5)),
        'module_name' => 'LifecycleSmokeRecords',
        'entity_name' => 'LifecycleSmokeRecord',
        'resource_name' => 'lifecycle-smoke-records',
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definitionJson,
        'checksum' => hash('sha256', json_encode($definitionJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ]);

    $version = BuilderDefinitionVersion::create([
        'builder_definition_id' => $createdDefinition->getKey(),
        'version' => 1,
        'status' => BuilderDefinition::STATUS_DRAFT,
        'definition_json' => $definitionJson,
        'checksum' => $createdDefinition->checksum,
    ]);

    $previewRun = BuilderPreviewRun::create([
        'builder_definition_id' => $createdDefinition->getKey(),
        'status' => 'previewed',
        'preview_path' => storage_path('app/module-builder-preview/LifecycleSmokeRecords'),
        'manifest_json' => ['real_runtime_writes_performed' => 0],
        'output_text' => 'Real runtime writes performed: 0',
    ]);

    lifecycle_mvp_check($checks, 'temporary BuilderDefinition created', $createdDefinition->exists);
    lifecycle_mvp_check($checks, 'temporary version created', $version->exists);
    lifecycle_mvp_check($checks, 'temporary preview run created', $previewRun->exists);

    $controller = $app->make(BuilderDefinitionController::class);

    $archiveResponse = $controller->archive($createdDefinition->fresh());
    $archivedDefinition = $createdDefinition->fresh();
    lifecycle_mvp_check($checks, 'archive response succeeds', $archiveResponse->getStatusCode() === 200);
    lifecycle_mvp_check($checks, 'definition status archived', $archivedDefinition->status === BuilderDefinition::STATUS_ARCHIVED);

    $restoreResponse = $controller->restore($archivedDefinition);
    $restoredDefinition = $createdDefinition->fresh();
    lifecycle_mvp_check($checks, 'restore response succeeds', $restoreResponse->getStatusCode() === 200);
    lifecycle_mvp_check($checks, 'definition status restored to draft', $restoredDefinition->status === BuilderDefinition::STATUS_DRAFT);

    $deleteResponse = $controller->destroy($restoredDefinition);
    lifecycle_mvp_check($checks, 'delete draft response succeeds', $deleteResponse->getStatusCode() === 200);
    lifecycle_mvp_check($checks, 'temporary definition deleted', BuilderDefinition::whereKey($createdDefinition->getKey())->doesntExist());
    lifecycle_mvp_check($checks, 'temporary versions deleted', BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->doesntExist());
    lifecycle_mvp_check($checks, 'temporary preview runs deleted', BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->doesntExist());
    lifecycle_mvp_check($checks, 'no real runtime lifecycle smoke module created', ! is_dir($root.'/modules/LifecycleSmokeRecords'));

    $createdDefinition = null;

    Artisan::call('route:list', ['--path' => 'builder']);
    $routes = Artisan::output();
    foreach (['archive', 'restore', 'DELETE', 'builder/definitions/{builderDefinition}'] as $routeNeedle) {
        lifecycle_mvp_check($checks, 'route:list contains '.$routeNeedle, str_contains($routes, $routeNeedle));
    }
} catch (Throwable $e) {
    lifecycle_mvp_check($checks, 'runtime lifecycle smoke completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        BuilderPreviewRun::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinitionVersion::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();
        lifecycle_mvp_check($checks, 'temporary DB records cleaned after failure', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }
}

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
lifecycle_mvp_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));

$changedPaths = array_filter(array_map(
    static fn (string $line): string => trim(substr($line, 3)),
    preg_split('/\R/', trim($statusOutput))
));

foreach ([
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
    'app/Services/Builder/BuilderPreviewService.php',
    'app/Services/Builder/BuilderDefinitionValidator.php',
    'database/migrations/',
    'modules/Warehouse/',
    'modules/Core/',
    'modules/SaaS/',
    'modules/Updater/',
    'modules/Installer/',
    'vendor/',
    'node_modules/',
    'public/build/',
] as $forbiddenPath) {
    $changed = str_ends_with($forbiddenPath, '/')
        ? array_filter($changedPaths, static fn (string $path): bool => str_starts_with($path, $forbiddenPath))
        : in_array($forbiddenPath, $changedPaths, true);

    lifecycle_mvp_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    lifecycle_mvp_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
