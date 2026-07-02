<?php

declare(strict_types=1);

use App\Models\BuilderDefinition;
use App\Models\BuilderPublishApprovalRequest;
use App\Models\BuilderPublishAuditLog;
use App\Models\BuilderPublishExecution;
use App\Services\Builder\BuilderPublishApprovalService;
use App\Services\Builder\BuilderPublishExecutionPreparationService;
use App\Services\Builder\BuilderPublishStagedFileValidationService;
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
        'capabilities' => ['tableable' => true, 'hasDetailView' => true, 'importable' => true],
        'formLayout' => ['enabled' => false, 'mode' => 'standard', 'sections' => [], 'stepper' => ['enabled' => false, 'steps' => []], 'conditions' => []],
        'automation' => ['enabled' => false, 'workflows' => []],
    ];
}

foreach ([
    'app/Services/Builder/BuilderPublishStagedFileValidationService.php',
    'docs/ai/03-architecture/builder-publish-staged-file-validation-mvp.md',
    'docs/ai/05-rag/contracts/builder-publish-staged-file-validation-contract.json',
    'docs/ai/04-docops/history/2026-07-02-builder-publish-staged-file-validation-mvp.md',
] as $path) {
    fail_if(! file_exists(project_path($path)), "Missing required file: {$path}");
}

$service = read_project_file('app/Services/Builder/BuilderPublishStagedFileValidationService.php');
foreach ([
    'storage/app/builder-publish-staged-validations',
    "'runtime_writes_performed' => 0",
    "'publish_executed' => false",
    'modules/',
    'database/migrations/',
    'routes/',
    'public/build/',
    'vendor/',
    'node_modules/',
    '.env',
] as $required) {
    contains_text($service, $required, 'staged validation service');
}

$controller = read_project_file('app/Http/Controllers/Builder/BuilderPublishExecutionController.php');
contains_text($controller, 'validateStagedFiles', 'controller');

$routes = read_project_file('routes/api.php');
contains_text($routes, 'validate-staged-files', 'routes');
fail_if((bool) preg_match("#definitions/\\{builderDefinition\\}/publish['\"]#", $routes), 'Forbidden /publish endpoint exists.');
fail_if(str_contains($routes, 'execute-publish'), 'Forbidden execute-publish route exists.');
fail_if(str_contains($routes, 'rollback-executions'), 'Forbidden rollback route exists.');

$api = read_project_file('modules/Builder/resources/js/services/builderApi.js');
contains_text($api, 'validatePublishExecutionStagedFiles', 'builderApi');
foreach (['publishDefinition', 'executePublish', 'rollbackPublish', 'copyToRuntime'] as $forbidden) {
    fail_if(str_contains($api, $forbidden), "Forbidden builderApi method exists: {$forbidden}");
}

$ui = read_project_file('modules/Builder/resources/js/components/BuilderPublishExecutionRecords.vue')
    .read_project_file('modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue')
    .read_project_file('modules/Builder/resources/js/views/BuilderDefinitionView.vue');
contains_text($ui, 'Validate Staged Files', 'Builder UI');
contains_text($ui, 'Staged validation only', 'Builder UI');
contains_text($ui, 'does not copy files to runtime, run migrations, register routes, or publish', 'Builder UI');
foreach (['text="Publish"', 'Execute Publish', 'Deploy', 'text="Rollback"', 'Copy to runtime', 'Run migrations'] as $forbidden) {
    fail_if(str_contains($ui, $forbidden), "Forbidden UI text exists: {$forbidden}");
}

$validationContract = json_contract('docs/ai/05-rag/contracts/builder-publish-staged-file-validation-contract.json');
$manifest = json_contract('docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json');
$boundaries = json_contract('docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json');
$toolRegistry = json_contract('docs/ai/05-rag/contracts/ai-tool-registry-contract.json');

fail_if(($validationContract['current_implementation_status'] ?? null) !== 'staged_file_validation_mvp', 'Staged validation contract must mark MVP status.');
fail_if(($validationContract['runtime_writes_performed'] ?? null) !== 0, 'Staged validation contract runtime writes must be zero.');
fail_if(($validationContract['publish_executed'] ?? null) !== false, 'Staged validation contract must not publish.');
contains_text(json_encode($manifest, JSON_PRETTY_PRINT) ?: '', 'BuilderPublishStagedFileValidationService', 'RAG manifest');
contains_text(json_encode($boundaries, JSON_PRETTY_PRINT) ?: '', 'autonomously validate staged files', 'Safety boundaries');
contains_text(json_encode($boundaries, JSON_PRETTY_PRINT) ?: '', 'copy staged files to runtime', 'Safety boundaries');
contains_text(json_encode($toolRegistry, JSON_PRETTY_PRINT) ?: '', 'builder.validate_staged_files', 'Tool Registry contract');

foreach (glob(project_path('database/migrations/*staged*validation*.php')) ?: [] as $migration) {
    $errors[] = 'Forbidden staged validation migration exists: '.str_replace($root.DIRECTORY_SEPARATOR, '', $migration);
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

        $moduleName = 'StagedValidationSmoke'.Str::random(8);
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
        File::put(project_path($execution->staging_root.'/backend/Model.php.stub'), "<?php\n// staged validation smoke\n");

        $validationReport = $validationService->validate($execution->fresh());
        $execution->refresh();

        fail_if($execution->status !== BuilderPublishExecution::STATUS_STAGING_VALIDATED, 'Execution should be staging_validated.');
        fail_if(($validationReport['runtime_writes_performed'] ?? null) !== 0, 'Runtime writes must be zero.');
        fail_if(($validationReport['publish_executed'] ?? null) !== false, 'Publish executed must be false.');
        fail_if(! str_starts_with((string) $validationReport['validation_report_path'], 'storage/app/builder-publish-staged-validations/'), 'Validation report path must be under storage.');
        fail_if(! is_file(project_path((string) $validationReport['validation_report_path'])), 'Validation report file missing.');
        $validationJson = json_decode((string) file_get_contents(project_path((string) $validationReport['validation_report_path'])), true);
        fail_if(! is_array($validationJson), 'Validation report JSON invalid.');
        fail_if(($validationReport['summary']['total_files'] ?? 0) < 2, 'Expected staged and rollback files to be discovered.');
        foreach ($validationReport['files'] ?? [] as $file) {
            fail_if(empty($file['sha256']), 'A discovered file is missing sha256.');
            fail_if(($file['runtime_written'] ?? true) !== false, 'A discovered file claims runtime_written true.');
        }
        fail_if(! BuilderPublishAuditLog::where('builder_definition_id', $definition->getKey())->where('event_type', 'publish_staging_validated')->exists(), 'publish_staging_validated audit event missing.');
        fail_if(is_dir(project_path('modules/'.$moduleName)), 'Runtime module directory was created.');
        fail_if($definition->fresh()->status === BuilderDefinition::STATUS_PUBLISHED, 'Definition was marked published.');

        $unsafeModule = 'StagedValidationUnsafe'.Str::random(8);
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
        File::ensureDirectoryExists(project_path($unsafeExecution->staging_root.'/modules/Fake'));
        File::put(project_path($unsafeExecution->staging_root.'/modules/Fake/Runtime.php'), "<?php\n// forbidden staged path\n");

        $unsafeValidation = $validationService->validate($unsafeExecution->fresh());
        fail_if(($unsafeValidation['status'] ?? null) !== BuilderPublishExecution::STATUS_STAGING_VALIDATION_FAILED, 'Unsafe staged path should fail validation.');
        fail_if(($unsafeValidation['runtime_writes_performed'] ?? null) !== 0, 'Unsafe validation runtime writes must be zero.');
        fail_if(! BuilderPublishAuditLog::where('builder_definition_id', $unsafeDefinition->getKey())->where('event_type', 'publish_staging_validation_failed')->exists(), 'publish_staging_validation_failed audit event missing.');
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
echo "Builder publish staged file validation MVP verified. Runtime writes remain zero and no publish/copy action exists.\n";
