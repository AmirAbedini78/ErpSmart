<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function ui_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function ui_run(string $root, string $command): array
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

function ui_read(string $root, string $path): string
{
    return is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : '';
}

$docs = [
    'docs/ai/03-architecture/current-ui-theme-system-probe.md',
    'docs/ai/03-architecture/builder-studio-ui-shell.md',
    'docs/ai/03-architecture/builder-studio-rtl-strategy.md',
    'docs/ai/04-docops/history/2026-07-01-builder-studio-ui-shell.md',
];

foreach ($docs as $doc) {
    ui_check($checks, $doc.' exists', is_file($root.'/'.$doc));
}

$docText = implode("\n", array_map(fn (string $doc): string => ui_read($root, $doc), $docs));
ui_check($checks, 'docs mention UI-first Builder', stripos($docText, 'UI-first') !== false);
ui_check($checks, 'docs mention Builder Studio', stripos($docText, 'Builder Studio') !== false);
ui_check($checks, 'docs mention Settings/Super Admin embedded entrypoint', stripos($docText, 'Settings/Super Admin') !== false);
ui_check($checks, 'docs mention same backend Builder Control Plane', stripos($docText, 'same backend Builder Control Plane') !== false);
ui_check($checks, 'docs mention SaaS deferred', stripos($docText, 'SaaS integration is deferred') !== false);
ui_check($checks, 'docs mention CLI as engineering harness only', stripos($docText, 'CLI remains an engineering harness only') !== false);
ui_check($checks, 'docs mention RTL strategy and not forcing global RTL', stripos($docText, 'Do not force global RTL') !== false);
ui_check($checks, 'docs mention theme probe findings', stripos($docText, 'Frontend Module Boot Pattern') !== false && stripos($docText, 'Component System') !== false);

$uiFiles = [
    'modules/Builder/resources/js/app.js',
    'modules/Builder/resources/js/routes.js',
    'modules/Builder/resources/js/services/builderApi.js',
    'modules/Builder/resources/js/fixtures/neutralDefinition.js',
    'modules/Builder/resources/js/views/BuilderDefinitionsIndex.vue',
    'modules/Builder/resources/js/views/BuilderDefinitionView.vue',
];

foreach ($uiFiles as $file) {
    ui_check($checks, $file.' exists', is_file($root.'/'.$file));
}

$appJs = ui_read($root, 'modules/Builder/resources/js/app.js');
$routesJs = ui_read($root, 'modules/Builder/resources/js/routes.js');
$apiJs = ui_read($root, 'modules/Builder/resources/js/services/builderApi.js');
$indexVue = ui_read($root, 'modules/Builder/resources/js/views/BuilderDefinitionsIndex.vue');
$detailVue = ui_read($root, 'modules/Builder/resources/js/views/BuilderDefinitionView.vue');
$rootApp = ui_read($root, 'resources/js/app.js');
$uiText = $appJs."\n".$routesJs."\n".$apiJs."\n".$indexVue."\n".$detailVue;

ui_check($checks, 'Builder app is imported by root app', str_contains($rootApp, "@/Builder/app.js"));
ui_check($checks, 'Builder route registration exists', str_contains($appJs, 'Innoclapps.booting') && str_contains($routesJs, '/builder/definitions'));
ui_check($checks, 'Settings entrypoint route exists', str_contains($appJs, 'software-customization') && str_contains($appJs, 'settings-software-customization'));
ui_check($checks, 'API endpoint strings exist', str_contains($apiJs, '/builder/definitions'));
ui_check($checks, 'create draft action exists', str_contains($indexVue, 'createDefinition') && str_contains($indexVue, 'neutralDefinition'));
ui_check($checks, 'raw JSON editor exists', str_contains($detailVue, 'IFormTextarea') && str_contains($detailVue, 'JSON.parse') && str_contains($detailVue, 'JSON.stringify'));
ui_check($checks, 'validate action exists', str_contains($apiJs, '/validate') && str_contains($detailVue, 'runValidation'));
ui_check($checks, 'preview action exists', str_contains($apiJs, '/preview') && str_contains($detailVue, 'runPreview'));
ui_check($checks, 'no publish action exists in Builder UI', stripos($uiText, 'publish') === false);
ui_check($checks, 'no SaaS/license/update references in Builder UI', ! preg_match('/Saas|license|licensing|Updater|update\\//i', $uiText));

[$statusCode, $statusOutput] = ui_run($root, 'git -c safe.directory='.escapeshellarg($root).' status --short');
ui_check($checks, 'git status command succeeds', $statusCode === 0, trim($statusOutput));

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
        || str_starts_with($path, 'vendor/')
        || str_starts_with($path, 'node_modules/')
        || str_starts_with($path, 'public/build/')
        || in_array($path, ['package.json', 'package-lock.json', 'composer.json', 'composer.lock'], true)
    ) {
        $forbiddenChanged[] = $line;
    }
}

ui_check($checks, 'no Warehouse runtime files changed', ! array_filter($forbiddenChanged, fn (string $line): bool => str_contains($line, 'modules/Warehouse/')));
ui_check($checks, 'no broad Core changes', ! array_filter($forbiddenChanged, fn (string $line): bool => str_contains($line, 'modules/Core/')));
ui_check($checks, 'no SaaS/license/update code changed', ! array_filter($forbiddenChanged, fn (string $line): bool => str_contains($line, 'modules/Saas/') || str_contains($line, 'modules/Updater/')));
ui_check($checks, 'no package/composer/vendor/build files changed', ! array_filter($forbiddenChanged, fn (string $line): bool => preg_match('#(vendor/|node_modules/|public/build/|package\.json|package-lock\.json|composer\.json|composer\.lock)#', $line) === 1));

$failed = array_filter($checks, fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
