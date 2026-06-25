<?php
/**
 * Verify Warehouse Attachments tab frontend markers.
 * Run from project root:
 *   docker compose exec app php patches/verify_warehouse_attachments_tab_force_fix.php
 */

$viewPath = 'modules/Warehouse/resources/js/views/WarehousesView.vue';
$view = is_file($viewPath) ? file_get_contents($viewPath) : '';

$result = [
    'view_exists' => is_file($viewPath),
    'imports_resource_media_panel' => str_contains($view, "ResourceMediaPanel from '@/Core/components/Resource/ResourceMediaPanel.vue'"),
    'has_attachments_label' => str_contains($view, "core::app.attachments"),
    'renders_resource_media_panel' => str_contains($view, '<ResourceMediaPanel'),
    'normalizes_media' => str_contains($view, 'media: Array.isArray(value.media) ? value.media : []'),
];

foreach ($result as $key => $value) {
    echo str_pad($key, 34).': '.($value ? 'true' : 'false').PHP_EOL;
}

exit(in_array(false, $result, true) ? 1 : 0);
