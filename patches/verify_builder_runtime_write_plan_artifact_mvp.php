<?php

declare(strict_types=1);

use App\Models\BuilderDefinition;
use App\Models\BuilderPublishApprovalRequest;
use App\Models\BuilderPublishAuditLog;
use App\Models\BuilderPublishExecution;
use App\Services\Builder\BuilderPublishApprovalService;
use App\Services\Builder\BuilderPublishExecutionPreparationService;
use App\Services\Builder\BuilderPublishStagedFileValidationService;
use App\Services\Builder\BuilderRuntimeWritePlanArtifactService;
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
    $full = project_path($path);

    return is_file($full) ? (string) file_get_contents($full) : '';
}

function json_contract(string $path): array
{
    global $errors;

    $full = project_path($path);
    if (! is_file($full)) {
        $errors[] = "Missing JSON contract: {$path}";

        return [];
    }

    $decoded = json_decode((string) file_get_contents($full), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Invalid JSON contract {$path}: ".json_last_error_msg();

        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function contains_text(string $haystack, string $needle, string $label): void
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
        'capabilities' => ['tableable' => true, 'hasDetailView' => true],
        'formLayout' => ['enabled' => false, 'mode' => 'standard', 'sections' => [], 'stepper' => ['enabled' => false, 'steps' => []], 'conditions' => []],
        'automation' => ['enabled' => false, 'workflows' => []],
    ];
}

foreach ([
    'app/Services/Builder/BuilderRuntimeWritePlanArtifactService.php',
    'docs/ai/03-architecture/builder-runtime-write-plan-artifact-mvp.md',
    'docs/ai/05-rag/contracts/builder-runtime-write-plan-artifact-contract.json',
    'docs/ai/04-docops/history/2026-07-02-builder-runtime-write-plan-artifact-mvp.md',
] as $path) {
    fail_if(! file_exists(project_path($path)), "Missing required file: {$path}");
}

$service = read_project_file('app/Services/Builder/BuilderRuntimeWritePlanArtifactService.php');
foreach ([
    'storage/app/builder-runtime-write-plans',
    "'runtime_writes_performed' => 0",
    "'publish_executed' => false",
    "'copy_to_runtime_executed' => false",
    'modules/Core',
    'modules/SaaS',
    'modules/Updater',
    'modules/Installer',
    'vendor',
    'node_modules',
    'public/build',
    '.env',
    'composer.json',
    'package.json',
    'routes/web.php',
    'resources/js/app.js',
    'database/migrations',
] as $required) {
    contains_text($service, $required, 'runtime write plan service');
}

$controller = read_project_file('app/Http/Controllers/Builder/BuilderPublishExecutionController.php');
contains_text($controller, 'runtimeWritePlan', 'controller');

$routes = read_project_file('routes/api.php');
contains_text($routes, 'runtime-write-plan', 'routes');
fail_if((bool) preg_match("#definitions/\\{builderDefinition\\}/publish['\"]#", $routes), 'Forbidden /publish endpoint exists.');
fail_if(str_contains($routes, 'execute-publish'), 'Forbidden execute-publish route exists.');
fail_if(str_contains($routes, 'copy-to-runtime'), 'Forbidden copy-to-runtime route exists.');
fail_if(str_contains($routes, 'rollback-executions'), 'Forbidden rollback route exists.');

$api = read_project_file('modules/Builder/resources/js/services/builderApi.js');
contains_text($api, 'createRuntimeWritePlan', 'builderApi');
foreach (['publishDefinition', 'executePublish', 'rollbackPublish', 'copyToRuntime', 'executeRuntimeWrite'] as $forbidden) {
    fail_if(str_contains($api, $forbidden), "Forbidden builderApi method exists: {$forbidden}");
}

$ui = read_project_file('modules/Builder/resources/js/components/BuilderPublishExecutionRecords.vue')
    .read_project_file('modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue')
    .read_project_file('modules/Builder/resources/js/views/BuilderDefinitionView.vue');
contains_text($ui, 'Create Runtime Write Plan', 'Builder UI');
contains_text($ui, 'Plan only', 'Builder UI');
contains_text($ui, 'does not copy files, run migrations, register routes, or publish', 'Builder UI');
foreach (['text="Publish"', 'Execute Publish', 'Deploy', 'text="Rollback"', 'Copy to runtime', 'Run migrations', 'Execute Runtime Write'] as $forbidden) {
    fail_if(str_contains($ui, $forbidden), "Forbidden UI text exists: {$forbidden}");
}

$planContract = json_contract('docs/ai/05-rag/contracts/builder-runtime-write-plan-artifact-contract.json');
$manifest = json_contract('docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json');
$boundaries = json_contract('docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json');
$toolRegistry = json_contract('docs/ai/05-rag/contracts/ai-tool-registry-contract.json');

fail_if(($planContract['current_implementation_status'] ?? null) !== 'runtime_write_plan_artifact_mvp', 'Runtime write plan contract must mark MVP status.');
fail_if(($planContract['runtime_writes_performed'] ?? null) !== 0, 'Runtime write plan contract runtime writes must be zero.');
fail_if(($planContract['publish_executed'] ?? null) !== false, 'Runtime write plan contract must not publish.');
fail_if(($planContract['copy_to_runtime_executed'] ?? null) !== false, 'Runtime write plan contract must not copy to runtime.');
contains_text(json_encode($manifest, JSON_PRETTY_PRINT) ?: '', 'runtime write plan artifact', 'RAG manifest');
contains_text(json_encode($boundaries, JSON_PRETTY_PRINT) ?: '', 'autonomously create runtime write plan', 'Safety boundaries');
contains_text(json_encode($boundaries, JSON_PRETTY_PRINT) ?: '', 'copy staged files to runtime', 'Safety boundaries');
contains_text(json_encode($toolRegistry, JSON_PRETTY_PRINT) ?: '', 'builder.create_runtime_write_plan', 'Tool Registry contract');

foreach (glob(project_path('database/migrations/*runtime*write*plan*.php')) ?: [] as $migration) {
    $errors[] = 'Forbidden runtime write plan migration exists: '.str_replace($root.DIRECTORY_SEPARATOR, '', $migration);
}

foreach ([
    'database/migrations',
    'modules/Warehouse',
    'modules/Core',
    'modules/SaaS',
    'modules/Updater',
    'modules/Installer',
    'package.json',
    'composer.json',
    'public/build',
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
] as $path) {
    $status = [];
    exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short -- '.escapeshellarg($path), $status);
    fail_if($status !== [], "Forbidden path has changes: {$path} ".implode('; ', $status));
}

if (! Schema::hasTable('builder_publish_executions')) {
    $errors[] = 'builder_publish_executions table is missing.';
}

$createdDefinitionIds = [];

try {
    if ($errors === []) {
        $approvalService = app(BuilderPublishApprovalService::class);
        $preparationService = app(BuilderPublishExecutionPreparationService::class);
        $validationService = app(BuilderPublishStagedFileValidationService::class);
        $planService = app(BuilderRuntimeWritePlanArtifactService::class);

        $moduleName = 'RuntimeWritePlanSmoke'.Str::random(8);
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

        $approval = $approvalService->approve($approvalService->requestApproval($definition));
        fail_if($approval->status !== BuilderPublishApprovalRequest::STATUS_APPROVED, 'Approval request was not approved.');

        $executionReport = $preparationService->prepare($definition->fresh());
        $execution = BuilderPublishExecution::findOrFail($executionReport['execution_id']);
        fail_if($execution->status !== BuilderPublishExecution::STATUS_PREFLIGHT_PASSED, 'Execution should be preflight_passed before staged validation.');

        File::ensureDirectoryExists(project_path($execution->staging_root.'/backend'));
        File::put(project_path($execution->staging_root.'/backend/Model.php.stub'), "<?php\n// runtime write plan smoke\n");
        File::put(project_path($execution->staging_root.'/backend/Migration.php.stub'), "<?php\n// planned migration only\n");

        $validationService->validate($execution->fresh());
        $execution->refresh();
        fail_if($execution->status !== BuilderPublishExecution::STATUS_STAGING_VALIDATED, 'Execution should be staging_validated before runtime write plan.');

        $planReport = $planService->plan($execution->fresh());
        $execution->refresh();

        fail_if(! in_array($execution->status, [BuilderPublishExecution::STATUS_RUNTIME_WRITE_PLANNED, BuilderPublishExecution::STATUS_RUNTIME_WRITE_PLAN_BLOCKED], true), 'Execution should be runtime_write_planned or runtime_write_plan_blocked.');
        fail_if(! str_starts_with((string) $planReport['runtime_write_plan_path'], 'storage/app/builder-runtime-write-plans/'), 'Runtime write plan path must be under storage.');
        fail_if(! is_file(project_path((string) $planReport['runtime_write_plan_path'])), 'Runtime write plan file missing.');
        $planJson = json_decode((string) file_get_contents(project_path((string) $planReport['runtime_write_plan_path'])), true);
        fail_if(! is_array($planJson), 'Runtime write plan JSON invalid.');
        fail_if(! array_key_exists('planned_writes', $planReport), 'planned_writes array missing.');
        fail_if(($planReport['runtime_writes_performed'] ?? null) !== 0, 'Runtime writes must be zero.');
        fail_if(($planReport['publish_executed'] ?? null) !== false, 'Publish executed must be false.');
        fail_if(($planReport['copy_to_runtime_executed'] ?? null) !== false, 'Copy to runtime must be false.');
        fail_if(is_dir(project_path('modules/'.$moduleName)), 'Runtime module directory was created.');
        fail_if($definition->fresh()->status === BuilderDefinition::STATUS_PUBLISHED, 'Definition was marked published.');
        fail_if(! BuilderPublishAuditLog::where('builder_definition_id', $definition->getKey())->whereIn('event_type', ['runtime_write_plan_created', 'runtime_write_plan_blocked'])->exists(), 'Runtime write plan audit event missing.');

        $rollbackManifest = json_decode((string) file_get_contents(project_path($execution->rollback_manifest_path)), true);
        fail_if(! is_array($rollbackManifest) || ! isset($rollbackManifest['runtime_write_plan']), 'Rollback manifest draft was not updated with planned write entries.');

        $unsafeModule = 'RuntimeWritePlanUnsafe'.Str::random(8);
        $unsafeJson = temp_definition($unsafeModule);
        $unsafeDefinition = BuilderDefinition::create([
            'uuid' => (string) Str::uuid(),
            'name' => $unsafeModule,
            'slug' => Str::slug($unsafeModule),
            'module_name' => $unsafeModule,
            'entity_name' => $unsafeModule.' Record',
            'resource_name' => Str::kebab($unsafeModule).'-records',
            'status' => BuilderDefinition::STATUS_DRAFT,
            'schema_version' => 1,
            'definition_json' => $unsafeJson,
            'checksum' => hash('sha256', json_encode($unsafeJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ]);
        $createdDefinitionIds[] = $unsafeDefinition->getKey();

        $approvalService->approve($approvalService->requestApproval($unsafeDefinition));
        $unsafeExecutionReport = $preparationService->prepare($unsafeDefinition->fresh());
        $unsafeExecution = BuilderPublishExecution::findOrFail($unsafeExecutionReport['execution_id']);

        File::ensureDirectoryExists(project_path($unsafeExecution->staging_root.'/modules/Core'));
        File::put(project_path($unsafeExecution->staging_root.'/modules/Core/Fake.php'), "<?php\n// forbidden future path\n");

        $fakeValidationPath = 'storage/app/builder-publish-staged-validations/'.$unsafeDefinition->getKey().'/'.$unsafeExecution->getKey().'/staged-file-validation.json';
        File::ensureDirectoryExists(project_path(dirname($fakeValidationPath)));
        File::put(project_path($fakeValidationPath), json_encode([
            'files' => [
                [
                    'relative_path' => 'modules/Core/Fake.php',
                    'absolute_scope' => 'staging',
                    'sha256' => hash('sha256', 'fake'),
                    'runtime_written' => false,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $unsafeExecution->fill([
            'status' => BuilderPublishExecution::STATUS_STAGING_VALIDATED,
            'metadata_json' => ['staged_file_validation_path' => $fakeValidationPath],
        ])->save();

        $unsafePlan = $planService->plan($unsafeExecution->fresh());
        fail_if(($unsafePlan['status'] ?? null) !== BuilderPublishExecution::STATUS_RUNTIME_WRITE_PLAN_BLOCKED, 'Forbidden path case should be blocked.');
        fail_if(($unsafePlan['runtime_writes_performed'] ?? null) !== 0, 'Forbidden path case runtime writes must be zero.');
        fail_if(($unsafePlan['copy_to_runtime_executed'] ?? null) !== false, 'Forbidden path case must not copy to runtime.');
        fail_if(is_dir(project_path('modules/'.$unsafeModule)), 'Unsafe case created runtime module directory.');
    }
} finally {
    foreach ($createdDefinitionIds as $definitionId) {
        BuilderPublishAuditLog::where('builder_definition_id', $definitionId)->delete();
        BuilderPublishExecution::where('builder_definition_id', $definitionId)->delete();
        BuilderPublishApprovalRequest::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();

        foreach ([
            'storage/app/builder-publish-candidates/'.$definitionId,
            'storage/app/builder-publish-dry-runs/'.$definitionId,
            'storage/app/builder-publish-readiness/'.$definitionId,
            'storage/app/builder-publish-staging/'.$definitionId,
            'storage/app/builder-publish-rollbacks/'.$definitionId,
            'storage/app/builder-publish-staged-validations/'.$definitionId,
            'storage/app/builder-runtime-write-plans/'.$definitionId,
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
echo "Builder runtime write plan artifact MVP verified. Runtime writes remain zero and no publish/copy action exists.\n";
