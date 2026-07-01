<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function lifecycle_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function lifecycle_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function lifecycle_json(string $root, string $path): ?array
{
    $decoded = json_decode(lifecycle_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function lifecycle_status(string $root): string
{
    return shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
}

function lifecycle_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];

        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = lifecycle_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

function lifecycle_has_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (! str_contains($haystack, $needle)) {
            return false;
        }
    }

    return true;
}

$architectureDocs = [
    'docs/ai/03-architecture/builder-safe-publish-and-lifecycle.md',
    'docs/ai/03-architecture/builder-module-removal-strategy.md',
    'docs/ai/03-architecture/builder-capability-removal-strategy.md',
];

$jsonContracts = [
    'docs/ai/05-rag/contracts/builder-lifecycle-state-machine.json',
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-module-removal-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-capability-removal-contract.json',
    'docs/ai/05-rag/contracts/builder-module-dependency-impact-map.json',
];

$history = 'docs/ai/04-docops/history/2026-07-02-builder-safe-publish-and-lifecycle-planning.md';
$manifestPath = 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json';
$safetyPath = 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json';
$demoPath = 'docs/ai/05-rag/contracts/builder-studio-demo-journey-contract.json';

foreach ([...$architectureDocs, $history] as $file) {
    lifecycle_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([...$jsonContracts, $manifestPath, $safetyPath, $demoPath] as $file) {
    lifecycle_check($checks, $file.' valid JSON', lifecycle_json($root, $file) !== null, json_last_error_msg());
}

$stateMachine = lifecycle_json($root, 'docs/ai/05-rag/contracts/builder-lifecycle-state-machine.json') ?? [];
$publish = lifecycle_json($root, 'docs/ai/05-rag/contracts/builder-publish-safety-contract.json') ?? [];
$removal = lifecycle_json($root, 'docs/ai/05-rag/contracts/builder-module-removal-safety-contract.json') ?? [];
$capability = lifecycle_json($root, 'docs/ai/05-rag/contracts/builder-capability-removal-contract.json') ?? [];
$impact = lifecycle_json($root, 'docs/ai/05-rag/contracts/builder-module-dependency-impact-map.json') ?? [];
$manifest = lifecycle_json($root, $manifestPath) ?? [];
$safety = lifecycle_json($root, $safetyPath) ?? [];
$demo = lifecycle_json($root, $demoPath) ?? [];

$stateText = lifecycle_flatten($stateMachine);
foreach (['draft', 'validated', 'previewed', 'published', 'disabled', 'uninstalled', 'rolled_back'] as $state) {
    lifecycle_check($checks, 'lifecycle state machine includes '.$state, str_contains($stateText, $state));
}

lifecycle_check($checks, 'publish contract marks actual publish unsafe for agent execution', ($publish['safe_for_agent'] ?? true) === false && ($publish['agent_may_execute_publish'] ?? true) === false);
lifecycle_check($checks, 'publish contract allows planning only', ($publish['agent_may_plan_publish'] ?? false) === true && str_contains(lifecycle_flatten($publish), 'future publish'));

$operations = $removal['operations'] ?? [];
foreach ([
    'delete_draft_definition',
    'archive_definition',
    'disable_published_module',
    'hide_existing_module',
    'remove_capability',
    'uninstall_generated_module',
    'destructive_delete',
] as $operation) {
    lifecycle_check($checks, 'removal contract includes '.$operation, array_key_exists($operation, is_array($operations) ? $operations : []));
}

$destructive = is_array($operations) ? ($operations['destructive_delete'] ?? []) : [];
lifecycle_check($checks, 'destructive_delete is not allowed in current MVP', is_array($destructive) && ($destructive['allowed_in_current_mvp'] ?? true) === false);

$removalText = lifecycle_flatten($removal);
lifecycle_check($checks, 'module removal contract mentions backups', str_contains(strtolower($removalText), 'backup'));
lifecycle_check($checks, 'module removal contract mentions dependency analysis', str_contains(strtolower($removalText), 'dependency'));

$impactText = lifecycle_flatten($impact);
foreach (['routes', 'menus', 'permissions', 'tables', 'relations', 'media', 'RAG', 'vector indexes'] as $dependency) {
    lifecycle_check($checks, 'dependency impact map includes '.$dependency, str_contains($impactText, $dependency));
}

$capabilityText = lifecycle_flatten($capability);
lifecycle_check($checks, 'capability removal contract includes form_layout', str_contains($capabilityText, 'form_layout'));
lifecycle_check($checks, 'capability removal contract includes automation_process', str_contains($capabilityText, 'automation_process'));

$manifestText = lifecycle_flatten($manifest);
lifecycle_check($checks, 'RAG manifest mentions lifecycle', str_contains(strtolower($manifestText), 'lifecycle'));
lifecycle_check($checks, 'RAG manifest mentions removal', str_contains(strtolower($manifestText), 'removal'));

$safetyText = lifecycle_flatten($safety);
foreach (['execute publish', 'execute uninstall', 'delete runtime modules', 'drop tables'] as $forbidden) {
    lifecycle_check($checks, 'safety boundaries forbid '.$forbidden, str_contains(strtolower($safetyText), $forbidden));
}

$demoText = lifecycle_flatten($demo);
lifecycle_check($checks, 'demo contract mentions future lifecycle roadmap', str_contains($demoText, 'future_module_lifecycle_roadmap'));
lifecycle_check($checks, 'demo contract says current UI does not expose delete/publish/uninstall', str_contains($demoText, 'delete_available') && str_contains($demoText, 'publish_available') && str_contains($demoText, 'uninstall_available'));

$builderUiFiles = glob($root.'/modules/Builder/resources/js/**/*.{vue,js}', GLOB_BRACE) ?: [];
$builderUiSource = '';
foreach ($builderUiFiles as $file) {
    $builderUiSource .= file_get_contents($file) ?: '';
}

$actionPattern = '/text=["\'](?:Publish|Delete|Uninstall)["\']|runPublish|publishDefinition|deleteDefinition|uninstallModule|@publish|@delete|@uninstall/i';
lifecycle_check($checks, 'no UI publish/delete/uninstall buttons were added', ! preg_match($actionPattern, $builderUiSource));

$summaryPath = $root.'/modules/Builder/resources/js/components/BuilderDefinitionSummary.vue';
$summarySource = is_file($summaryPath) ? (file_get_contents($summaryPath) ?: '') : '';
$hasRoadmapNote = str_contains($summarySource, 'Future lifecycle');
lifecycle_check(
    $checks,
    'UI roadmap note is absent or non-actionable',
    ! $hasRoadmapNote || (
        str_contains($summarySource, 'archive') &&
        str_contains($summarySource, 'disable') &&
        ! preg_match($actionPattern, $summarySource)
    )
);

$status = lifecycle_status($root);
lifecycle_check($checks, 'git status command succeeds', $status !== '', trim($status));

$changedPaths = array_filter(array_map(
    static fn (string $line): string => trim(substr($line, 3)),
    preg_split('/\R/', trim($status))
));

foreach ([
    'app/Http/Controllers/Builder/',
    'app/Services/Builder/',
    'app/Models/',
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
    'database/migrations/',
    'modules/Warehouse/',
    'modules/Core/',
    'modules/SaaS/',
    'modules/Updater/',
    'modules/Installer/',
    'routes/',
    'resources/js/app.js',
    'vendor/',
    'node_modules/',
    'public/build/',
] as $forbiddenPath) {
    $changed = str_ends_with($forbiddenPath, '/')
        ? array_filter($changedPaths, static fn (string $path): bool => str_starts_with($path, $forbiddenPath))
        : in_array($forbiddenPath, $changedPaths, true);

    lifecycle_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    lifecycle_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
