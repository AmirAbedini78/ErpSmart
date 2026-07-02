<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function approval_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function approval_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function approval_json(string $root, string $path): ?array
{
    $decoded = json_decode(approval_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function approval_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = approval_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

$approvalDoc = 'docs/ai/03-architecture/builder-human-approval-gate.md';
$auditDoc = 'docs/ai/03-architecture/builder-publish-audit-log-strategy.md';
$approvalContract = 'docs/ai/05-rag/contracts/builder-human-approval-gate-contract.json';
$auditContract = 'docs/ai/05-rag/contracts/builder-publish-audit-log-contract.json';
$history = 'docs/ai/04-docops/history/2026-07-02-builder-human-approval-gate-planning.md';

foreach ([$approvalDoc, $auditDoc, $approvalContract, $auditContract, $history] as $file) {
    approval_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    $approvalContract,
    $auditContract,
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-publish-candidate-snapshot-contract.json',
] as $jsonFile) {
    approval_check($checks, $jsonFile.' valid JSON', approval_json($root, $jsonFile) !== null, json_last_error_msg());
}

$approval = approval_json($root, $approvalContract) ?? [];
$audit = approval_json($root, $auditContract) ?? [];
$safety = approval_flatten(approval_json($root, 'docs/ai/05-rag/contracts/builder-publish-safety-contract.json') ?? []);
$boundaries = approval_flatten(approval_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);
$manifest = approval_flatten(approval_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$candidate = approval_flatten(approval_json($root, 'docs/ai/05-rag/contracts/builder-publish-candidate-snapshot-contract.json') ?? []);

$futureStatuses = $approval['future_statuses'] ?? [];
foreach (['requested', 'approved', 'rejected', 'revoked', 'expired', 'invalidated'] as $status) {
    approval_check($checks, 'approval contract has future status '.$status, in_array($status, $futureStatuses, true));
}

approval_check($checks, 'approval contract says approval_does_not_publish true', ($approval['approval_does_not_publish'] ?? null) === true);
approval_check($checks, 'approval contract says agent_may_approve_publish false', ($approval['agent_may_approve_publish'] ?? null) === false);
approval_check($checks, 'approval contract says agent_may_execute_publish false', ($approval['agent_may_execute_publish'] ?? null) === false);
approval_check($checks, 'approval contract says planning_only', ($approval['current_implementation_status'] ?? null) === 'planning_only');
approval_check($checks, 'audit contract has append_only true', ($audit['append_only'] ?? null) === true);

$events = $audit['future_events'] ?? [];
foreach ([
    'candidate_snapshot_created',
    'approval_requested',
    'approval_approved',
    'approval_rejected',
    'approval_revoked',
    'approval_invalidated',
    'publish_preflight_started',
    'publish_preflight_failed',
    'publish_started',
    'publish_failed',
    'publish_succeeded',
    'rollback_started',
    'rollback_failed',
    'rollback_succeeded',
    'lifecycle_archived',
    'lifecycle_restored',
    'lifecycle_deleted_draft',
] as $event) {
    approval_check($checks, 'audit contract contains event '.$event, in_array($event, $events, true));
}

approval_check($checks, 'publish safety mentions approval gate planning', str_contains($safety, 'human approval gate planning'));
approval_check($checks, 'publish safety says actual publish forbidden', str_contains($safety, 'actual_publish_still_forbidden true'));
approval_check($checks, 'safety boundaries forbid approve/reject/revoke', str_contains($boundaries, 'approve publish') && str_contains($boundaries, 'reject publish approval') && str_contains($boundaries, 'revoke publish approval'));
approval_check($checks, 'safety boundaries forbid request approval until implemented', str_contains($boundaries, 'request publish approval before approval implementation exists'));
approval_check($checks, 'RAG manifest mentions approval gate and audit log', str_contains($manifest, 'builder-human-approval-gate') && str_contains($manifest, 'builder-publish-audit-log'));
approval_check($checks, 'candidate snapshot contract says approval not implemented', str_contains($candidate, 'not_implemented') && str_contains($candidate, 'approval_requested remains false') && str_contains($candidate, 'approval_granted remains false'));

$uiText = '';
foreach (glob($root.'/modules/Builder/resources/js/**/*.vue') ?: [] as $file) {
    $uiText .= file_get_contents($file) ?: '';
}
approval_check($checks, 'no approval buttons exist', ! preg_match('/text=["\'](?:Approve Publish|Reject Publish|Revoke Approval|Request Approval)["\']|approvePublish|rejectPublish|revokeApproval|requestApproval/i', $uiText));
approval_check($checks, 'no publish button/action exists', ! preg_match('/text=["\']Publish["\']|runPublish|publishDefinition|@publish(?!-readiness)/i', $uiText));

$routes = approval_read($root, 'routes/api.php');
approval_check($checks, 'no approve/reject/revoke routes exist', ! preg_match('/approval-requests|\/approve|\/reject|\/revoke|approvePublish|rejectPublish|revoke/i', $routes));

$serviceText = '';
foreach (glob($root.'/app/Services/Builder/*.php') ?: [] as $file) {
    $serviceText .= file_get_contents($file) ?: '';
}
$controllerText = '';
foreach (glob($root.'/app/Http/Controllers/Builder/*.php') ?: [] as $file) {
    $controllerText .= file_get_contents($file) ?: '';
}
$modelText = '';
foreach (glob($root.'/app/Models/*.php') ?: [] as $file) {
    $modelText .= file_get_contents($file) ?: '';
}
$migrationNames = implode(' ', array_map('basename', glob($root.'/database/migrations/*approval*') ?: []));

approval_check($checks, 'no approval persistence service implemented', ! preg_match('/ApprovalRequest|approvalRequests|approvePublish|rejectPublish|revokeApproval|requestApproval/i', $serviceText));
approval_check($checks, 'no approval controller methods implemented', ! preg_match('/function\s+(approve|reject|revoke|requestApproval|approvePublish|rejectPublish|revokeApproval)\s*\(/i', $controllerText));
approval_check($checks, 'no approval models implemented', ! preg_match('/class\s+.*Approval|ApprovalRequest/i', $modelText));
approval_check($checks, 'no approval migrations exist', $migrationNames === '');

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
approval_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));
$changedPaths = array_filter(array_map(static fn (string $line): string => trim(substr($line, 3)), preg_split('/\R/', trim($statusOutput))));

foreach ([
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
    approval_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    approval_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);
echo $failed === [] ? "PASS\n" : "FAIL\n";
exit($failed === [] ? 0 : 1);
