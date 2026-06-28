<?php
$root = dirname(__DIR__);
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';
$corePath = $root.'/modules/Core/resources/js/composables/useGlobalEventListener.js';

function check(string $name, bool $ok): void
{
    printf("%-48s : %s\n", $name, $ok ? 'true' : 'false');
}

$view = is_file($viewPath) ? file_get_contents($viewPath) : '';
$core = is_file($corePath) ? file_get_contents($corePath) : '';

$candidates = [
    'useGlobalEventListener',
    'onGlobalEvent',
    'onGlobalEventListener',
    'listenGlobalEvent',
    'useEventListener',
];

$detected = null;
foreach ($candidates as $candidate) {
    if (preg_match('/export\s+(?:function|const)\s+'.preg_quote($candidate, '/').'\b/', $core) ||
        preg_match('/export\s*\{[^}]*\b'.preg_quote($candidate, '/').'\b[^}]*\}/s', $core)) {
        $detected = ['type' => 'named', 'name' => $candidate];
        break;
    }
}

if (! $detected && preg_match('/export\s+default\s+(?:function\s+)?([A-Za-z_$][\w$]*)?/m', $core, $match)) {
    $name = $match[1] ?? 'useGlobalEventListener';
    $detected = ['type' => 'default', 'name' => $name !== '' ? $name : 'useGlobalEventListener'];
}

$listenerName = $detected['name'] ?? null;
$expectedNamedImport = $listenerName ? "import { {$listenerName} } from '@/Core/composables/useGlobalEventListener'" : '';
$expectedDefaultImport = $listenerName ? "import {$listenerName} from '@/Core/composables/useGlobalEventListener'" : '';

check('core_global_listener_file_exists', is_file($corePath));
check('view_exists', is_file($viewPath));
check('detected_listener_export', (bool) $listenerName);
check('no_bad_on_global_import', ! str_contains($view, 'import { onGlobal }'));
check('uses_detected_listener_import', $listenerName && (str_contains($view, $expectedNamedImport) || str_contains($view, $expectedDefaultImport)));
check('no_on_global_call', ! preg_match('/\bonGlobal\s*\(/', $view));
check('listens_to_floating_resource_updated', str_contains($view, "'floating-resource-updated'") || str_contains($view, '"floating-resource-updated"'));
check('uses_detected_listener_call', $listenerName && preg_match('/\b'.preg_quote($listenerName, '/').'\s*\(\s*[\'\"]floating-resource-updated[\'\"]/', $view));
check('matches_same_resource_name', str_contains($view, 'updatedResourceName !== resourceName'));
check('matches_same_resource_id', str_contains($view, 'String(resourceId) !== String(warehouseId.value)'));
check('syncs_payload_resource', str_contains($view, 'synchronizeResource(updatedResource'));
check('fetches_after_floating_update', str_contains($view, 'await fetchResource()'));
check('history_note_exists', is_file($root.'/docs/ai/04-docops/history/2026-06-28-warehouse-sync-global-event-export-fix.md'));
