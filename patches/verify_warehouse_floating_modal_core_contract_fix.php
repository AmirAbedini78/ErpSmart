<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$componentPath = $root.'/modules/Warehouse/resources/js/components/WarehouseFloatingModal.vue';
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';
$appPath = $root.'/modules/Warehouse/resources/js/app.js';
$resourcePath = $root.'/modules/Warehouse/app/Resources/Warehouse.php';

$component = is_file($componentPath) ? file_get_contents($componentPath) : '';
$view = is_file($viewPath) ? file_get_contents($viewPath) : '';
$app = is_file($appPath) ? file_get_contents($appPath) : '';
$resource = is_file($resourcePath) ? file_get_contents($resourcePath) : '';

$checks = [
    'floating_component_exists' => is_file($componentPath),
    'component_named_warehouse_floating_modal' => str_contains($component, "defineOptions({ name: 'WarehouseFloatingModal' })"),
    'accepts_visible_prop' => str_contains($component, 'visible: { type: Boolean'),
    'accepts_floating_ready_prop' => str_contains($component, 'floatingReady: { type: Boolean'),
    'accepts_resource_prop' => str_contains($component, 'resource: { type: Object'),
    'accepts_fields_prop' => str_contains($component, 'fields: { type: Array'),
    'accepts_mode_prop' => str_contains($component, 'mode: { type: String'),
    'accepts_update_handler_prop' => str_contains($component, 'updateHandler: { type: Function'),
    'does_not_require_resource_id_prop' => ! str_contains($component, 'resourceId:'),
    'uses_core_update_handler' => str_contains($component, 'props.updateHandler(form)'),
    'uses_core_visible_model' => str_contains($component, "@update:visible=\"$emit('update:visible', $event)\""),
    'uses_form_fields_with_core_fields' => str_contains($component, ':fields="fields"') && str_contains($component, ':resource-id="resource.id"'),
    'app_registers_component' => str_contains($app, "app.component('WarehouseFloatingModal', WarehouseFloatingModal)"),
    'view_calls_float_edit_mode' => str_contains($view, 'floatResourceInEditMode({'),
    'view_uses_open_edit_floating_modal' => str_contains($view, '@click="openEditFloatingModal"'),
    'view_class_typo_fixed' => ! str_contains($view, 'sm:flex-rowsm:items-start'),
    'resource_has_float_edit_action' => str_contains($resource, 'floatResourceInEditMode'),
];

foreach ($checks as $name => $passed) {
    echo str_pad($name, 42).' : '.($passed ? 'true' : 'false').PHP_EOL;
}

exit(in_array(false, $checks, true) ? 1 : 0);
