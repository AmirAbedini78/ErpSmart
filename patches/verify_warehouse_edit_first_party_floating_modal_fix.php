<?php

$root = dirname(__DIR__);
function p(string $relative): string { global $root; return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative); }
function read_or_empty(string $relative): string { $path = p($relative); return file_exists($path) ? file_get_contents($path) : ''; }

$view = read_or_empty('modules/Warehouse/resources/js/views/WarehousesView.vue');
$app = read_or_empty('modules/Warehouse/resources/js/app.js');
$resource = read_or_empty('modules/Warehouse/app/Resources/Warehouse.php');
$floating = read_or_empty('modules/Warehouse/resources/js/components/WarehouseFloatingModal.vue');

$checks = [
    'floating_component_exists' => $floating !== '',
    'floating_component_named' => str_contains($floating, "name: 'WarehouseFloatingModal'"),
    'floating_uses_islideover' => str_contains($floating, '<ISlideover'),
    'floating_uses_resource_id_prop' => str_contains($floating, 'resourceId') && str_contains($floating, 'currentWarehouseId'),
    'floating_uses_update_resource' => str_contains($floating, 'updateResource(form, currentWarehouseId.value)'),
    'app_imports_floating_modal' => str_contains($app, "WarehouseFloatingModal from './components/WarehouseFloatingModal.vue'"),
    'app_registers_floating_modal' => preg_match("/app\.component\(['\"]WarehouseFloatingModal['\"]\s*,\s*WarehouseFloatingModal\)/", $app) === 1,
    'view_uses_floating_composable' => str_contains($view, "useFloatingResourceModal"),
    'view_calls_float_edit_mode' => str_contains($view, 'floatResourceInEditMode({'),
    'view_edit_button_calls_open' => str_contains($view, '@click="openEditFloatingModal"'),
    'view_back_uses_named_route' => str_contains($view, "router.push({ name: 'warehouse-index' })"),
    'view_no_warehouse_edit_import' => ! str_contains($view, "import WarehousesEdit from './WarehousesEdit.vue'"),
    'view_no_teleport_inline_edit' => ! str_contains($view, '<Teleport to="body">'),
    'view_no_window_location_hard_route' => ! str_contains($view, 'window.location'),
    'resource_imports_action' => str_contains($resource, 'use Modules\\Core\\Actions\\Action;'),
    'resource_has_float_edit_action' => str_contains($resource, 'Action::make()->floatResourceInEditMode()'),
];

foreach ($checks as $name => $ok) {
    printf("%-38s : %s\n", $name, $ok ? 'true' : 'false');
}

exit(in_array(false, $checks, true) ? 1 : 0);
