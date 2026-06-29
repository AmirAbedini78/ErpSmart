<?php

$root = dirname(__DIR__);

function read_file(string $relative): string
{
    global $root;
    $path = $root.'/'.$relative;

    return is_file($path) ? file_get_contents($path) : '';
}

$warehouseResource = read_file('modules/Warehouse/app/Resources/Warehouse.php');
$warehouseJsonResource = read_file('modules/Warehouse/app/Http/Resources/WarehouseResource.php');
$activityModel = read_file('modules/Activities/app/Models/Activity.php');
$history = read_file('docs/ai/04-docops/history/2026-06-28-warehouse-activity-associations-json-resource-fix.md');

$checks = [
    'warehouse_json_resource_exists' => $warehouseJsonResource !== '',
    'warehouse_json_resource_extends_core_json_resource' => str_contains($warehouseJsonResource, 'extends JsonResource'),
    'warehouse_json_resource_has_display_name' => str_contains($warehouseJsonResource, "'display_name'") && str_contains($warehouseJsonResource, "'path' => '/warehouses/'"),
    'warehouse_resource_imports_json_resource' => str_contains($warehouseResource, 'WarehouseResource as WarehouseJsonResource'),
    'warehouse_resource_has_json_resource_method' => str_contains($warehouseResource, 'public function jsonResource(): string'),
    'warehouse_resource_returns_json_resource_class' => str_contains($warehouseResource, 'return WarehouseJsonResource::class;'),
    'activity_touch_relations_include_warehouses' => str_contains($activityModel, "return ['contacts', 'companies', 'deals', 'warehouses'];"),
    'activity_purge_detaches_warehouses' => str_contains($activityModel, "foreach (['contacts', 'companies', 'deals', 'warehouses'] as $".'relation)'),
    'activity_next_activity_cleanup_does_not_include_warehouse' => ! str_contains($activityModel, 'Warehouse::class') && ! str_contains($activityModel, 'WarehouseModel::class'),
    'history_note_exists' => str_contains($history, 'Warehouse Activity Associations JSON Resource Fix'),
];

foreach ($checks as $name => $ok) {
    echo str_pad($name, 55).': '.($ok ? 'true' : 'false').PHP_EOL;
}

if (in_array(false, $checks, true)) {
    exit(1);
}
