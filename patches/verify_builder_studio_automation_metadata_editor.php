<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function automation_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function automation_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function automation_json(string $root, string $path): ?array
{
    $decoded = json_decode(automation_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function automation_git_status(string $root): string
{
    return shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
}

$architectureDoc = 'docs/ai/03-architecture/builder-studio-automation-metadata-editor.md';
$contract = 'docs/ai/05-rag/contracts/builder-automation-metadata-contract.json';
$history = 'docs/ai/04-docops/history/2026-07-02-builder-studio-automation-metadata-editor.md';
$component = 'modules/Builder/resources/js/components/BuilderAutomationEditor.vue';
$view = 'modules/Builder/resources/js/views/BuilderDefinitionView.vue';
$capabilities = 'modules/Builder/resources/js/components/BuilderCapabilitiesEditor.vue';
$raw = 'modules/Builder/resources/js/components/BuilderRawJsonEditor.vue';
$schema = 'docs/ai/05-rag/contracts/module-builder-mvp-schema.json';
$componentMap = 'docs/ai/05-rag/contracts/builder-studio-component-map.json';
$manifest = 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json';
$capabilityMap = 'docs/ai/05-rag/contracts/builder-capability-status-map.json';
$safety = 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json';

foreach ([$architectureDoc, $contract, $history, $component] as $file) {
    automation_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([$contract, $schema, $componentMap, $manifest, $capabilityMap, $safety] as $jsonFile) {
    automation_check($checks, $jsonFile.' valid JSON', automation_json($root, $jsonFile) !== null, json_last_error_msg());
}

$componentSource = automation_read($root, $component);
$viewSource = automation_read($root, $view);
$capabilitiesSource = automation_read($root, $capabilities);
$rawSource = automation_read($root, $raw);
$schemaSource = automation_read($root, $schema);
$componentMapSource = automation_read($root, $componentMap);
$manifestSource = automation_read($root, $manifest);
$capabilityMapSource = automation_read($root, $capabilityMap);
$safetySource = automation_read($root, $safety);

automation_check($checks, 'BuilderDefinitionView imports BuilderAutomationEditor', str_contains($viewSource, 'import BuilderAutomationEditor'));
automation_check($checks, 'BuilderDefinitionView renders BuilderAutomationEditor', str_contains($viewSource, '<BuilderAutomationEditor'));
automation_check($checks, 'automation normalization exists', str_contains($viewSource, 'function normalizeAutomation') && str_contains($viewSource, 'value.automation ||= {}'));

foreach ([
    'automation.enabled',
    'Workflows',
    'Trigger',
    'Conditions',
    'Actions',
    'record_created',
    'record_updated',
    'field_changed',
    'status_changed',
    'manual',
    'create_task',
    'send_email',
    'send_notification',
    'request_approval',
    'webhook',
    'taskTitle',
    'taskDueInDays',
    'emailTo',
    'emailSubject',
    'emailTemplate',
    'notificationMessage',
    'approvalRole',
    'webhookUrl',
] as $needle) {
    automation_check($checks, 'UI supports '.$needle, str_contains($componentSource, $needle));
}

automation_check($checks, 'metadata-only warning exists', str_contains($componentSource, 'Automation metadata only; runtime workflow engine is future work.') && str_contains($componentSource, 'Metadata only; this action will not execute in MVP.'));
automation_check($checks, 'field selects derive from definition.fields', str_contains($componentSource, 'props.definition.fields.filter'));
automation_check($checks, 'move up/down behavior exists for actions', str_contains($componentSource, 'moveAction') && str_contains($componentSource, 'Up') && str_contains($componentSource, 'Down'));
automation_check($checks, 'raw JSON remains available', str_contains($rawSource, 'Raw JSON') && str_contains($viewSource, '<BuilderRawJsonEditor'));
automation_check($checks, 'save action remains available', str_contains($viewSource, 'saveDefinition') && str_contains($viewSource, 'text="Save"'));
automation_check($checks, 'validate action remains available', str_contains($viewSource, 'runValidation') && str_contains($viewSource, 'text="Validate"'));
automation_check($checks, 'preview action remains available', str_contains($viewSource, 'runPreview') && str_contains($viewSource, 'text="Preview"'));
automation_check($checks, 'no publish action exists', ! preg_match('/text=["\']Publish["\']|publishDefinition|runPublish|@publish/i', $viewSource.$componentSource));
automation_check($checks, 'schema allows optional top-level automation', str_contains($schemaSource, '"automation"') && str_contains($schemaSource, '"workflows"') && str_contains($schemaSource, '"actions"'));

$runtimeExecutionPattern = '/Innoclapps\.request|axios|fetch\(|sendMail|Mail::|Notification::|Task::create|Approval::|webhookRequest|curl_|new XMLHttpRequest/i';
automation_check($checks, 'no runtime execution code exists in automation component', ! preg_match($runtimeExecutionPattern, $componentSource));
automation_check($checks, 'no email send runtime exists', ! preg_match('/sendMail|Mail::|emailService|smtp/i', $componentSource));
automation_check($checks, 'no task create API exists', ! preg_match('/Task::create|createTask\(|tasks\/create|taskApi/i', $componentSource));
automation_check($checks, 'no webhook request exists', ! preg_match('/fetch\(|axios|curl_|webhookRequest|new XMLHttpRequest/i', $componentSource));
automation_check($checks, 'no approval runtime call exists', ! preg_match('/Approval::|approvalApi|requestApproval\(/i', $componentSource));

automation_check($checks, 'RAG component map includes BuilderAutomationEditor', str_contains($componentMapSource, 'BuilderAutomationEditor'));
automation_check($checks, 'RAG manifest mentions automation metadata', str_contains($manifestSource, 'builder-automation-metadata-contract.json') && str_contains($manifestSource, 'definition_json.automation'));
automation_check($checks, 'capability map marks automation metadata editable', str_contains($capabilityMapSource, 'Metadata-editable in Builder Studio automation') && str_contains($capabilityMapSource, 'runtime execution is future work'));
automation_check($checks, 'safety boundaries forbid automation execution', str_contains($safetySource, 'execute automation metadata') && str_contains($safetySource, 'send emails from Builder metadata') && str_contains($safetySource, 'send webhooks from Builder metadata'));
automation_check($checks, 'capability UI contains automation helper', str_contains($capabilitiesSource, 'Automation metadata editor available; runtime execution is future work.'));

foreach (['Warehouse', 'Product', 'Inventory', 'SKU', 'sku', 'stock'] as $forbidden) {
    automation_check($checks, 'no fixed '.$forbidden.' business assumption introduced in Builder UI', ! preg_match('/\b'.preg_quote($forbidden, '/').'\b/', $componentSource.$viewSource.$capabilitiesSource));
}

$status = automation_git_status($root);
automation_check($checks, 'git status command succeeds', $status !== '', trim($status));

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

    automation_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    automation_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

automation_check($checks, 'no backend automation runtime implementation added', ! preg_match(
    '/BuilderAutomation|AutomationRuntime|WorkflowEngine|send_email|create_task|request_approval|webhookUrl/i',
    automation_read($root, 'app/Http/Controllers/Builder/BuilderDefinitionController.php').
    automation_read($root, 'app/Services/Builder/BuilderPreviewService.php').
    automation_read($root, 'app/Services/Builder/BuilderDefinitionValidator.php')
));
automation_check($checks, 'no app services changed for this automation batch', ! array_filter($changedPaths, fn (string $path): bool => str_starts_with($path, 'app/Services/Builder/')));

$failed = array_filter($checks, fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
