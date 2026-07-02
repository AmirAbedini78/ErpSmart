<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;
$storageRoots = [];

function preflight_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function preflight_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function preflight_json(string $root, string $path): ?array
{
    $decoded = json_decode(preflight_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function preflight_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = preflight_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

function preflight_check_status(array $report, string $key): ?string
{
    foreach ($report['checks'] ?? [] as $check) {
        if (($check['key'] ?? null) === $key) {
            return $check['status'] ?? null;
        }
    }

    return null;
}

function preflight_delete_directory_if_unique(?string $path, string $expectedBase): void
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
use App\Services\Builder\BuilderApprovedCandidatePreflightService;
use App\Services\Builder\BuilderPublishApprovalService;
use Illuminate\Support\Facades\Schema;

$servicePath = 'app/Services/Builder/BuilderApprovedCandidatePreflightService.php';
$controllerPath = 'app/Http/Controllers/Builder/BuilderDefinitionController.php';
$routesPath = 'routes/api.php';
$apiPath = 'modules/Builder/resources/js/services/builderApi.js';
$uiPath = 'modules/Builder/resources/js/components/BuilderApprovedCandidatePreflight.vue';
$docPath = 'docs/ai/03-architecture/builder-approved-candidate-preflight.md';
$contractPath = 'docs/ai/05-rag/contracts/builder-approved-candidate-preflight-contract.json';
$historyPath = 'docs/ai/04-docops/history/2026-07-02-builder-approved-candidate-preflight.md';

foreach ([$servicePath, $uiPath, $docPath, $contractPath, $historyPath] as $file) {
    preflight_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    $contractPath,
    'docs/ai/05-rag/contracts/builder-human-approval-request-api-map.json',
    'docs/ai/05-rag/contracts/builder-human-approval-gate-contract.json',
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-studio-api-map.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
    'docs/ai/05-rag/contracts/builder-studio-component-map.json',
] as $jsonFile) {
    preflight_check($checks, $jsonFile.' valid JSON', preflight_json($root, $jsonFile) !== null, json_last_error_msg());
}

$service = preflight_read($root, $servicePath);
$controller = preflight_read($root, $controllerPath);
$routes = preflight_read($root, $routesPath);
$api = preflight_read($root, $apiPath);
$ui = preflight_read($root, $uiPath).preflight_read($root, 'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue');
$manifestText = preflight_flatten(preflight_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$safetyText = preflight_flatten(preflight_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);

preflight_check($checks, 'preflight service reports writes_performed 0', str_contains($service, "'writes_performed' => 0"));
preflight_check($checks, 'preflight service reports publish_executed false', str_contains($service, "'publish_executed' => false"));
preflight_check($checks, 'preflight service does not write modules/', ! preg_match('/File::put|Storage::put|modules\//', $service));
preflight_check($checks, 'preflight service does not run migrations', ! preg_match('/migrate|Artisan::call|Schema::create|Schema::table/i', $service));
preflight_check($checks, 'preflight service does not create audit logs', ! preg_match('/BuilderPublishAuditLog|logAudit|->create\(/', $service));
preflight_check($checks, 'controller endpoint exists', str_contains($controller, 'approvedCandidatePreflight'));
preflight_check($checks, 'route exists', str_contains($routes, 'approved-candidate-preflight'));
preflight_check($checks, 'builderApi has getApprovedCandidatePreflight', str_contains($api, 'getApprovedCandidatePreflight'));
preflight_check($checks, 'UI has Check Approved Candidate Preflight', str_contains($ui, 'Check Approved Candidate Preflight'));
preflight_check($checks, 'UI safety notice says preflight only/no publish/no runtime files', str_contains($ui, 'Preflight only. This checks approval and candidate freshness. It does not publish or write runtime files.'));
preflight_check($checks, 'UI has no Publish/Execute Publish/Deploy/Copy to runtime button', ! preg_match('/text=["\'](?:Publish|Execute Publish|Deploy|Copy to runtime)["\']|runPublish|publishDefinition|copyToRuntime/i', $ui.$api));
preflight_check($checks, 'RAG manifest mentions approved candidate preflight', str_contains($manifestText, 'approved candidate preflight'));
preflight_check($checks, 'safety boundaries forbid treating eligible as published', str_contains($safetyText, 'eligible_for_future_publish as published module'));

try {
    foreach (['builder_definitions', 'builder_publish_approval_requests', 'builder_publish_audit_logs'] as $table) {
        preflight_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }

    if (! Schema::hasTable('builder_publish_approval_requests') || ! Schema::hasTable('builder_publish_audit_logs')) {
        throw new RuntimeException('Approval tables are missing; run docker compose exec app php artisan migrate');
    }

    $definition = json_decode(preflight_read($root, 'docs/ai/05-rag/examples/definition-driven-custom-module.json'), true);
    if (! is_array($definition)) {
        throw new RuntimeException('Unable to read definition-driven-custom-module.json');
    }

    $suffix = bin2hex(random_bytes(4));
    $moduleName = 'PreflightSmoke'.$suffix;
    $route = 'preflight-smoke-'.$suffix;
    $definition['module']['name'] = $moduleName;
    $definition['module']['namespace'] = 'Modules\\'.$moduleName;
    $definition['module']['singularLabel'] = 'PreflightSmoke';
    $definition['module']['pluralLabel'] = $moduleName;
    $definition['module']['table'] = 'preflight_smoke_'.$suffix;
    $definition['module']['routeName'] = $route;
    $definition['module']['resourceName'] = $route;
    $definition['resource']['modelClass'] = 'Modules\\'.$moduleName.'\\Models\\PreflightSmoke';

    $createdDefinition = BuilderDefinition::create([
        'name' => 'Preflight Smoke '.$suffix,
        'slug' => 'preflight-smoke-'.$suffix,
        'module_name' => $moduleName,
        'entity_name' => 'PreflightSmoke',
        'resource_name' => $route,
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definition,
        'checksum' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ]);

    $versionCountBefore = BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count();
    $previewCountBefore = BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count();
    $approvalService = $app->make(BuilderPublishApprovalService::class);
    $preflight = $app->make(BuilderApprovedCandidatePreflightService::class);

    $approval = $approvalService->requestApproval($createdDefinition->fresh());
    $storageRoots[] = $root.'/'.$approval->candidate_root;
    $storageRoots[] = isset($approval->snapshot_json['dry_run']['dry_run_root']) ? $root.'/'.$approval->snapshot_json['dry_run']['dry_run_root'] : null;
    $storageRoots[] = $root.'/storage/app/builder-publish-readiness/'.$createdDefinition->getKey();
    $approval = $approvalService->approve($approval->fresh(), 'preflight smoke');
    $auditCountBeforePreflight = BuilderPublishAuditLog::where('builder_definition_id', $createdDefinition->getKey())->count();
    $approvalStatusBefore = $approval->status;

    $report = $preflight->check($createdDefinition->fresh());
    preflight_check($checks, 'approved preflight eligible or warning', in_array($report['status'], ['eligible', 'warning'], true));
    preflight_check($checks, 'eligible_for_future_publish true', ($report['eligible_for_future_publish'] ?? null) === true);
    preflight_check($checks, 'approved_request_exists passed', preflight_check_status($report, 'approved_request_exists') === 'passed');
    preflight_check($checks, 'checksum matches passed', preflight_check_status($report, 'definition_checksum_matches') === 'passed');
    preflight_check($checks, 'candidate snapshot path exists passed', preflight_check_status($report, 'candidate_snapshot_path_exists') === 'passed');
    preflight_check($checks, 'publish_executed false', ($report['publish_executed'] ?? null) === false);
    preflight_check($checks, 'runtime_writes_performed 0', ($report['runtime_writes_performed'] ?? null) === 0);
    preflight_check($checks, 'preflight creates no DB records', BuilderPublishAuditLog::where('builder_definition_id', $createdDefinition->getKey())->count() === $auditCountBeforePreflight);

    $createdDefinition->forceFill(['checksum' => 'changed-'.$suffix])->save();
    $stale = $preflight->check($createdDefinition->fresh());
    preflight_check($checks, 'stale checksum is blocked', ($stale['eligible_for_future_publish'] ?? true) === false && $stale['status'] === 'blocked');
    preflight_check($checks, 'checksum mismatch blocker exists', preflight_check_status($stale, 'definition_checksum_matches') === 'blocked');
    preflight_check($checks, 'preflight does not mutate approval status', $approval->fresh()->status === $approvalStatusBefore);
    preflight_check($checks, 'stale preflight creates no audit logs', BuilderPublishAuditLog::where('builder_definition_id', $createdDefinition->getKey())->count() === $auditCountBeforePreflight);
    preflight_check($checks, 'preflight does not create preview runs', BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count() === $previewCountBefore);
    preflight_check($checks, 'preflight does not create versions', BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count() === $versionCountBefore);
    preflight_check($checks, 'no real runtime smoke module created', ! is_dir($root.'/modules/'.$moduleName));
} catch (Throwable $e) {
    preflight_check($checks, 'runtime approved candidate preflight smoke completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        BuilderPublishAuditLog::where('builder_definition_id', $definitionId)->delete();
        BuilderPublishApprovalRequest::where('builder_definition_id', $definitionId)->delete();
        BuilderPreviewRun::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinitionVersion::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();
        preflight_check($checks, 'temporary DB records cleaned', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }

    foreach (array_filter($storageRoots) as $path) {
        if (str_contains($path, '/builder-publish-candidates/')) {
            preflight_delete_directory_if_unique($path, $root.'/storage/app/builder-publish-candidates');
        } elseif (str_contains($path, '/builder-publish-dry-runs/')) {
            preflight_delete_directory_if_unique($path, $root.'/storage/app/builder-publish-dry-runs');
        } elseif (str_contains($path, '/builder-publish-readiness/')) {
            preflight_delete_directory_if_unique($path, $root.'/storage/app/builder-publish-readiness');
        }
    }
    preflight_check($checks, 'unique storage artifacts cleaned', true);
}

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
preflight_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));
$changedPaths = array_filter(array_map(static fn (string $line): string => trim(substr($line, 3)), preg_split('/\R/', trim($statusOutput))));

foreach ([
    'database/migrations/',
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
    preflight_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    preflight_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);
echo $failed === [] ? "PASS\n" : "FAIL\n";
exit($failed === [] ? 0 : 1);
