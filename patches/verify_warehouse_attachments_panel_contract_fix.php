<?php
$viewPath = 'modules/Warehouse/resources/js/views/WarehousesView.vue';
$view = is_file($viewPath) ? file_get_contents($viewPath) : '';

$checks = [
    'view_exists' => is_file($viewPath),
    'has_resource_media_panel' => str_contains($view, '<ResourceMediaPanel'),
    'passes_panel_prop' => str_contains($view, ':panel="attachmentsPanel"'),
    'has_panel_metadata' => str_contains($view, 'const attachmentsPanel = computed('),
    'provides_record' => str_contains($view, "provide('record', safeResource)"),
    'provides_resource' => str_contains($view, "provide('resource', safeResource)"),
    'guards_until_id' => str_contains($view, 'v-if="safeResource.id"'),
    'normalizes_media' => str_contains($view, 'media: Array.isArray(value.media) ? value.media : []'),
];

foreach ($checks as $name => $ok) {
    echo str_pad($name, 30).' : '.($ok ? 'true' : 'false').PHP_EOL;
}

exit(in_array(false, $checks, true) ? 1 : 0);
