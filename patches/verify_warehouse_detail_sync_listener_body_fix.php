<?php
$root = dirname(__DIR__);
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';
$corePath = $root.'/modules/Core/resources/js/composables/useGlobalEventListener.js';

function check(string $name, bool $ok): void
{
    printf("%-52s : %s\n", $name, $ok ? 'true' : 'false');
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

$listenerName = null;
foreach ($candidates as $candidate) {
    if (preg_match('/export\s+(?:function|const)\s+'.preg_quote($candidate, '/').'\b/', $core) ||
        preg_match('/export\s*\{[^}]*\b'.preg_quote($candidate, '/').'\b[^}]*\}/s', $core)) {
        $listenerName = $candidate;
        break;
    }
}

if (! $listenerName && preg_match('/export\s+default\s+(?:function\s+)?([A-Za-z_$][\w$]*)?/m', $core, $match)) {
    $listenerName = ($match[1] ?? '') !== '' ? $match[1] : 'useGlobalEventListener';
}

$expectedNamedImport = $listenerName ? "import { {$listenerName} } from '@/Core/composables/useGlobalEventListener'" : '';
$expectedDefaultImport = $listenerName ? "import {$listenerName} from '@/Core/composables/useGlobalEventListener'" : '';

check('core_listener_file_exists', is_file($corePath));
check('view_exists', is_file($viewPath));
check('detected_listener_export', (bool) $listenerName);
check('no_bad_on_global_import', ! str_contains($view, 'import { onGlobal }'));
check('uses_detected_listener_import', $listenerName && (str_contains($view, $expectedNamedImport) || str_contains($view, $expectedDefaultImport)));
check('no_on_global_call', ! preg_match('/\bonGlobal\s*\(/', $view));
check('listens_to_floating_resource_updated', str_contains($view, "'floating-resource-updated'") || str_contains($view, '"floating-resource-updated"'));
check('uses_detected_listener_call', $listenerName && preg_match('/\b'.preg_quote($listenerName, '/').'\s*\(\s*[\'\"]floating-resource-updated[\'\"]\s*,\s*refreshWarehouseDetailAfterFloatingUpdate/', $view));
check('has_same_update_helper', str_contains($view, 'function isSameWarehouseFloatingUpdate'));
check('matches_same_resource_name', str_contains($view, 'updatedResourceName !== resourceName') && str_contains($view, 'updatedResourceName === resourceName'));
check('matches_same_resource_id', str_contains($view, 'String(resourceId) !== String(warehouseId.value)') && str_contains($view, 'String(resourceId) === String(warehouseId.value)'));
check('syncs_payload_resource', str_contains($view, 'synchronizeResource(normalizeResource(updatedResource), true)'));
check('sets_resource_after_sync', str_contains($view, 'setResource(resource)'));
check('fetches_after_floating_update', str_contains($view, 'await fetchResource()'));
check('normalizes_after_floating_update', str_contains($view, 'normalizeCurrentResource()'));
check('view_class_typo_fixed', ! str_contains($view, 'sm:flex-rowsm:items-start'));
check('history_note_exists', is_file($root.'/docs/ai/04-docops/history/2026-06-28-warehouse-detail-sync-listener-body-fix.md'));
