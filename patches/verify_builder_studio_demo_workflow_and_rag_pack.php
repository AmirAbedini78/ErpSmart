<?php

$root = dirname(__DIR__);
$failures = [];

function check(bool $condition, string $message): void
{
    global $failures;

    echo ($condition ? 'true ' : 'false ').$message.PHP_EOL;

    if (! $condition) {
        $failures[] = $message;
    }
}

function read_file(string $path): string
{
    global $root;

    return file_get_contents($root.'/'.$path) ?: '';
}

function json_file(string $path): array
{
    $json = json_decode(read_file($path), true);

    return is_array($json) ? $json : [];
}

function file_exists_in_repo(string $path): bool
{
    global $root;

    return is_file($root.'/'.$path);
}

function git_status(): string
{
    global $root;

    return shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
}

$demoDoc = 'docs/ai/03-architecture/builder-studio-demo-workflow.md';
$history = 'docs/ai/04-docops/history/2026-07-01-builder-studio-demo-workflow-and-rag-pack.md';
$manifestPath = 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json';
$componentMapPath = 'docs/ai/05-rag/contracts/builder-studio-component-map.json';
$apiMapPath = 'docs/ai/05-rag/contracts/builder-studio-api-map.json';
$capabilityMapPath = 'docs/ai/05-rag/contracts/builder-capability-status-map.json';
$safetyPath = 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json';

check(file_exists_in_repo($demoDoc), 'demo workflow doc exists');
check(file_exists_in_repo($history), 'history note exists');

$jsonPaths = [
    $manifestPath,
    $componentMapPath,
    $apiMapPath,
    $capabilityMapPath,
    $safetyPath,
];

foreach ($jsonPaths as $path) {
    $decoded = json_file($path);
    check(file_exists_in_repo($path), $path.' exists');
    check($decoded !== [], $path.' is valid JSON');
}

$manifest = json_file($manifestPath);
$componentMap = json_file($componentMapPath);
$apiMap = json_file($apiMapPath);
$capabilityMap = json_file($capabilityMapPath);
$safety = json_file($safetyPath);

$manifestText = json_encode($manifest);
$safetyText = json_encode($safety);

check(isset($manifest['source_of_truth']), 'manifest mentions source_of_truth');
check(str_contains($manifestText, 'AI Builder Agent'), 'manifest mentions Builder Agent');
check(str_contains($manifestText, 'Business Operations Agent'), 'manifest mentions Business Operations Agent separation');

$componentText = json_encode($componentMap);
foreach ([
    'BuilderDefinitionView',
    'BuilderModuleIdentityForm',
    'BuilderFieldsEditor',
    'BuilderCapabilitiesEditor',
    'BuilderRelationsEditor',
    'BuilderRawJsonEditor',
    'BuilderValidationPreviewPanel',
] as $component) {
    check(str_contains($componentText, $component), 'component map lists '.$component);
}

$expectedEndpoints = [
    'GET /api/builder/definitions',
    'POST /api/builder/definitions',
    'GET /api/builder/definitions/{id}',
    'PUT /api/builder/definitions/{id}',
    'POST /api/builder/definitions/{id}/validate',
    'POST /api/builder/definitions/{id}/preview',
];

$endpoints = $apiMap['endpoints'] ?? [];
$endpointStrings = array_map(
    fn ($endpoint) => ($endpoint['method'] ?? '').' '.($endpoint['path'] ?? ''),
    is_array($endpoints) ? $endpoints : []
);

foreach ($expectedEndpoints as $endpoint) {
    check(in_array($endpoint, $endpointStrings, true), 'API map lists '.$endpoint);
}

$publishRelatedFlags = array_map(fn ($endpoint) => $endpoint['publish_related'] ?? null, is_array($endpoints) ? $endpoints : []);
check($publishRelatedFlags !== [] && ! in_array(true, $publishRelatedFlags, true), 'API map marks publish_related false');

check(isset($capabilityMap['preview_safe']) && is_array($capabilityMap['preview_safe']), 'capability map includes preview_safe group');
check(isset($capabilityMap['warning_only']) && is_array($capabilityMap['warning_only']), 'capability map includes warning_only group');
check(in_array('workflow', $capabilityMap['warning_only'] ?? [], true), 'capability map marks workflow warning-only');
check(in_array('emails', $capabilityMap['warning_only'] ?? [], true), 'capability map marks emails warning-only');

check(str_contains($safetyText, 'publish'), 'safety boundaries forbid publish');
check(str_contains($safetyText, 'write runtime modules directly'), 'safety boundaries forbid direct runtime module writes');
check(str_contains($safetyText, 'run migrations directly'), 'safety boundaries forbid migrations');
check(isset($safety['source_of_truth_rules']), 'safety boundaries include source_of_truth rules');

$index = read_file('modules/Builder/resources/js/views/BuilderDefinitionsIndex.vue');
$view = read_file('modules/Builder/resources/js/views/BuilderDefinitionView.vue');
$panel = read_file('modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue');
$api = read_file('modules/Builder/resources/js/services/builderApi.js');
$uiText = $index.$view.$panel.$api;

check(str_contains($index, 'Create a draft, edit visually, validate, and preview'), 'UI contains demo workflow/help text');
check(str_contains($view, 'Demo flow'), 'detail UI contains Demo flow card');
check(str_contains($uiText, 'Save'), 'UI contains save action');
check(str_contains($uiText, 'Validate'), 'UI contains validate action');
check(str_contains($uiText, 'Preview'), 'UI contains preview action');
check(! preg_match('/text=["\']Publish["\']|publishDefinition|runPublish|@publish/i', $uiText), 'UI contains no publish action');

foreach ([
    'listDefinitions',
    'createDefinition',
    'getDefinition',
    'updateDefinition',
    'validateDefinition',
    'previewDefinition',
] as $method) {
    check(str_contains($api, 'function '.$method) || str_contains($api, 'const '.$method), 'builderApi method '.$method.' exists');
}

foreach ([
    '/builder/definitions',
    '/validate',
    '/preview',
] as $endpointString) {
    check(str_contains($api, $endpointString), 'API service contains '.$endpointString);
}

foreach ([
    'Warehouse',
    'Product',
    'Inventory',
    'sku',
    'price',
    'stock',
] as $forbiddenBusinessTerm) {
    check(! preg_match('/\b'.preg_quote($forbiddenBusinessTerm, '/').'\b/i', $uiText), 'no fixed '.$forbiddenBusinessTerm.' business assumption introduced');
}

$status = git_status();
$changedPaths = array_filter(array_map(
    fn ($line) => trim(substr($line, 3)),
    preg_split('/\R/', trim($status))
));

$forbiddenPrefixes = [
    'modules/Warehouse/',
    'modules/Core/',
    'modules/SaaS/',
    'modules/Updater/',
    'modules/Installer/',
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
    'database/migrations/',
    'vendor/',
    'node_modules/',
    'public/build/',
];

$forbiddenExact = [
    'package.json',
    'package-lock.json',
    'composer.json',
    'composer.lock',
];

foreach ($forbiddenPrefixes as $prefix) {
    check(! array_filter($changedPaths, fn ($path) => str_starts_with($path, $prefix)), 'no '.$prefix.' files changed');
}

foreach ($forbiddenExact as $path) {
    check(! in_array($path, $changedPaths, true), 'no '.$path.' changed');
}

check(! preg_match('/SaaS|License|Licensing|Updater/i', $status), 'no SaaS/license/update code changed');

if ($failures !== []) {
    echo 'FAIL'.PHP_EOL;
    exit(1);
}

echo 'PASS'.PHP_EOL;
