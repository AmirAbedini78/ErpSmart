<?php

$root = dirname(__DIR__);
$checks = [];
$offDefinition = 'docs/ai/05-rag/examples/definition-driven-capabilities-off-module.json';
$onDefinition = 'docs/ai/05-rag/examples/definition-driven-capabilities-on-module.json';
$previewBase = $root.'/storage/app/module-builder-preview';

function cap_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function cap_run(string $root, string $command): array
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

function cap_remove_dir(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

function cap_files_under(string $path): array
{
    if (! is_dir($path)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $files[] = $item->getPathname();
        }
    }

    sort($files);

    return $files;
}

function cap_json_file(string $path): ?array
{
    if (! is_file($path)) {
        return null;
    }

    $decoded = json_decode(file_get_contents($path) ?: '', true);

    return is_array($decoded) ? $decoded : null;
}

[$lintCode] = cap_run($root, 'php -l app/Console/Commands/ErpsmartMakeModuleCommand.php');
cap_check($checks, 'command PHP syntax is valid', $lintCode === 0);

$offJson = cap_json_file($root.'/'.$offDefinition);
$onJson = cap_json_file($root.'/'.$onDefinition);
cap_check($checks, 'capabilities-off definition exists and is valid JSON', $offJson !== null);
cap_check($checks, 'capabilities-on definition exists and is valid JSON', $onJson !== null);

cap_remove_dir($previewBase.'/MinimalRecords');
cap_remove_dir($previewBase.'/EnhancedRecords');

[$offCode, $offOutput] = cap_run(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($offDefinition).' --preview'
);
[$onCode, $onOutput] = cap_run(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($onDefinition).' --preview'
);

cap_check($checks, 'preview off succeeds', $offCode === 0, trim($offOutput));
cap_check($checks, 'preview on succeeds', $onCode === 0, trim($onOutput));
cap_check($checks, 'preview off reports zero runtime writes', str_contains($offOutput, 'Real runtime writes performed: 0'));
cap_check($checks, 'preview on reports zero runtime writes', str_contains($onOutput, 'Real runtime writes performed: 0'));

$previewFiles = array_merge(cap_files_under($previewBase.'/MinimalRecords'), cap_files_under($previewBase.'/EnhancedRecords'));
$realPreviewBase = realpath($previewBase) ?: $previewBase;
$onlyPreview = $previewFiles !== [];

foreach ($previewFiles as $file) {
    $realFile = realpath($file) ?: $file;

    if (! str_starts_with($realFile, $realPreviewBase.DIRECTORY_SEPARATOR)) {
        $onlyPreview = false;
        break;
    }
}

cap_check($checks, 'preview files are only under storage/app/module-builder-preview', $onlyPreview);

$offRoot = $previewBase.'/MinimalRecords/modules/MinimalRecords';
$onRoot = $previewBase.'/EnhancedRecords/modules/EnhancedRecords';
$offModel = file_get_contents($offRoot.'/app/Models/MinimalRecord.php') ?: '';
$offResource = file_get_contents($offRoot.'/app/Resources/MinimalRecord.php') ?: '';
$offJsonResource = file_get_contents($offRoot.'/app/Http/Resources/MinimalRecordResource.php') ?: '';
$offView = file_get_contents($offRoot.'/resources/js/views/MinimalRecordsView.vue') ?: '';
$offApp = file_get_contents($offRoot.'/resources/js/app.js') ?: '';
$onModel = file_get_contents($onRoot.'/app/Models/EnhancedRecord.php') ?: '';
$onResource = file_get_contents($onRoot.'/app/Resources/EnhancedRecord.php') ?: '';
$onJsonResource = file_get_contents($onRoot.'/app/Http/Resources/EnhancedRecordResource.php') ?: '';
$onView = file_get_contents($onRoot.'/resources/js/views/EnhancedRecordsView.vue') ?: '';
$onApp = file_get_contents($onRoot.'/resources/js/app.js') ?: '';

foreach (['HasMedia', 'HasActivities', 'AssociatesResources', 'PipesComments', 'CreateRelatedActivityAction'] as $token) {
    cap_check($checks, 'off preview excludes '.$token, ! str_contains($offModel.$offResource, $token));
}

foreach (["Tab::make('notes'", "Tab::make('activities'", "Panel::make('media'", 'resource-media-panel'] as $token) {
    cap_check($checks, 'off preview excludes '.$token, ! str_contains($offResource, $token));
}

cap_check($checks, 'off preview excludes import_id', ! str_contains($offModel.$offJsonResource, 'import_id'));
cap_check($checks, 'off preview excludes floating edit action', ! str_contains($offResource, 'floatResourceInEditMode'));
cap_check($checks, 'off preview excludes floating modal component file', ! is_file($offRoot.'/resources/js/components/MinimalRecordFloatingModal.vue'));
cap_check($checks, 'off preview has empty local tab component map', str_contains($offView, 'const tabComponents = {}'));
cap_check($checks, 'off app does not register floating modal', ! str_contains($offApp, 'MinimalRecordFloatingModal'));
cap_check($checks, 'off globalSearch is false', str_contains($offResource, 'public static bool $globallySearchable = false;'));

foreach (['HasMedia', 'HasActivities'] as $token) {
    cap_check($checks, 'on model includes '.$token, str_contains($onModel, $token));
}

foreach (['AssociatesResources', 'Mediable', 'PipesComments', 'CreateRelatedActivityAction'] as $token) {
    cap_check($checks, 'on resource includes '.$token, str_contains($onResource, $token));
}

foreach (["Tab::make('notes'", "Tab::make('activities'", "Panel::make('media'", 'resource-media-panel'] as $token) {
    cap_check($checks, 'on preview includes '.$token, str_contains($onResource, $token));
}

cap_check($checks, 'on preview includes import_id because importable true', str_contains($onModel, "'import_id',") && str_contains($onJsonResource, "'import_id' => \$this->import_id,"));
cap_check($checks, 'on preview includes floating edit action', str_contains($onResource, 'floatResourceInEditMode'));
cap_check($checks, 'on preview includes floating modal component file', is_file($onRoot.'/resources/js/components/EnhancedRecordFloatingModal.vue'));
cap_check($checks, 'on view maps activities and notes components', str_contains($onView, "'activities-tab': ActivitiesTab") && str_contains($onView, "'notes-tab': RecordTabNote"));
cap_check($checks, 'on app registers floating modal', str_contains($onApp, 'EnhancedRecordFloatingModal'));
cap_check($checks, 'on globalSearch is true', str_contains($onResource, 'public static bool $globallySearchable = true;'));

foreach (['warehouse', 'product', 'inventory', 'sku', 'stock'] as $forbidden) {
    cap_check($checks, 'off preview avoids '.$forbidden, stripos($offModel.$offResource.$offJsonResource, $forbidden) === false);
    cap_check($checks, 'on preview avoids '.$forbidden, stripos($onModel.$onResource.$onJsonResource, $forbidden) === false);
}

$warningDefinition = $onJson;
$warningDefinition['module']['name'] = 'WarningRecords';
$warningDefinition['module']['namespace'] = 'Modules\\WarningRecords';
$warningDefinition['module']['singularLabel'] = 'WarningRecord';
$warningDefinition['module']['pluralLabel'] = 'WarningRecords';
$warningDefinition['module']['table'] = 'warning_records';
$warningDefinition['module']['routeName'] = 'warning-records';
$warningDefinition['module']['resourceName'] = 'warning-records';
$warningDefinition['resource']['modelClass'] = 'Modules\\WarningRecords\\Models\\WarningRecord';

foreach (['documents', 'calls', 'emails', 'emailSending', 'workflow', 'tasks', 'approvals', 'notifications', 'timeline', 'softDeletes', 'stepperForm', 'formLayout', 'sections', 'conditionalVisibility'] as $capability) {
    $warningDefinition['capabilities'][$capability] = true;
}

if (! is_dir($previewBase)) {
    mkdir($previewBase, 0775, true);
}

$warningPath = $previewBase.'/capability-warning-definition.json';
file_put_contents($warningPath, json_encode($warningDefinition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

[$warningCode, $warningOutput] = cap_run(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg('storage/app/module-builder-preview/capability-warning-definition.json').' --dry-run'
);

cap_check($checks, 'warning definition dry-run succeeds', $warningCode === 0);

foreach (['documents', 'calls', 'emails', 'emailSending', 'workflow', 'tasks', 'approvals', 'notifications', 'timeline', 'softDeletes', 'stepperForm', 'formLayout', 'sections', 'conditionalVisibility'] as $capability) {
    cap_check(
        $checks,
        'warning is printed for '.$capability,
        str_contains($warningOutput, $capability.' requested but is future/unsupported in preview; no unsafe APIs are generated')
    );
}

cap_check($checks, 'no real MinimalRecords module exists', ! is_dir($root.'/modules/MinimalRecords'));
cap_check($checks, 'no real EnhancedRecords module exists', ! is_dir($root.'/modules/EnhancedRecords'));
cap_check($checks, 'no real warning module exists', ! is_dir($root.'/modules/WarningRecords'));

[$statusCode, $statusOutput] = cap_run(
    $root,
    'git -c safe.directory='.escapeshellarg($root).' status --porcelain --untracked-files=all'
);

$changedFiles = array_filter(array_map(
    fn (string $line): string => trim(substr($line, 3)),
    explode(PHP_EOL, trim($statusOutput))
));

$forbiddenPatterns = [
    '#^modules/Warehouse/#',
    '#^modules/Core/#',
    '#^modules/Activities/#',
    '#^modules/Notes/#',
    '#^modules/MinimalRecords/#',
    '#^modules/EnhancedRecords/#',
    '#^modules/WarningRecords/#',
    '#^database/migrations/#',
    '#^vendor/#',
    '#^node_modules/#',
    '#^public/build/#',
    '#^package\.json$#',
    '#^package-lock\.json$#',
    '#^composer\.json$#',
    '#^composer\.lock$#',
];

$forbiddenChanged = [];

foreach ($changedFiles as $file) {
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $file) === 1) {
            $forbiddenChanged[] = $file;
            break;
        }
    }
}

cap_check($checks, 'git status read succeeds', $statusCode === 0);
cap_check($checks, 'no forbidden runtime/source paths changed', $forbiddenChanged === [], implode(', ', array_unique($forbiddenChanged)));

$passed = array_reduce($checks, fn (bool $carry, array $check): bool => $carry && $check[1], true);

echo $passed ? 'PASS'.PHP_EOL : 'FAIL'.PHP_EOL;

exit($passed ? 0 : 1);
