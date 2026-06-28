<?php
/**
 * Fix Warehouse detail sync listener import/call after floating edit.
 *
 * The previous patch assumed `onGlobal` is exported from
 * modules/Core/resources/js/composables/useGlobalEventListener.js.
 * This script detects the real export in the local project and rewrites
 * WarehousesView.vue to use it.
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
        fail("File not found: {$path}");
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fail("Cannot read file: {$path}");
    }

    return $content;
}

function write_file_or_fail(string $path, string $content): void
{
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

function detect_global_listener_export(string $source): array
{
    $candidates = [
        'useGlobalEventListener',
        'onGlobalEvent',
        'onGlobalEventListener',
        'listenGlobalEvent',
        'useEventListener',
    ];

    foreach ($candidates as $name) {
        if (preg_match('/export\s+(?:function|const)\s+'.preg_quote($name, '/').'\b/', $source)) {
            return ['type' => 'named', 'name' => $name];
        }

        if (preg_match('/export\s*\{[^}]*\b'.preg_quote($name, '/').'\b[^}]*\}/s', $source)) {
            return ['type' => 'named', 'name' => $name];
        }
    }

    if (preg_match('/export\s+default\s+(?:function\s+)?([A-Za-z_$][\w$]*)?/m', $source, $match)) {
        $name = $match[1] ?? 'useGlobalEventListener';
        $name = $name !== '' ? $name : 'useGlobalEventListener';

        return ['type' => 'default', 'name' => $name];
    }

    fail('Could not detect export from '.$GLOBALS['corePath']);
}

$core = read_file_or_fail($corePath);
$view = read_file_or_fail($viewPath);
backup_file($viewPath);

$detected = detect_global_listener_export($core);
$listenerName = $detected['name'];

// Remove the incorrect import first.
$view = preg_replace(
    "/^import\s+\{\s*onGlobal\s*\}\s+from\s+['\"]@\/Core\/composables\/useGlobalEventListener['\"]\s*\n/m",
    '',
    $view
);

// Remove any previous import of the same composable to avoid duplicates.
$view = preg_replace(
    "/^import\s+\{\s*".preg_quote($listenerName, '/')."\s*\}\s+from\s+['\"]@\/Core\/composables\/useGlobalEventListener['\"]\s*\n/m",
    '',
    $view
);
$view = preg_replace(
    "/^import\s+".preg_quote($listenerName, '/')."\s+from\s+['\"]@\/Core\/composables\/useGlobalEventListener['\"]\s*\n/m",
    '',
    $view
);

$importLine = $detected['type'] === 'named'
    ? "import { {$listenerName} } from '@/Core/composables/useGlobalEventListener'\n"
    : "import {$listenerName} from '@/Core/composables/useGlobalEventListener'\n";

// Insert after floating modal import, or after useResourceFields import as fallback.
if (str_contains($view, "import { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'\n")) {
    $view = str_replace(
        "import { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'\n",
        "import { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'\n".$importLine,
        $view
    );
} elseif (str_contains($view, "import { useResourceFields } from '@/Core/composables/useResourceFields'\n")) {
    $view = str_replace(
        "import { useResourceFields } from '@/Core/composables/useResourceFields'\n",
        "import { useResourceFields } from '@/Core/composables/useResourceFields'\n".$importLine,
        $view
    );
} else {
    fail('Could not find a safe insertion point for useGlobalEventListener import.');
}

// Rewrite the call name. Keep handler body intact.
$view = preg_replace('/\bonGlobal\s*\(/', $listenerName.'(', $view);

// In case the previous patch added an old alias, normalize it too.
foreach (['onGlobalEvent', 'onGlobalEventListener', 'listenGlobalEvent', 'useEventListener', 'useGlobalEventListener'] as $candidate) {
    if ($candidate !== $listenerName) {
        // Only rewrite calls, not imports or identifiers in text.
        $view = preg_replace('/\b'.preg_quote($candidate, '/').'\s*\(\s*[\'\"]floating-resource-updated[\'\"]/', $listenerName.'(\'floating-resource-updated\'', $view);
    }
}

write_file_or_fail($viewPath, $view);

// Add a small history note without making docs the blocker for runtime.
$historyPath = $root.'/docs/ai/04-docops/history/2026-06-28-warehouse-sync-global-event-export-fix.md';
@mkdir(dirname($historyPath), 0775, true);
$history = <<<'MD'
# Warehouse Sync Global Event Export Fix

Status: applied

The Warehouse detail view listens to the first-party `floating-resource-updated` event so a successful Floating Resource Modal edit refreshes the current detail record without a browser reload.

A previous patch assumed `onGlobal` was exported by `modules/Core/resources/js/composables/useGlobalEventListener.js`; the local project does not export `onGlobal`. This fix detects the actual exported listener composable and rewrites `WarehousesView.vue` to use the project-local export.

Canonical rule for future modules:

- Do not invent event helper names.
- Reuse the actual exported helper from `useGlobalEventListener.js`.
- For CRM-style resources, use `Action::make()->floatResourceInEditMode()` and listen for `floating-resource-updated` on detail pages that must sync immediately after edit.
MD;
write_file_or_fail($historyPath, $history."\n");

echo "Warehouse sync global event export fix applied.\n";
echo "Detected listener export: {$listenerName} ({$detected['type']})\n";
