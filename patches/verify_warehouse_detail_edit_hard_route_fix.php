<?php

$view = base_path('modules/Warehouse/resources/js/views/WarehousesView.vue');
$routes = base_path('modules/Warehouse/resources/js/routes.js');
$viewContent = is_file($view) ? file_get_contents($view) : '';
$routesContent = is_file($routes) ? file_get_contents($routes) : '';

$checks = [
    'view_exists' => is_file($view),
    'routes_exists' => is_file($routes),
    'no_inline_edit_import' => ! str_contains($viewContent, "import WarehousesEdit"),
    'no_inline_edit_teleport' => ! str_contains($viewContent, '<Teleport to="body">'),
    'navbar_edit_goes_to_page' => str_contains($viewContent, 'data-warehouse-edit-button="navbar"') && str_contains($viewContent, '@click.stop.prevent="goToEditPage"'),
    'body_edit_goes_to_page' => str_contains($viewContent, 'data-warehouse-edit-button="body"') && str_contains($viewContent, '@click.stop.prevent="goToEditPage"'),
    'back_uses_window_location' => str_contains($viewContent, 'function goBackToIndex()') && str_contains($viewContent, 'window.location.assign(`/${resourceName}`)'),
    'edit_uses_window_location' => str_contains($viewContent, 'function goToEditPage()') && str_contains($viewContent, 'window.location.assign(`/${resourceName}/${warehouseId.value}/edit`)'),
    'translation_helper_exists' => str_contains($viewContent, 'function normalizeTranslationText('),
    'routes_has_top_level_edit' => str_contains($routesContent, "path: '/warehouses/:id/edit'") && str_contains($routesContent, "name: 'warehouses.edit'"),
    'routes_edit_before_view' => strpos($routesContent, "path: '/warehouses/:id/edit'") !== false && strpos($routesContent, "path: '/warehouses/:id'") !== false && strpos($routesContent, "path: '/warehouses/:id/edit'") < strpos($routesContent, "path: '/warehouses/:id'"),
    'routes_index_after_view' => strpos($routesContent, "path: '/warehouses'") !== false && strpos($routesContent, "path: '/warehouses/:id'") !== false && strrpos($routesContent, "path: '/warehouses'") > strpos($routesContent, "path: '/warehouses/:id'"),
];

foreach ($checks as $name => $passed) {
    echo str_pad($name, 34).' : '.($passed ? 'true' : 'false').PHP_EOL;
}

exit(in_array(false, $checks, true) ? 1 : 0);
