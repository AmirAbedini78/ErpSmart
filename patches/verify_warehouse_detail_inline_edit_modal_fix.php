<?php

$root = dirname(__DIR__);
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';
$editPath = $root.'/modules/Warehouse/resources/js/views/WarehousesEdit.vue';

$view = file_exists($viewPath) ? file_get_contents($viewPath) : '';
$edit = file_exists($editPath) ? file_get_contents($editPath) : '';

$checks = [
    'view_exists' => file_exists($viewPath),
    'edit_exists' => file_exists($editPath),
    'view_imports_warehouses_edit' => str_contains($view, "import WarehousesEdit from './WarehousesEdit.vue'"),
    'view_has_inline_edit_state' => str_contains($view, 'showInlineEditForm'),
    'view_button_opens_inline_edit' => str_contains($view, '@click.prevent="openInlineEditForm"'),
    'view_renders_warehouses_edit' => str_contains($view, '<WarehousesEdit'),
    'edit_accepts_record_id' => str_contains($edit, 'recordId'),
    'edit_accepts_redirect_on_close' => str_contains($edit, 'redirectOnClose'),
    'edit_uses_current_id' => str_contains($edit, 'currentWarehouseId'),
    'edit_emits_closed' => str_contains($edit, "emit('closed')"),
];

foreach ($checks as $name => $passed) {
    echo str_pad($name, 36).' : '.($passed ? 'true' : 'false').PHP_EOL;
}
