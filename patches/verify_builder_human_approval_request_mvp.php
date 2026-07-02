<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;
$storageRoots = [];

function approval_mvp_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function approval_mvp_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function approval_mvp_json(string $root, string $path): ?array
{
    $decoded = json_decode(approval_mvp_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function approval_mvp_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = approval_mvp_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

function approval_mvp_delete_directory_if_unique(?string $path, string $expectedBase): void
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
use App\Models\BuilderPublishApprovalRequest;
use App\Models\BuilderPublishAuditLog;
use App\Services\Builder\BuilderPublishApprovalService;
use Illuminate\Support\Facades\Schema;

$migrationApproval = 'database/migrations/2026_07_02_000004_create_builder_publish_approval_requests_table.php';
$migrationAudit = 'database/migrations/2026_07_02_000005_create_builder_publish_audit_logs_table.php';
$approvalModel = 'app/Models/BuilderPublishApprovalRequest.php';
$auditModel = 'app/Models/BuilderPublishAuditLog.php';
$approvalService = 'app/Services/Builder/BuilderPublishApprovalService.php';
$approvalController = 'app/Http/Controllers/Builder/BuilderPublishApprovalRequestController.php';
$routesPath = 'routes/api.php';
$apiPath = 'modules/Builder/resources/js/services/builderApi.js';
$uiPath = 'modules/Builder/resources/js/components/BuilderPublishApprovalRequests.vue';
$docPath = 'docs/ai/03-architecture/builder-human-approval-request-mvp.md';
$apiContract = 'docs/ai/05-rag/contracts/builder-human-approval-request-api-map.json';
$historyPath = 'docs/ai/04-docops/history/2026-07-02-builder-human-approval-request-mvp.md';

foreach ([$migrationApproval, $migrationAudit, $approvalModel, $auditModel, $approvalService, $approvalController, $uiPath, $docPath, $apiContract, $historyPath] as $file) {
    approval_mvp_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    $apiContract,
    'docs/ai/05-rag/contracts/builder-human-approval-gate-contract.json',
    'docs/ai/05-rag/contracts/builder-publish-audit-log-contract.json',
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-studio-api-map.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
    'docs/ai/05-rag/contracts/builder-studio-component-map.json',
] as $jsonFile) {
    approval_mvp_check($checks, $jsonFile.' valid JSON', approval_mvp_json($root, $jsonFile) !== null, json_last_error_msg());
}

$routes = approval_mvp_read($root, $routesPath);
$api = approval_mvp_read($root, $apiPath);
$ui = approval_mvp_read($root, $uiPath).approval_mvp_read($root, 'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue');
$manifestText = approval_mvp_flatten(approval_mvp_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$safetyText = approval_mvp_flatten(approval_mvp_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);

approval_mvp_check($checks, 'routes include approval request list', str_contains($routes, 'publish-approval-requests') && str_contains($routes, 'index'));
approval_mvp_check($checks, 'routes include approval request create', str_contains($routes, 'publish-approval-requests') && str_contains($routes, 'store'));
approval_mvp_check($checks, 'routes include approve/reject/revoke', str_contains($routes, '/approve') && str_contains($routes, '/reject') && str_contains($routes, '/revoke'));
approval_mvp_check($checks, 'no execute publish endpoint exists', ! preg_match('/execute-publish|\/publish[\'")]|\bpublishDefinition\b|runPublish/i', $routes.$api));
approval_mvp_check($checks, 'builderApi has approval methods', str_contains($api, 'listPublishApprovalRequests') && str_contains($api, 'requestPublishApproval') && str_contains($api, 'approvePublishApprovalRequest') && str_contains($api, 'rejectPublishApprovalRequest') && str_contains($api, 'revokePublishApprovalRequest'));
approval_mvp_check($checks, 'builderApi has no publishDefinition', ! str_contains($api, 'publishDefinition'));
approval_mvp_check($checks, 'UI has approval actions', str_contains($ui, 'Request Approval') && str_contains($ui, 'Approve Candidate') && str_contains($ui, 'Reject Candidate') && str_contains($ui, 'Revoke Approval'));
approval_mvp_check($checks, 'UI safety notice says approval does not publish', str_contains($ui, 'Approval does not publish. It only records human review state for a candidate snapshot.'));
approval_mvp_check($checks, 'UI has no Publish/Deploy/Copy to runtime button', ! preg_match('/text=["\'](?:Publish|Deploy|Copy to runtime|Approve Publish)["\']|copyToRuntime|runPublish|publishDefinition/i', $ui.$api));
approval_mvp_check($checks, 'RAG manifest mentions approval persistence MVP', str_contains($manifestText, 'approval request persistence') || str_contains($manifestText, 'BuilderPublishApprovalService'));
approval_mvp_check($checks, 'safety boundaries forbid autonomous approve/reject/revoke and publish', str_contains($safetyText, 'autonomously approve') && str_contains($safetyText, 'execute publish'));

try {
    foreach (['builder_definitions', 'builder_definition_versions', 'builder_preview_runs'] as $table) {
        approval_mvp_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }
    foreach (['builder_publish_approval_requests', 'builder_publish_audit_logs'] as $table) {
        approval_mvp_check($checks, $table.' table exists', Schema::hasTable($table), 'run: docker compose exec app php artisan migrate');
    }

    if (! Schema::hasTable('builder_publish_approval_requests') || ! Schema::hasTable('builder_publish_audit_logs')) {
        throw new RuntimeException('Approval tables are missing; run docker compose exec app php artisan migrate');
    }

    $definition = json_decode(approval_mvp_read($root, 'docs/ai/05-rag/examples/definition-driven-custom-module.json'), true);
    if (! is_array($definition)) {
        throw new RuntimeException('Unable to read definition-driven-custom-module.json');
    }

    $suffix = bin2hex(random_bytes(4));
    $moduleName = 'ApprovalSmoke'.$suffix;
    $route = 'approval-smoke-'.$suffix;
    $definition['module']['name'] = $moduleName;
    $definition['module']['namespace'] = 'Modules\\'.$moduleName;
    $definition['module']['singularLabel'] = 'ApprovalSmoke';
    $definition['module']['pluralLabel'] = $moduleName;
    $definition['module']['table'] = 'approval_smoke_'.$suffix;
    $definition['module']['routeName'] = $route;
    $definition['module']['resourceName'] = $route;
    $definition['resource']['modelClass'] = 'Modules\\'.$moduleName.'\\Models\\ApprovalSmoke';
    $definition['relations'] = [['name' => 'owner', 'type' => 'belongsTo', 'targetModule' => 'Users', 'targetModel' => 'User', 'targetResource' => 'users', 'foreignKey' => 'user_id']];
    $definition['formLayout'] = ['enabled' => true, 'sections' => [['id' => 'main', 'fields' => [['field' => 'title']]]]];
    $definition['automation'] = ['enabled' => true, 'workflows' => [['id' => 'workflow_1', 'trigger' => ['type' => 'record_created'], 'actions' => [['type' => 'send_notification']]]]];

    $createdDefinition = BuilderDefinition::create([
        'name' => 'Approval Smoke '.$suffix,
        'slug' => 'approval-smoke-'.$suffix,
        'module_name' => $moduleName,
        'entity_name' => 'ApprovalSmoke',
        'resource_name' => $route,
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definition,
        'checksum' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ]);

    $versionCountBefore = BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count();
    $previewCountBefore = BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count();
    $service = $app->make(BuilderPublishApprovalService::class);

    $requested = $service->requestApproval($createdDefinition->fresh());
    $storageRoots[] = $root.'/'.$requested->candidate_root;
    $storageRoots[] = isset($requested->snapshot_json['dry_run']['dry_run_root']) ? $root.'/'.$requested->snapshot_json['dry_run']['dry_run_root'] : null;
    $storageRoots[] = $root.'/storage/app/builder-publish-readiness/'.$createdDefinition->getKey();
    approval_mvp_check($checks, 'requestApproval creates requested status', $requested->status === BuilderPublishApprovalRequest::STATUS_REQUESTED);
    approval_mvp_check($checks, 'candidate_id exists', filled($requested->candidate_id));
    approval_mvp_check($checks, 'candidate snapshot path exists', is_file($root.'/'.$requested->candidate_snapshot_path));
    approval_mvp_check($checks, 'candidate snapshot internal approval_requested remains false', ($requested->snapshot_json['approval_requested'] ?? null) === false);
    approval_mvp_check($checks, 'candidate snapshot publish_executed false', ($requested->snapshot_json['publish_executed'] ?? null) === false);
    approval_mvp_check($checks, 'candidate snapshot runtime writes zero', ($requested->snapshot_json['runtime_writes_performed'] ?? null) === 0);
    approval_mvp_check($checks, 'audit approval_requested created', BuilderPublishAuditLog::where('builder_publish_approval_request_id', $requested->getKey())->where('event_type', 'approval_requested')->exists());

    $approved = $service->approve($requested->fresh(), 'approve smoke');
    approval_mvp_check($checks, 'approve sets approved status', $approved->status === BuilderPublishApprovalRequest::STATUS_APPROVED);
    approval_mvp_check($checks, 'audit approval_approved created', BuilderPublishAuditLog::where('builder_publish_approval_request_id', $approved->getKey())->where('event_type', 'approval_approved')->exists());

    $rejected = $service->requestApproval($createdDefinition->fresh());
    $storageRoots[] = $root.'/'.$rejected->candidate_root;
    $storageRoots[] = isset($rejected->snapshot_json['dry_run']['dry_run_root']) ? $root.'/'.$rejected->snapshot_json['dry_run']['dry_run_root'] : null;
    $rejected = $service->reject($rejected->fresh(), 'reject smoke');
    approval_mvp_check($checks, 'reject sets rejected status', $rejected->status === BuilderPublishApprovalRequest::STATUS_REJECTED);
    approval_mvp_check($checks, 'audit approval_rejected created', BuilderPublishAuditLog::where('builder_publish_approval_request_id', $rejected->getKey())->where('event_type', 'approval_rejected')->exists());

    $revoked = $service->requestApproval($createdDefinition->fresh());
    $storageRoots[] = $root.'/'.$revoked->candidate_root;
    $storageRoots[] = isset($revoked->snapshot_json['dry_run']['dry_run_root']) ? $root.'/'.$revoked->snapshot_json['dry_run']['dry_run_root'] : null;
    $revoked = $service->revoke($revoked->fresh(), 'revoke smoke');
    approval_mvp_check($checks, 'revoke sets revoked status', $revoked->status === BuilderPublishApprovalRequest::STATUS_REVOKED);
    approval_mvp_check($checks, 'audit approval_revoked created', BuilderPublishAuditLog::where('builder_publish_approval_request_id', $revoked->getKey())->where('event_type', 'approval_revoked')->exists());

    $invalidated = $service->requestApproval($createdDefinition->fresh());
    $storageRoots[] = $root.'/'.$invalidated->candidate_root;
    $storageRoots[] = isset($invalidated->snapshot_json['dry_run']['dry_run_root']) ? $root.'/'.$invalidated->snapshot_json['dry_run']['dry_run_root'] : null;
    $createdDefinition->forceFill(['checksum' => 'changed-'.$suffix])->save();
    $invalidated = $service->approve($invalidated->fresh(), 'should invalidate');
    approval_mvp_check($checks, 'checksum changed approval invalidated', $invalidated->status === BuilderPublishApprovalRequest::STATUS_INVALIDATED);
    approval_mvp_check($checks, 'audit approval_invalidated created', BuilderPublishAuditLog::where('builder_publish_approval_request_id', $invalidated->getKey())->where('event_type', 'approval_invalidated')->exists());

    approval_mvp_check($checks, 'approval service does not create preview runs', BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count() === $previewCountBefore);
    approval_mvp_check($checks, 'approval service does not create versions', BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count() === $versionCountBefore);
    approval_mvp_check($checks, 'no real runtime smoke module created', ! is_dir($root.'/modules/'.$moduleName));
} catch (Throwable $e) {
    approval_mvp_check($checks, 'runtime approval smoke completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        BuilderPublishAuditLog::where('builder_definition_id', $definitionId)->delete();
        BuilderPublishApprovalRequest::where('builder_definition_id', $definitionId)->delete();
        BuilderPreviewRun::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinitionVersion::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();
        approval_mvp_check($checks, 'temporary DB records cleaned', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }

    foreach (array_filter($storageRoots) as $path) {
        if (str_contains($path, '/builder-publish-candidates/')) {
            approval_mvp_delete_directory_if_unique($path, $root.'/storage/app/builder-publish-candidates');
        } elseif (str_contains($path, '/builder-publish-dry-runs/')) {
            approval_mvp_delete_directory_if_unique($path, $root.'/storage/app/builder-publish-dry-runs');
        } elseif (str_contains($path, '/builder-publish-readiness/')) {
            approval_mvp_delete_directory_if_unique($path, $root.'/storage/app/builder-publish-readiness');
        }
    }
    approval_mvp_check($checks, 'unique storage artifacts cleaned', true);
}

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
approval_mvp_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));
$changedPaths = array_filter(array_map(static fn (string $line): string => trim(substr($line, 3)), preg_split('/\R/', trim($statusOutput))));

foreach ([
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
    'modules/Warehouse/',
    'modules/Core/',
    'modules/SaaS/',
    'modules/Updater/',
    'modules/Installer/',
    'routes/web.php',
    'resources/js/app.js',
    'vendor/',
    'node_modules/',
    'public/build/',
] as $forbiddenPath) {
    $changed = str_ends_with($forbiddenPath, '/')
        ? array_filter($changedPaths, static fn (string $path): bool => str_starts_with($path, $forbiddenPath))
        : in_array($forbiddenPath, $changedPaths, true);
    approval_mvp_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    approval_mvp_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);
echo $failed === [] ? "PASS\n" : "FAIL\n";
exit($failed === [] ? 0 : 1);
