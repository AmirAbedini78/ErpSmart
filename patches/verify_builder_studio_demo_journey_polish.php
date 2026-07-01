<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function journey_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function journey_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function journey_json(string $root, string $path): ?array
{
    $decoded = json_decode(journey_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function journey_git_status(string $root): string
{
    return shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
}

$doc = 'docs/ai/03-architecture/builder-studio-demo-journey-polish.md';
$contract = 'docs/ai/05-rag/contracts/builder-studio-demo-journey-contract.json';
$history = 'docs/ai/04-docops/history/2026-07-02-builder-studio-demo-journey-polish.md';
$index = 'modules/Builder/resources/js/views/BuilderDefinitionsIndex.vue';
$view = 'modules/Builder/resources/js/views/BuilderDefinitionView.vue';
$summary = 'modules/Builder/resources/js/components/BuilderDefinitionSummary.vue';
$raw = 'modules/Builder/resources/js/components/BuilderRawJsonEditor.vue';
$formLayout = 'modules/Builder/resources/js/components/BuilderFormLayoutEditor.vue';
$automation = 'modules/Builder/resources/js/components/BuilderAutomationEditor.vue';
$componentMap = 'docs/ai/05-rag/contracts/builder-studio-component-map.json';
$manifest = 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json';
$safety = 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json';

foreach ([$doc, $contract, $history, $summary] as $file) {
    journey_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([$contract, $componentMap, $manifest, $safety] as $jsonFile) {
    journey_check($checks, $jsonFile.' valid JSON', journey_json($root, $jsonFile) !== null, json_last_error_msg());
}

$indexSource = journey_read($root, $index);
$viewSource = journey_read($root, $view);
$summarySource = journey_read($root, $summary);
$rawSource = journey_read($root, $raw);
$formLayoutSource = journey_read($root, $formLayout);
$automationSource = journey_read($root, $automation);
$componentMapSource = journey_read($root, $componentMap);
$manifestSource = journey_read($root, $manifest);
$safetySource = journey_read($root, $safety);

journey_check($checks, 'UI contains demo route/entrypoint information', str_contains($indexSource, 'Builder Studio') && str_contains($indexSource, 'Create draft'));
journey_check($checks, 'UI contains Validate and Preview only notice', str_contains($indexSource, 'Validate and Preview only'));
journey_check($checks, 'UI contains Publish absent notice', str_contains($indexSource.$viewSource, 'Publish is intentionally absent') || str_contains($indexSource.$viewSource, 'Publish is not available yet'));
journey_check($checks, 'index contains create draft CTA', str_contains($indexSource, 'text="Create draft"'));
journey_check($checks, 'index contains empty state', str_contains($indexSource, 'No builder definitions yet.'));
journey_check($checks, 'index contains definition counts', str_contains($indexSource, 'Total definitions') && str_contains($indexSource, 'Previewed'));

foreach ([
    'Identity',
    'Fields',
    'Form Layout',
    'Automation',
    'Capabilities',
    'Relations',
    'Raw JSON',
    'Validate',
    'Preview',
] as $section) {
    journey_check($checks, 'detail contains section '.$section, str_contains($viewSource.$rawSource, $section));
}

journey_check($checks, 'summary component is referenced', str_contains($viewSource, 'BuilderDefinitionSummary'));
journey_check($checks, 'summary shows field count', str_contains($summarySource, 'Field count'));
journey_check($checks, 'summary shows relation count', str_contains($summarySource, 'Relation count'));
journey_check($checks, 'summary shows capability count', str_contains($summarySource, 'Capability count'));
journey_check($checks, 'summary shows Preview-only safety notice', str_contains($summarySource, 'Preview-only MVP'));
journey_check($checks, 'summary shows No publish safety notice', str_contains($summarySource, 'No publish'));
journey_check($checks, 'summary shows No runtime writes safety notice', str_contains($summarySource, 'No runtime writes'));
journey_check($checks, 'section navigation exists', str_contains($viewSource, 'Section Navigation') && str_contains($viewSource, 'sectionNavigation'));
journey_check($checks, 'form layout metadata-only warning remains', str_contains($formLayoutSource, 'Form layout metadata only'));
journey_check($checks, 'automation metadata-only warning remains', str_contains($automationSource, 'Automation metadata only'));
journey_check($checks, 'raw JSON remains available', str_contains($rawSource, 'Raw JSON') && str_contains($viewSource, '<BuilderRawJsonEditor'));
journey_check($checks, 'save action remains available', str_contains($viewSource, 'saveDefinition') && str_contains($viewSource, 'text="Save"'));
journey_check($checks, 'validate action remains available', str_contains($viewSource, 'runValidation') && str_contains($viewSource, 'text="Validate"'));
journey_check($checks, 'preview action remains available', str_contains($viewSource, 'runPreview') && str_contains($viewSource, 'text="Preview"'));
journey_check($checks, 'no publish action exists', ! preg_match('/text=["\']Publish["\']|publishDefinition|runPublish|@publish/i', $indexSource.$viewSource.$summarySource));

journey_check($checks, 'RAG manifest mentions demo journey', str_contains($manifestSource, 'builder-studio-demo-journey-contract.json') && str_contains($manifestSource, 'manual browser smoke journey'));
journey_check($checks, 'RAG component map includes BuilderDefinitionSummary', str_contains($componentMapSource, 'BuilderDefinitionSummary'));
journey_check($checks, 'safety boundaries mention no publish claim', str_contains($safetySource, 'claim publish exists'));
journey_check($checks, 'demo contract documents future lifecycle roadmap', str_contains(journey_read($root, $contract), 'uninstall published module') && str_contains(journey_read($root, $doc), 'Builder Module Lifecycle task'));

foreach (['Warehouse', 'Product', 'Inventory', 'SKU', 'sku', 'stock'] as $forbidden) {
    journey_check($checks, 'no fixed '.$forbidden.' business assumption introduced in Builder UI', ! preg_match('/\b'.preg_quote($forbidden, '/').'\b/', $indexSource.$viewSource.$summarySource));
}

$status = journey_git_status($root);
journey_check($checks, 'git status command succeeds', $status !== '', trim($status));

$changedPaths = array_filter(array_map(
    fn (string $line): string => trim(substr($line, 3)),
    preg_split('/\R/', trim($status))
));

foreach ([
    'app/Http/Controllers/Builder/',
    'app/Services/Builder/',
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
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
        ? array_filter($changedPaths, fn (string $path): bool => str_starts_with($path, $forbiddenPath))
        : in_array($forbiddenPath, $changedPaths, true);

    journey_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    journey_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
