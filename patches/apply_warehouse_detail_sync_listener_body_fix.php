<?php
/**
 * Canonicalize the Warehouse detail listener body for Core floating modal updates.
 *
 * Previous patch fixed the import/export name, but the handler body may still be
 * non-canonical or partially rewritten. This patch rewrites only the listener
 * helper block so the detail page immediately syncs after a floating edit.
 */

$root = dirname(__DIR__);
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';
$corePath = $root.'/modules/Core/resources/js/composables/useGlobalEventListener.js';

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function read_file_or_fail(string $path): string
{
    if (! is_file($path)) {
        fail("Missing file: {$path}");
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fail("Cannot read file: {$path}");
    }

    return $content;
}

function write_file_or_fail(string $path, string $content): void
{
    $dir = dirname($path);

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (file_put_contents($path, $content) === false) {
        fail("Cannot write file: {$path}");
    }
}

function backup_file(string $path): void
{
    $backup = $path.'.bak-'.date('YmdHis');

    if (! copy($path, $backup)) {
        fail("Cannot create backup: {$backup}");
    }
}

function detect_listener_export(string $core): array
{
    $candidates = [
        'useGlobalEventListener',
        'onGlobalEvent',
        'onGlobalEventListener',
        'listenGlobalEvent',
        'useEventListener',
    ];

    foreach ($candidates as $name) {
        if (preg_match('/export\s+(?:function|const)\s+'.preg_quote($name, '/').'\b/', $core)) {
            return ['type' => 'named', 'name' => $name];
        }

        if (preg_match('/export\s*\{[^}]*\b'.preg_quote($name, '/').'\b[^}]*\}/s', $core)) {
            return ['type' => 'named', 'name' => $name];
        }
    }

    if (preg_match('/export\s+default\s+(?:function\s+)?([A-Za-z_$][\w$]*)?/m', $core, $match)) {
        $name = $match[1] ?? 'useGlobalEventListener';

        return ['type' => 'default', 'name' => $name !== '' ? $name : 'useGlobalEventListener'];
    }

    fail('Could not detect listener export from useGlobalEventListener.js');
}

$core = read_file_or_fail($corePath);
$view = read_file_or_fail($viewPath);
backup_file($viewPath);

$detected = detect_listener_export($core);
$listenerName = $detected['name'];

// Remove the previously broken import if it still exists.
$view = preg_replace(
    "/^import\s+\{\s*onGlobal\s*\}\s+from\s+['\"]@\/Core\/composables\/useGlobalEventListener['\"]\s*\n/m",
    '',
    $view
);

// Ensure the actual project-local listener import exists.
$namedImport = "import { {$listenerName} } from '@/Core/composables/useGlobalEventListener'";
$defaultImport = "import {$listenerName} from '@/Core/composables/useGlobalEventListener'";
$hasListenerImport = str_contains($view, $namedImport) || str_contains($view, $defaultImport);

if (! $hasListenerImport) {
    $importLine = $detected['type'] === 'named' ? $namedImport."\n" : $defaultImport."\n";
    $floatingImport = "import { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'\n";
    $fieldsImport = "import { useResourceFields } from '@/Core/composables/useResourceFields'\n";

    if (str_contains($view, $floatingImport)) {
        $view = str_replace($floatingImport, $floatingImport.$importLine, $view);
    } elseif (str_contains($view, $fieldsImport)) {
        $view = str_replace($fieldsImport, $fieldsImport.$importLine, $view);
    } else {
        fail('Could not find a safe import insertion point in WarehousesView.vue');
    }
}

$canonicalBlock = <<<VUE

function isSameWarehouseFloatingUpdate({
  resourceName: updatedResourceName,
  resourceId,
} = {}) {
  return (
    updatedResourceName === resourceName &&
    String(resourceId) === String(warehouseId.value)
  )
}

async function refreshWarehouseDetailAfterFloatingUpdate({
  resourceName: updatedResourceName,
  resourceId,
  resource: updatedResource,
} = {}) {
  if (updatedResourceName !== resourceName) {
    return
  }

  if (String(resourceId) !== String(warehouseId.value)) {
    return
  }

  if (updatedResource) {
    synchronizeResource(normalizeResource(updatedResource), true)
    setResource(resource)
  }

  try {
    await fetchResource()
    normalizeCurrentResource()
    setResource(resource)
  } catch (error) {
    console.error('Failed to refresh warehouse detail after floating edit.', error)
  }
}

{$listenerName}('floating-resource-updated', refreshWarehouseDetailAfterFloatingUpdate)
VUE;

// Remove any earlier generated sync block and replace it with the canonical one.
$blockPattern = '/\nfunction\s+isSameWarehouseFloatingUpdate\s*\([\s\S]*?\n[A-Za-z_$][\w$]*\s*\(\s*[\'\"]floating-resource-updated[\'\"]\s*,\s*refreshWarehouseDetailAfterFloatingUpdate\s*\)\s*\n?/m';

if (preg_match($blockPattern, $view)) {
    $view = preg_replace($blockPattern, $canonicalBlock."\n", $view, 1);
} else {
    // Remove a direct listener call, if a partial patch left one behind.
    $view = preg_replace(
        '/\n[A-Za-z_$][\w$]*\s*\(\s*[\'\"]floating-resource-updated[\'\"]\s*,\s*refreshWarehouseDetailAfterFloatingUpdate\s*\)\s*\n?/m',
        "\n",
        $view
    );

    $openEditPattern = <<<'VUE'
function openEditFloatingModal() {
  floatResourceInEditMode({
    resourceName,
    resourceId: Number(warehouseId.value) || warehouseId.value,
  })
}
VUE;

    if (! str_contains($view, $openEditPattern)) {
        fail('Could not locate openEditFloatingModal block for listener insertion.');
    }

    $view = str_replace($openEditPattern, $openEditPattern.$canonicalBlock, $view);
}

// Keep the UI typo cleanup from the previous patch.
$view = str_replace('sm:flex-rowsm:items-start', 'sm:flex-row sm:items-start', $view);

write_file_or_fail($viewPath, $view);

$historyPath = $root.'/docs/ai/04-docops/history/2026-06-28-warehouse-detail-sync-listener-body-fix.md';
$history = <<<'MD'
# Warehouse Detail Sync Listener Body Fix

Status: applied

The Warehouse detail page listener for `floating-resource-updated` was canonicalized after the import/export helper was corrected.

The listener now:

1. Checks that the updated resource name matches `warehouses`.
2. Checks that the updated resource id matches the current detail route id.
3. Synchronizes the returned payload resource immediately.
4. Fetches the latest detail record and rehydrates detail fields.

This is the canonical post-floating-edit detail synchronization behavior for future CRM-style modules.
MD;
write_file_or_fail($historyPath, $history."\n");

echo "Warehouse detail sync listener body fix applied.\n";
echo "Detected listener export: {$listenerName} ({$detected['type']})\n";
