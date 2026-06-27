<?php
/**
 * Verify Warehouse Attachments delete action frontend contract.
 *
 * Run from project root:
 *   docker compose exec app php patches/verify_warehouse_attachments_delete_action_fix.php
 */

$viewPath = 'modules/Warehouse/resources/js/views/WarehousesView.vue';
$view = is_file($viewPath) ? file_get_contents($viewPath) : '';

$result = [
    'view_exists' => is_file($viewPath),
    'has_resource_media_panel' => str_contains($view, 'ResourceMediaPanel'),
    'record_update_defaults_true' => str_contains($view, 'update: true'),
    'media_items_authorized_delete' => str_contains($view, 'media.authorizations || {}') && str_contains($view, 'delete: true'),
    'still_guards_media_panel' => str_contains($view, 'v-if="safeResource.id"'),
    'still_passes_panel_prop' => str_contains($view, ':panel="attachmentsPanel"'),
];

foreach ($result as $key => $value) {
    echo str_pad($key, 32).' : '.($value ? 'true' : 'false').PHP_EOL;
}
