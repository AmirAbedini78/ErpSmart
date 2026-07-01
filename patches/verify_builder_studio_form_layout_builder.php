<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function form_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function form_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function form_json(string $root, string $path): ?array
{
    $json = json_decode(form_read($root, $path), true);

    return is_array($json) ? $json : null;
}

function form_git_status(string $root): string
{
    return shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
}

$architectureDoc = 'docs/ai/03-architecture/builder-studio-form-layout-builder.md';
$contract = 'docs/ai/05-rag/contracts/builder-form-layout-contract.json';
$history = 'docs/ai/04-docops/history/2026-07-01-builder-studio-form-layout-builder.md';
$component = 'modules/Builder/resources/js/components/BuilderFormLayoutEditor.vue';
$view = 'modules/Builder/resources/js/views/BuilderDefinitionView.vue';
$capabilities = 'modules/Builder/resources/js/components/BuilderCapabilitiesEditor.vue';
$raw = 'modules/Builder/resources/js/components/BuilderRawJsonEditor.vue';
$schema = 'docs/ai/05-rag/contracts/module-builder-mvp-schema.json';
$componentMap = 'docs/ai/05-rag/contracts/builder-studio-component-map.json';
$manifest = 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json';
$capabilityMap = 'docs/ai/05-rag/contracts/builder-capability-status-map.json';

foreach ([$architectureDoc, $contract, $history, $component] as $file) {
    form_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([$contract, $schema, $componentMap, $manifest, $capabilityMap] as $jsonFile) {
    form_check($checks, $jsonFile.' valid JSON', form_json($root, $jsonFile) !== null, json_last_error_msg());
}

$componentSource = form_read($root, $component);
$viewSource = form_read($root, $view);
$capabilitiesSource = form_read($root, $capabilities);
$rawSource = form_read($root, $raw);
$schemaSource = form_read($root, $schema);
$componentMapSource = form_read($root, $componentMap);
$manifestSource = form_read($root, $manifest);
$capabilityMapSource = form_read($root, $capabilityMap);

form_check($checks, 'BuilderDefinitionView imports BuilderFormLayoutEditor', str_contains($viewSource, 'import BuilderFormLayoutEditor'));
form_check($checks, 'BuilderDefinitionView renders BuilderFormLayoutEditor', str_contains($viewSource, '<BuilderFormLayoutEditor'));
form_check($checks, 'formLayout normalization exists', str_contains($viewSource, 'function normalizeFormLayout') && str_contains($viewSource, 'value.formLayout ||= {}'));

foreach ([
    'layout.enabled',
    'layout.mode',
    'Sections',
    'Section fields',
    'Stepper',
    'Conditions',
    'readonlyOn',
    'hiddenOn',
    'requiredOverride',
    'helpText',
    'targetField',
    'operator',
    'appliesTo',
] as $needle) {
    form_check($checks, 'UI supports '.$needle, str_contains($componentSource, $needle));
}

form_check($checks, 'stepper metadata-only warning exists', str_contains($componentSource, 'Stepper metadata only; runtime renderer not implemented yet.'));
form_check($checks, 'conditional visibility metadata-only warning exists', str_contains($componentSource, 'Conditional visibility metadata only; runtime renderer not implemented yet.'));
form_check($checks, 'form layout metadata-only warning exists', str_contains($componentSource, 'Form layout metadata only; runtime renderer not implemented yet.'));
form_check($checks, 'fields are derived from definition.fields', str_contains($componentSource, 'props.definition.fields.filter'));
form_check($checks, 'move up/down behavior exists', str_contains($componentSource, 'moveItem') && str_contains($componentSource, 'moveSection') && str_contains($componentSource, 'moveField') && str_contains($componentSource, 'moveStep'));
form_check($checks, 'raw JSON remains available', str_contains($rawSource, 'Raw JSON') && str_contains($viewSource, '<BuilderRawJsonEditor'));
form_check($checks, 'save action remains available', str_contains($viewSource, 'saveDefinition') && str_contains($viewSource, 'text="Save"'));
form_check($checks, 'validate action remains available', str_contains($viewSource, 'runValidation') && str_contains($viewSource, 'text="Validate"'));
form_check($checks, 'preview action remains available', str_contains($viewSource, 'runPreview') && str_contains($viewSource, 'text="Preview"'));
form_check($checks, 'no publish action exists', ! preg_match('/text=["\']Publish["\']|publishDefinition|runPublish|@publish/i', $viewSource.$componentSource));
form_check($checks, 'schema allows optional top-level formLayout', str_contains($schemaSource, '"formLayout"') && str_contains($schemaSource, '"sections"') && str_contains($schemaSource, '"conditions"'));

form_check($checks, 'RAG component map includes BuilderFormLayoutEditor', str_contains($componentMapSource, 'BuilderFormLayoutEditor'));
form_check($checks, 'RAG manifest mentions form layout', str_contains($manifestSource, 'builder-form-layout-contract.json') && str_contains($manifestSource, 'definition_json.formLayout'));
form_check($checks, 'capability map marks formLayout metadata-editable', str_contains($capabilityMapSource, 'Metadata-editable in Builder Studio') && str_contains($capabilityMapSource, 'runtime renderer is future work'));
form_check($checks, 'capability UI contains metadata helper', str_contains($capabilitiesSource, 'Metadata editor available; runtime renderer is future work.'));

foreach (['Warehouse', 'Product', 'Inventory', 'SKU', 'sku', 'stock'] as $forbidden) {
    form_check($checks, 'no fixed '.$forbidden.' business assumption introduced in Builder UI', ! preg_match('/\b'.preg_quote($forbidden, '/').'\b/', $componentSource.$viewSource.$capabilitiesSource));
}

$status = form_git_status($root);
form_check($checks, 'git status command succeeds', $status !== '', trim($status));

$changedPaths = array_filter(array_map(
    fn (string $line): string => trim(substr($line, 3)),
    preg_split('/\R/', trim($status))
));

foreach ([
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

    form_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    form_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

form_check(
    $checks,
    'no backend form layout runtime implementation added',
    ! preg_match(
        '/BuilderFormLayout|FormLayoutRenderer|renderFormLayout|formLayoutRenderer|consumeFormLayout/i',
        form_read($root, 'app/Http/Controllers/Builder/BuilderDefinitionController.php').
        form_read($root, 'app/Services/Builder/BuilderPreviewService.php').
        form_read($root, 'app/Services/Builder/BuilderDefinitionValidator.php')
    )
);
form_check($checks, 'no app services changed for this form layout batch', ! array_filter($changedPaths, fn (string $path): bool => str_starts_with($path, 'app/Services/Builder/')));

$failed = array_filter($checks, fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
