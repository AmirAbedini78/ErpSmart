<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function visual_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function visual_run(string $root, string $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);

    if (! is_resource($process)) {
        return [1, 'Unable to start command'];
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

function visual_read(string $root, string $path): string
{
    return is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : '';
}

$doc = 'docs/ai/03-architecture/builder-studio-visual-definition-editor.md';
$history = 'docs/ai/04-docops/history/2026-07-01-builder-studio-visual-definition-editor.md';
visual_check($checks, 'architecture doc exists', is_file($root.'/'.$doc));
visual_check($checks, 'history note exists', is_file($root.'/'.$history));

$componentFiles = [
    'modules/Builder/resources/js/views/BuilderDefinitionView.vue',
    'modules/Builder/resources/js/components/BuilderModuleIdentityForm.vue',
    'modules/Builder/resources/js/components/BuilderFieldsEditor.vue',
    'modules/Builder/resources/js/components/BuilderCapabilitiesEditor.vue',
    'modules/Builder/resources/js/components/BuilderRelationsEditor.vue',
    'modules/Builder/resources/js/components/BuilderRawJsonEditor.vue',
    'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue',
    'modules/Builder/resources/js/components/BuilderStatusBadge.vue',
];

foreach ($componentFiles as $file) {
    visual_check($checks, $file.' exists', is_file($root.'/'.$file));
}

$view = visual_read($root, 'modules/Builder/resources/js/views/BuilderDefinitionView.vue');
$identity = visual_read($root, 'modules/Builder/resources/js/components/BuilderModuleIdentityForm.vue');
$fields = visual_read($root, 'modules/Builder/resources/js/components/BuilderFieldsEditor.vue');
$capabilities = visual_read($root, 'modules/Builder/resources/js/components/BuilderCapabilitiesEditor.vue');
$relations = visual_read($root, 'modules/Builder/resources/js/components/BuilderRelationsEditor.vue');
$raw = visual_read($root, 'modules/Builder/resources/js/components/BuilderRawJsonEditor.vue');
$panel = visual_read($root, 'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue');
$api = visual_read($root, 'modules/Builder/resources/js/services/builderApi.js');
$builderText = implode("\n", [$view, $identity, $fields, $capabilities, $relations, $raw, $panel, $api]);

visual_check($checks, 'module identity editor exists', str_contains($view, 'BuilderModuleIdentityForm') && str_contains($identity, 'Module name') && str_contains($identity, 'globalSearchAction'));
visual_check($checks, 'fields editor exists', str_contains($view, 'BuilderFieldsEditor') && str_contains($fields, 'Fields'));
visual_check($checks, 'add field behavior strings exist', str_contains($fields, 'Add field') && str_contains($fields, 'field_'));
visual_check($checks, 'remove field behavior strings exist', str_contains($fields, 'Remove') && str_contains($fields, 'removeField'));
visual_check($checks, 'capabilities editor exists', str_contains($view, 'BuilderCapabilitiesEditor') && str_contains($capabilities, 'Capabilities'));
foreach (['tableable', 'hasDetailView', 'notes', 'activities', 'media', 'workflow', 'conditionalVisibility'] as $capability) {
    visual_check($checks, 'capability '.$capability.' exists', str_contains($capabilities, $capability));
}
visual_check($checks, 'future/warning capability label exists', str_contains($capabilities, 'Preview warning only'));
visual_check($checks, 'relations editor exists', str_contains($view, 'BuilderRelationsEditor') && str_contains($relations, 'Relations'));
visual_check($checks, 'MVP relation types exist', str_contains($relations, 'belongsTo') && str_contains($relations, 'hasMany'));
visual_check($checks, 'future relation types are labeled', str_contains($relations, 'belongsToMany') && str_contains($relations, 'future/preview warning only'));
visual_check($checks, 'raw JSON editor still exists', str_contains($view, 'BuilderRawJsonEditor') && str_contains($raw, 'Raw JSON'));
visual_check($checks, 'Apply Raw JSON action exists', str_contains($raw, 'Apply Raw JSON') && str_contains($view, 'applyRawJson'));
visual_check($checks, 'Format JSON action exists', str_contains($raw, 'Format JSON') && str_contains($view, 'formatRawJson'));
visual_check($checks, 'visual editor updates raw JSON', str_contains($view, 'handleVisualChange') && str_contains($view, 'definitionText.value = stringify'));
visual_check($checks, 'raw JSON updates visual editor', str_contains($view, 'definitionJson.value = normalizeDefinition'));
visual_check($checks, 'validate action exists', str_contains($panel, 'Validate') && str_contains($view, 'runValidation'));
visual_check($checks, 'preview action exists', str_contains($panel, 'Preview') && str_contains($view, 'runPreview'));
visual_check($checks, 'save action exists', str_contains($panel, 'Save') && str_contains($view, 'saveDefinition'));
visual_check($checks, 'no publish action exists', stripos($builderText, 'publish') === false);
foreach (['listDefinitions', 'createDefinition', 'getDefinition', 'updateDefinition', 'validateDefinition', 'previewDefinition'] as $method) {
    visual_check($checks, 'builderApi method '.$method.' exists', str_contains($api, 'function '.$method) || str_contains($api, 'const '.$method));
}
foreach (['/builder/definitions', '/validate', '/preview'] as $endpoint) {
    visual_check($checks, 'API endpoint '.$endpoint.' exists', str_contains($api, $endpoint));
}

foreach (['Warehouse', 'Product', 'Inventory', 'sku', 'price', 'stock'] as $forbidden) {
    visual_check($checks, 'no fixed '.$forbidden.' business assumption introduced', stripos($builderText, $forbidden) === false);
}

[$statusCode, $statusOutput] = visual_run($root, 'git -c safe.directory='.escapeshellarg($root).' status --short');
visual_check($checks, 'git status command succeeds', $statusCode === 0, trim($statusOutput));

$forbiddenChanged = [];
foreach (explode("\n", trim($statusOutput)) as $line) {
    if ($line === '') {
        continue;
    }

    $path = trim(substr($line, 3));
    if (
        str_starts_with($path, 'modules/Warehouse/')
        || str_starts_with($path, 'modules/Core/')
        || str_starts_with($path, 'modules/Saas/')
        || str_starts_with($path, 'modules/Updater/')
        || str_starts_with($path, 'app/Console/Commands/ErpsmartMakeModuleCommand.php')
        || str_starts_with($path, 'vendor/')
        || str_starts_with($path, 'node_modules/')
        || str_starts_with($path, 'public/build/')
        || in_array($path, ['package.json', 'package-lock.json', 'composer.json', 'composer.lock'], true)
    ) {
        $forbiddenChanged[] = $line;
    }
}

visual_check($checks, 'no SaaS/license/update code changed', ! array_filter($forbiddenChanged, fn (string $line): bool => str_contains($line, 'modules/Saas/') || str_contains($line, 'modules/Updater/')));
visual_check($checks, 'no Warehouse/Core files changed', ! array_filter($forbiddenChanged, fn (string $line): bool => str_contains($line, 'modules/Warehouse/') || str_contains($line, 'modules/Core/')));
visual_check($checks, 'no package/composer/vendor/build files changed', ! array_filter($forbiddenChanged, fn (string $line): bool => preg_match('#(vendor/|node_modules/|public/build/|package\.json|package-lock\.json|composer\.json|composer\.lock)#', $line) === 1));
visual_check($checks, 'app/Console command not modified', ! array_filter($forbiddenChanged, fn (string $line): bool => str_contains($line, 'app/Console/Commands/ErpsmartMakeModuleCommand.php')));

echo "Reminder: run docker compose exec node npm run build after PASS.\n";

$failed = array_filter($checks, fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
