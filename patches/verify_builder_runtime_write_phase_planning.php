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
        $errors[] = "Invalid JSON in {$path}: ".json_last_error_msg();

        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function contains_text(string $haystack, string $needle, string $label): void
{
    fail_if(! str_contains($haystack, $needle), "{$label} missing {$needle}");
}

foreach ([
    'docs/ai/03-architecture/builder-runtime-write-phase-plan.md',
    'docs/ai/03-architecture/builder-runtime-path-allowlist-strategy.md',
    'docs/ai/03-architecture/builder-runtime-write-backup-strategy.md',
    'docs/ai/04-docops/history/2026-07-02-builder-runtime-write-phase-planning.md',
] as $doc) {
    fail_if(! is_file(project_path($doc)), "Missing document: {$doc}");
}

$runtimeWrite = json_contract('docs/ai/05-rag/contracts/builder-runtime-write-phase-contract.json');
$allowlist = json_contract('docs/ai/05-rag/contracts/builder-runtime-path-allowlist-contract.json');
$backup = json_contract('docs/ai/05-rag/contracts/builder-runtime-write-backup-contract.json');
$safety = json_contract('docs/ai/05-rag/contracts/builder-publish-safety-contract.json');
$audit = json_contract('docs/ai/05-rag/contracts/builder-publish-audit-log-contract.json');
$manifest = json_contract('docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json');
$boundaries = json_contract('docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json');
$toolRegistry = json_contract('docs/ai/05-rag/contracts/ai-tool-registry-contract.json');

fail_if(($runtimeWrite['current_implementation_status'] ?? null) !== 'planning_only', 'Runtime write contract must be planning_only.');
fail_if(($runtimeWrite['runtime_write_endpoint_implemented'] ?? null) !== false, 'Runtime write endpoint must not be implemented.');
fail_if(($runtimeWrite['runtime_write_ui_action_implemented'] ?? null) !== false, 'Runtime write UI action must not be implemented.');
fail_if(($runtimeWrite['runtime_writes_currently_allowed'] ?? null) !== false, 'Runtime writes must not be allowed.');
fail_if(($runtimeWrite['requires_execution_status'] ?? null) !== 'staging_validated', 'Runtime write must require staging_validated.');
fail_if(($runtimeWrite['requires_human_final_confirmation'] ?? null) !== true, 'Runtime write must require human final confirmation.');
fail_if(($runtimeWrite['requires_runtime_path_lock'] ?? null) !== true, 'Runtime write must require runtime path lock.');
fail_if(($runtimeWrite['requires_backup_before_overwrite'] ?? null) !== true, 'Runtime write must require backup before overwrite.');
fail_if(($runtimeWrite['migrations_run_in_this_phase'] ?? null) !== false, 'Migrations must not run in runtime write phase.');
fail_if(($runtimeWrite['route_registration_in_this_phase'] ?? null) !== false, 'Route registration must not happen in runtime write phase.');
fail_if(($runtimeWrite['publish_status_set_in_this_phase'] ?? null) !== false, 'Published status must not be set in runtime write phase.');
fail_if(($runtimeWrite['agent_may_execute_runtime_write'] ?? null) !== false, 'Agent must not execute runtime write.');
fail_if(($runtimeWrite['mcp_may_execute_runtime_write'] ?? null) !== false, 'MCP must not execute runtime write.');

fail_if(($allowlist['path_traversal_forbidden'] ?? null) !== true, 'Allowlist contract must forbid traversal.');
fail_if(($allowlist['absolute_external_paths_forbidden'] ?? null) !== true, 'Allowlist contract must forbid external absolute paths.');
fail_if(($allowlist['requires_module_slug_validation'] ?? null) !== true, 'Allowlist contract must require module slug validation.');
fail_if(($allowlist['agent_may_override_allowlist'] ?? null) !== false, 'Agent must not override allowlist.');

fail_if(($backup['backup_required_before_overwrite'] ?? null) !== true, 'Backup contract must require backup before overwrite.');
fail_if(($backup['backup_manifest_required'] ?? null) !== true, 'Backup contract must require backup manifest.');
fail_if(($backup['rollback_manifest_must_reference_backups'] ?? null) !== true, 'Rollback manifest must reference backups.');
fail_if(($backup['agent_may_skip_backup'] ?? null) !== false, 'Agent must not skip backup.');

$safetyText = json_encode($safety, JSON_PRETTY_PRINT) ?: '';
contains_text($safetyText, 'runtime write phase planning', 'Publish safety contract');
contains_text($safetyText, 'runtime_writes_currently_allowed', 'Publish safety contract');
contains_text($safetyText, 'actual_publish_still_forbidden', 'Publish safety contract');

$auditEvents = array_merge($audit['implemented_events'] ?? [], $audit['future_events'] ?? []);
foreach ([
    'runtime_write_plan_created',
    'runtime_write_backup_created',
    'runtime_write_started',
    'runtime_write_succeeded',
    'runtime_write_failed',
    'runtime_write_skipped',
] as $event) {
    fail_if(! in_array($event, $auditEvents, true), "Audit contract missing future event: {$event}");
}

$manifestText = json_encode($manifest, JSON_PRETTY_PRINT) ?: '';
contains_text($manifestText, 'builder-runtime-write-phase-plan.md', 'RAG manifest');
contains_text($manifestText, 'builder-runtime-path-allowlist-strategy.md', 'RAG manifest');
contains_text($manifestText, 'builder-runtime-write-backup-strategy.md', 'RAG manifest');
contains_text($manifestText, 'Runtime write phase planning is documentation and contracts only', 'RAG manifest');

$boundariesText = json_encode($boundaries, JSON_PRETTY_PRINT) ?: '';
foreach ([
    'execute runtime write',
    'override runtime path allowlist',
    'skip runtime write backup',
    'use MCP to execute runtime write',
] as $text) {
    contains_text($boundariesText, $text, 'Safety boundaries');
}

$toolText = json_encode($toolRegistry, JSON_PRETTY_PRINT) ?: '';
contains_text($toolText, 'runtime_write_tool_implemented', 'Tool Registry contract');
fail_if(($toolRegistry['runtime_write_tool_implemented'] ?? null) !== false, 'Runtime write tool must not be implemented.');
fail_if(str_contains($toolText, 'builder.execute_runtime_write'), 'Tool Registry must not expose builder.execute_runtime_write.');
fail_if(str_contains($toolText, 'builder.copy_to_runtime'), 'Tool Registry must not expose builder.copy_to_runtime.');

$routes = read_project_file('routes/api.php');
foreach (['runtime-write', 'copy-to-runtime', 'execute-publish', 'execute-runtime-write'] as $forbidden) {
    fail_if(str_contains($routes, $forbidden), "Forbidden route token exists: {$forbidden}");
}
fail_if((bool) preg_match("#definitions/\\{builderDefinition\\}/publish['\"]#", $routes), 'Forbidden publish endpoint exists.');

$builderUi = '';
foreach (glob(project_path('modules/Builder/resources/js/**/*.vue')) ?: [] as $file) {
    $builderUi .= "\n".((string) file_get_contents($file));
}
foreach (glob(project_path('modules/Builder/resources/js/**/*.js')) ?: [] as $file) {
    $builderUi .= "\n".((string) file_get_contents($file));
}
foreach ([
    'Execute Runtime Write',
    'Runtime Write',
    'Copy to runtime',
    'Copy To Runtime',
    'Execute Publish',
    'text="Publish"',
    'Run migrations',
] as $forbidden) {
    fail_if(str_contains($builderUi, $forbidden), "Forbidden Builder UI action/token exists: {$forbidden}");
}

foreach ([
    'app/Services/Builder/BuilderRuntimeWriteService.php',
    'app/Services/Builder/BuilderPublishRuntimeWriteService.php',
    'app/Http/Controllers/Builder/BuilderRuntimeWriteController.php',
    'app/Http/Controllers/Builder/BuilderPublishRuntimeWriteController.php',
    'app/Models/BuilderRuntimeWrite.php',
] as $file) {
    fail_if(file_exists(project_path($file)), "Forbidden runtime write implementation file exists: {$file}");
}

foreach (glob(project_path('database/migrations/*runtime*write*.php')) ?: [] as $file) {
    $errors[] = 'Forbidden runtime write migration exists: '.str_replace($root.DIRECTORY_SEPARATOR, '', $file);
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

if ($errors !== []) {
    echo "FAIL\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS\n";
echo "Runtime write phase planning verified. No runtime write, copy-to-runtime, publish, migration execution, or rollback implementation was detected.\n";
