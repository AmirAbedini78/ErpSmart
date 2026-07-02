<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

function fail_if(bool $condition, string $message): void
{
    global $errors;

    if ($condition) {
        $errors[] = $message;
    }
}

function path_join(string $root, string $path): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function read_file(string $path): string
{
    return file_exists($path) ? (string) file_get_contents($path) : '';
}

function json_file(string $root, string $path): array
{
    global $errors;

    $fullPath = path_join($root, $path);

    if (! file_exists($fullPath)) {
        $errors[] = "Missing JSON contract: {$path}";

        return [];
    }

    $decoded = json_decode((string) file_get_contents($fullPath), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Invalid JSON in {$path}: ".json_last_error_msg();

        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function contains_all(string $haystack, array $needles, string $label): void
{
    global $errors;

    foreach ($needles as $needle) {
        if (! str_contains($haystack, $needle)) {
            $errors[] = "{$label} is missing required text: {$needle}";
        }
    }
}

function bool_value(array $data, string $key): ?bool
{
    return array_key_exists($key, $data) && is_bool($data[$key]) ? $data[$key] : null;
}

$architectureDocs = [
    'docs/ai/03-architecture/builder-publish-execution-architecture.md',
    'docs/ai/03-architecture/builder-publish-rollback-manifest-strategy.md',
    'docs/ai/03-architecture/builder-publish-locking-and-staging-strategy.md',
];

foreach ($architectureDocs as $doc) {
    fail_if(! file_exists(path_join($root, $doc)), "Missing architecture doc: {$doc}");
}

$execution = json_file($root, 'docs/ai/05-rag/contracts/builder-publish-execution-contract.json');
$rollback = json_file($root, 'docs/ai/05-rag/contracts/builder-publish-rollback-manifest-contract.json');
$locking = json_file($root, 'docs/ai/05-rag/contracts/builder-publish-locking-contract.json');
$safety = json_file($root, 'docs/ai/05-rag/contracts/builder-publish-safety-contract.json');
$audit = json_file($root, 'docs/ai/05-rag/contracts/builder-publish-audit-log-contract.json');
$boundaries = json_file($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json');
$manifest = json_file($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json');
$preflight = json_file($root, 'docs/ai/05-rag/contracts/builder-approved-candidate-preflight-contract.json');

fail_if(($execution['current_implementation_status'] ?? null) !== 'planning_only', 'Publish execution contract must be planning_only.');
fail_if(bool_value($execution, 'explicit_human_command_required') !== true, 'Publish execution contract must require explicit human command.');
fail_if(bool_value($execution, 'final_confirmation_required') !== true, 'Publish execution contract must require final confirmation.');
fail_if(bool_value($execution, 'approved_candidate_preflight_required') !== true, 'Publish execution contract must require approved candidate preflight.');
fail_if(bool_value($execution, 'rollback_manifest_required') !== true, 'Publish execution contract must require rollback manifest.');
fail_if(bool_value($execution, 'staging_required') !== true, 'Publish execution contract must require staging.');
fail_if(bool_value($execution, 'agent_may_execute_publish') !== false, 'Publish execution contract must forbid agent publish execution.');
fail_if(bool_value($execution, 'actual_publish_implemented') !== false, 'Publish execution contract must mark actual publish unimplemented.');
fail_if(bool_value($execution, 'runtime_writes_currently_allowed') !== false, 'Publish execution contract must forbid current runtime writes.');

fail_if(bool_value($rollback, 'required_before_runtime_writes') !== true, 'Rollback contract must require manifest before runtime writes.');

$lockScopes = $locking['lock_scopes'] ?? [];
foreach (['definition_publish_lock', 'runtime_path_lock', 'migration_lock'] as $scope) {
    fail_if(! in_array($scope, $lockScopes, true), "Locking contract missing lock scope: {$scope}");
}

$safetyText = json_encode($safety, JSON_PRETTY_PRINT);
contains_all($safetyText ?: '', [
    'publish execution architecture planning',
    'rollback manifest planning',
    'locking and staging planning',
    'actual_publish_still_forbidden',
    'approved candidate preflight',
], 'Publish safety contract');

$auditEvents = array_merge($audit['future_events'] ?? [], $audit['implemented_events'] ?? []);
foreach ([
    'publish_lock_acquired',
    'publish_lock_failed',
    'publish_lock_released',
    'rollback_manifest_created',
    'publish_staging_created',
    'publish_staging_validated',
    'publish_runtime_write_started',
    'publish_runtime_write_failed',
    'publish_runtime_write_succeeded',
    'post_publish_smoke_started',
    'post_publish_smoke_failed',
    'post_publish_smoke_succeeded',
] as $event) {
    fail_if(! in_array($event, $auditEvents, true), "Audit contract missing future event: {$event}");
}

$boundariesText = json_encode($boundaries, JSON_PRETTY_PRINT);
contains_all($boundariesText ?: '', [
    'summarize publish execution plan',
    'execute publish',
    'acquire publish lock',
    'write runtime files',
    'execute rollback',
    'ai_builder_agent_may_execute_publish',
    'ai_builder_agent_may_acquire_publish_lock',
    'ai_builder_agent_may_write_runtime_files',
    'ai_builder_agent_may_execute_rollback',
], 'Agent safety boundaries');
fail_if(($boundaries['lifecycle_planning_boundaries']['ai_builder_agent_may_execute_publish'] ?? null) !== false, 'Agent may execute publish must be false.');
fail_if(($boundaries['lifecycle_planning_boundaries']['ai_builder_agent_may_acquire_publish_lock'] ?? null) !== false, 'Agent may acquire publish lock must be false.');
fail_if(($boundaries['lifecycle_planning_boundaries']['ai_builder_agent_may_write_runtime_files'] ?? null) !== false, 'Agent may write runtime files must be false.');
fail_if(($boundaries['lifecycle_planning_boundaries']['ai_builder_agent_may_execute_rollback'] ?? null) !== false, 'Agent may execute rollback must be false.');

$manifestText = json_encode($manifest, JSON_PRETTY_PRINT);
contains_all($manifestText ?: '', [
    'builder-publish-execution-architecture.md',
    'builder-publish-rollback-manifest-strategy.md',
    'builder-publish-locking-and-staging-strategy.md',
    'builder-publish-execution-contract.json',
    'Publish execution architecture planning is documentation and contracts only',
], 'Builder Studio AI/RAG manifest');

$preflightText = json_encode($preflight, JSON_PRETTY_PRINT);
contains_all($preflightText ?: '', [
    'required_input_to_future_publish_execution',
    'preflight_does_not_publish',
    'actual_publish_still_forbidden',
], 'Approved candidate preflight contract');

$routes = read_file(path_join($root, 'routes/api.php'));
foreach (['publish-executions', 'execute-publish', 'rollback-executions', 'execute-rollback'] as $forbiddenRoute) {
    fail_if(str_contains($routes, $forbiddenRoute), "Forbidden executable route found: {$forbiddenRoute}");
}

$builderUiFiles = glob(path_join($root, 'modules/Builder/resources/js/**/*.vue')) ?: [];
$builderUiFiles = array_merge(
    $builderUiFiles,
    glob(path_join($root, 'modules/Builder/resources/js/**/*.js')) ?: []
);
$builderUiText = '';
foreach ($builderUiFiles as $file) {
    $builderUiText .= "\n".read_file($file);
}

foreach ([
    'Execute Publish',
    'Deploy',
    'Copy to runtime',
    'Copy artifacts into runtime',
    'Run Publish',
    'Start Publish',
] as $forbiddenUiText) {
    fail_if(str_contains($builderUiText, $forbiddenUiText), "Forbidden executable UI text found: {$forbiddenUiText}");
}

foreach ([
    'publishDefinition',
    'executePublish',
    'createPublishExecution',
    'rollbackDefinition',
    'executeRollback',
] as $forbiddenSymbol) {
    fail_if(str_contains($builderUiText, $forbiddenSymbol), "Forbidden executable UI symbol found: {$forbiddenSymbol}");
}

foreach ([
    'app/Services/Builder/BuilderPublishExecutionService.php',
    'app/Services/Builder/BuilderPublishExecutor.php',
    'app/Services/Builder/BuilderRollbackService.php',
    'app/Http/Controllers/Builder/BuilderPublishExecutionController.php',
    'app/Http/Controllers/Builder/BuilderRollbackController.php',
] as $forbiddenFile) {
    fail_if(file_exists(path_join($root, $forbiddenFile)), "Forbidden implementation file exists: {$forbiddenFile}");
}

foreach (glob(path_join($root, 'database/migrations/*publish_execution*.php')) ?: [] as $file) {
    $errors[] = 'Forbidden publish execution migration exists: '.str_replace($root.DIRECTORY_SEPARATOR, '', $file);
}

foreach (glob(path_join($root, 'database/migrations/*rollback*.php')) ?: [] as $file) {
    $errors[] = 'Forbidden rollback migration exists: '.str_replace($root.DIRECTORY_SEPARATOR, '', $file);
}

$forbiddenStatusPaths = [
    'modules/Warehouse',
    'modules/Core',
    'modules/SaaS',
    'modules/Updater',
    'modules/Installer',
    'package.json',
    'composer.json',
    'public/build',
];

$statusOutput = [];
exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short -- '.implode(' ', array_map('escapeshellarg', $forbiddenStatusPaths)), $statusOutput);
fail_if($statusOutput !== [], 'Forbidden paths have git changes: '.implode('; ', $statusOutput));

if ($errors !== []) {
    echo "FAIL\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS\n";
echo "Publish execution architecture planning verified. No publish/rollback execution endpoint, UI action, runtime write implementation, or forbidden path change was detected.\n";
