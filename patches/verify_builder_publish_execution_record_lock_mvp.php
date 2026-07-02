<?php

declare(strict_types=1);

use App\Models\BuilderDefinition;
use App\Models\BuilderPublishAuditLog;
use App\Models\BuilderPublishExecution;
use App\Services\Builder\BuilderPublishApprovalService;
use App\Services\Builder\BuilderPublishExecutionPreparationService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$errors = [];

function fail_if(bool $condition, string $message): void
{
    global $errors;

    if ($condition) {
        $errors[] = $message;
    }
}

function project_path(string $path): string
{
    global $root;

    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function read_project_file(string $path): string
{
    $fullPath = project_path($path);

    return is_file($fullPath) ? (string) file_get_contents($fullPath) : '';
}

function json_contract(string $path): array
{
    global $errors;

    $fullPath = project_path($path);
    if (! is_file($fullPath)) {
        $errors[] = "Missing JSON contract: {$path}";

        return [];
    }

    $data = json_decode((string) file_get_contents($fullPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Invalid JSON contract {$path}: ".json_last_error_msg();

        return [];
    }

    return is_array($data) ? $data : [];
}

function contains(string $haystack, string $needle, string $label): void
{
    fail_if(! str_contains($haystack, $needle), "{$label} missing {$needle}");
}

function temp_definition(string $moduleName): array
{
    return [
        'schemaVersion' => 1,
        'module' => [
            'name' => $moduleName,
            'namespace' => $moduleName,
            'singularLabel' => $moduleName.' Record',
            'pluralLabel' => $moduleName.' Records',
            'table' => Str::snake($moduleName).'_records',
            'routeName' => Str::kebab($moduleName).'-records',
            'resourceName' => Str::kebab($moduleName).'-records',
            'icon' => 'Settings',
        ],
        'resource' => [
            'modelClass' => $moduleName.'Record',
            'titleField' => 'title',
            'orderBy' => 'title',
            'globalSearchAction' => true,
            'hasDetailView' => true,
        ],
        'fields' => [
            ['name' => 'id', 'type' => 'id', 'label' => 'ID', 'primary' => false, 'required' => false],
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'primary' => true, 'required' => true],
            ['name' => 'active', 'type' => 'boolean', 'label' => 'Active', 'required' => false],
        ],
        'relations' => [],
        'capabilities' => [
            'tableable' => true,
            'hasDetailView' => true,
            'importable' => true,
        ],
        'formLayout' => [
            'enabled' => true,
            'mode' => 'standard',
            'sections' => [],
            'stepper' => ['enabled' => false, 'steps' => []],
            'conditions' => [],
        ],
        'automation' => [
            'enabled' => false,
            'workflows' => [],
        ],
    ];
}

foreach ([
    'database/migrations/2026_07_02_000006_create_builder_publish_executions_table.php',
    'app/Models/BuilderPublishExecution.php',
    'app/Services/Builder/BuilderPublishExecutionPreparationService.php',
    'app/Http/Controllers/Builder/BuilderPublishExecutionController.php',
    'docs/ai/03-architecture/builder-publish-execution-record-lock-mvp.md',
    'docs/ai/03-architecture/ai-agent-runtime-flow.md',
    'docs/ai/03-architecture/ai-tool-registry-strategy.md',
    'docs/ai/03-architecture/ai-builder-contract.md',
    'docs/ai/03-architecture/mcp-adapter-future-plan.md',
    'docs/ai/04-docops/history/2026-07-02-builder-publish-execution-record-lock-mvp.md',
] as $path) {
    fail_if(! file_exists(project_path($path)), "Missing required file: {$path}");
}

$routes = read_project_file('routes/api.php');
contains($routes, 'publish-executions', 'routes/api.php');
contains($routes, 'BuilderPublishExecutionController', 'routes/api.php');
fail_if((bool) preg_match("#definitions/\\{builderDefinition\\}/publish['\"]#", $routes), 'Forbidden /publish route exists.');
fail_if(str_contains($routes, 'execute-publish'), 'Forbidden execute-publish route exists.');
fail_if(str_contains($routes, 'rollback-executions'), 'Forbidden rollback route exists.');

$api = read_project_file('modules/Builder/resources/js/services/builderApi.js');
contains($api, 'listPublishExecutions', 'builderApi.js');
contains($api, 'createPublishExecutionRecord', 'builderApi.js');
foreach (['publishDefinition', 'executePublish', 'rollbackPublish'] as $forbidden) {
    fail_if(str_contains($api, $forbidden), "Forbidden builderApi method exists: {$forbidden}");
}

$ui = read_project_file('modules/Builder/resources/js/components/BuilderPublishExecutionRecords.vue')
    .read_project_file('modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue')
    .read_project_file('modules/Builder/resources/js/views/BuilderDefinitionView.vue');
contains($ui, 'Create Publish Execution Record', 'Builder UI');
contains($ui, 'Execution record only', 'Builder UI');
contains($ui, 'does not publish or write runtime files', 'Builder UI');
foreach (['text="Publish"', 'Execute Publish', 'Deploy', 'Copy to runtime', 'Run migrations'] as $forbidden) {
    fail_if(str_contains($ui, $forbidden), "Forbidden UI action text exists: {$forbidden}");
}

$apiMap = json_contract('docs/ai/05-rag/contracts/builder-publish-execution-record-api-map.json');
$toolRegistry = json_contract('docs/ai/05-rag/contracts/ai-tool-registry-contract.json');
$agentRuntime = json_contract('docs/ai/05-rag/contracts/ai-agent-runtime-contract.json');
$aiBuilder = json_contract('docs/ai/05-rag/contracts/ai-builder-action-contract.json');
$mcp = json_contract('docs/ai/05-rag/contracts/mcp-adapter-future-contract.json');
$manifest = json_contract('docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json');
$boundaries = json_contract('docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json');

fail_if(($toolRegistry['tool_registry_implemented'] ?? null) !== false, 'Tool Registry contract must mark implementation false.');
fail_if(($toolRegistry['mcp_server_implemented'] ?? null) !== false, 'Tool Registry contract must mark MCP server false.');
fail_if(($mcp['mcp_server_implemented'] ?? null) !== false, 'MCP contract must mark server false.');
fail_if(($mcp['erpsmart_core_depends_on_mcp'] ?? null) !== false, 'MCP contract must say ERPSMART core does not depend on MCP.');
fail_if(($agentRuntime['direct_db_write_allowed'] ?? null) !== false, 'AI Agent contract must forbid direct DB writes.');
fail_if(($agentRuntime['direct_runtime_file_write_allowed'] ?? null) !== false, 'AI Agent contract must forbid direct runtime file writes.');
fail_if(($aiBuilder['ai_may_publish'] ?? null) !== false, 'AI Builder action contract must forbid publish.');

$manifestText = json_encode($manifest, JSON_PRETTY_PRINT);
contains($manifestText ?: '', 'RAG is knowledge layer, Tool Registry is action layer, and MCP is future adapter.', 'RAG manifest');
contains($manifestText ?: '', 'BuilderPublishExecutionPreparationService', 'RAG manifest');
contains($manifestText ?: '', 'BuilderPublishExecutionRecords.vue', 'RAG manifest');

$boundariesText = json_encode($boundaries, JSON_PRETTY_PRINT);
foreach ([
    'autonomously create publish execution records',
    'call forbidden tools',
    'use MCP to bypass permissions, approval, or audit',
    'execute publish',
] as $requiredBoundary) {
    contains($boundariesText ?: '', $requiredBoundary, 'Safety boundaries');
}

foreach ([
    'modules/Warehouse',
    'modules/Core',
    'modules/SaaS',
    'modules/Updater',
    'modules/Installer',
    'package.json',
    'composer.json',
    'public/build',
] as $path) {
    $status = [];
    exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short -- '.escapeshellarg($path), $status);
    fail_if($status !== [], "Forbidden path has changes: {$path} ".implode('; ', $status));
}

if (! Schema::hasTable('builder_publish_executions')) {
    $errors[] = 'builder_publish_executions table is missing. Run: docker compose exec app php artisan migrate';
}

$createdDefinitionIds = [];
$moduleNames = [];

try {
    if ($errors === []) {
        $moduleName = 'ExecutionSmoke'.Str::random(8);
        $moduleNames[] = $moduleName;
        $definitionJson = temp_definition($moduleName);
        $definition = BuilderDefinition::create([
            'uuid' => (string) Str::uuid(),
            'name' => $moduleName,
            'slug' => Str::slug($moduleName),
            'module_name' => $moduleName,
            'entity_name' => $moduleName.' Record',
            'resource_name' => Str::kebab($moduleName).'-records',
            'status' => BuilderDefinition::STATUS_DRAFT,
            'schema_version' => 1,
            'definition_json' => $definitionJson,
            'checksum' => hash('sha256', json_encode($definitionJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ]);
        $createdDefinitionIds[] = $definition->getKey();

        $approvalService = app(BuilderPublishApprovalService::class);
        $approval = $approvalService->requestApproval($definition);
        $approval = $approvalService->approve($approval);

        $executionService = app(BuilderPublishExecutionPreparationService::class);
        $report = $executionService->prepare($definition->fresh());
        $execution = BuilderPublishExecution::find($report['execution_id']);

        fail_if(! $execution, 'Execution record was not created.');
        fail_if(($report['status'] ?? null) !== BuilderPublishExecution::STATUS_PREFLIGHT_PASSED, 'Execution status should be preflight_passed.');
        fail_if(($report['lock']['acquired'] ?? null) !== true, 'Lock should be acquired.');
        fail_if(($report['lock']['released'] ?? null) !== true, 'Lock should be released.');
        fail_if(($report['writes_performed'] ?? null) !== 0, 'writes_performed must be 0.');
        fail_if(($report['runtime_writes_performed'] ?? null) !== 0, 'runtime_writes_performed must be 0.');
        fail_if(($report['publish_executed'] ?? null) !== false, 'publish_executed must be false.');
        fail_if(($report['runtime_module_effect'] ?? null) !== 'none', 'runtime_module_effect must be none.');
        fail_if(! str_starts_with((string) ($report['staging_root'] ?? ''), 'storage/app/builder-publish-staging/'), 'staging_root must be under storage/app/builder-publish-staging.');
        fail_if(! str_starts_with((string) ($report['rollback_manifest_path'] ?? ''), 'storage/app/builder-publish-rollbacks/'), 'rollback manifest must be under storage/app/builder-publish-rollbacks.');
        fail_if(! is_file(project_path((string) $report['rollback_manifest_path'])), 'Rollback manifest draft file missing.');

        $rollbackJson = json_decode((string) file_get_contents(project_path((string) $report['rollback_manifest_path'])), true);
        fail_if(! is_array($rollbackJson), 'Rollback manifest JSON invalid.');
        fail_if(($rollbackJson['publish_executed'] ?? null) !== false, 'Rollback manifest must not mark publish executed.');
        fail_if(($rollbackJson['runtime_writes_performed'] ?? null) !== 0, 'Rollback manifest runtime writes must be zero.');
        fail_if($definition->fresh()->status === BuilderDefinition::STATUS_PUBLISHED, 'BuilderDefinition status must not be published.');
        fail_if(is_dir(project_path('modules/'.$moduleName)), 'Runtime module directory was created.');

        foreach ([
            'publish_preflight_started',
            'publish_lock_acquired',
            'rollback_manifest_created',
            'publish_staging_created',
            'publish_lock_released',
        ] as $event) {
            fail_if(! BuilderPublishAuditLog::where('builder_definition_id', $definition->getKey())->where('event_type', $event)->exists(), "Missing audit event: {$event}");
        }

        $failedModule = 'ExecutionSmokeFail'.Str::random(8);
        $moduleNames[] = $failedModule;
        $failedJson = temp_definition($failedModule);
        $failedDefinition = BuilderDefinition::create([
            'uuid' => (string) Str::uuid(),
            'name' => $failedModule,
            'slug' => Str::slug($failedModule),
            'module_name' => $failedModule,
            'entity_name' => $failedModule.' Record',
            'resource_name' => Str::kebab($failedModule).'-records',
            'status' => BuilderDefinition::STATUS_DRAFT,
            'schema_version' => 1,
            'definition_json' => $failedJson,
            'checksum' => hash('sha256', json_encode($failedJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ]);
        $createdDefinitionIds[] = $failedDefinition->getKey();

        $failedReport = $executionService->prepare($failedDefinition);
        fail_if(($failedReport['status'] ?? null) !== BuilderPublishExecution::STATUS_PREFLIGHT_FAILED, 'Unapproved definition should fail preflight.');
        fail_if(($failedReport['runtime_writes_performed'] ?? null) !== 0, 'Failure case runtime writes must be zero.');
        fail_if(is_dir(project_path('modules/'.$failedModule)), 'Failure case created a runtime module directory.');
    }
} finally {
    foreach ($createdDefinitionIds as $definitionId) {
        BuilderPublishAuditLog::where('builder_definition_id', $definitionId)->delete();
        BuilderPublishExecution::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();

        foreach ([
            'storage/app/builder-publish-candidates/'.$definitionId,
            'storage/app/builder-publish-dry-runs/'.$definitionId,
            'storage/app/builder-publish-readiness/'.$definitionId,
            'storage/app/builder-publish-staging/'.$definitionId,
            'storage/app/builder-publish-rollbacks/'.$definitionId,
        ] as $directory) {
            if (is_dir(project_path($directory))) {
                File::deleteDirectory(project_path($directory));
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS\n";
echo "Publish execution record lock MVP verified. Runtime writes remain zero and no publish execution exists.\n";
