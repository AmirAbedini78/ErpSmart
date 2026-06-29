<?php

$root = dirname(__DIR__);
$resourcePath = $root.'/modules/Warehouse/app/Resources/Warehouse.php';
$jsonResourcePath = $root.'/modules/Warehouse/app/Http/Resources/WarehouseResource.php';
$coreJsonResourcePath = $root.'/modules/Core/app/Resource/JsonResource.php';
$historyPath = $root.'/docs/ai/04-docops/history/2026-06-29-warehouse-detail-core-json-resource-contract-final-fix.md';

function check(string $name, bool $result): void
{
    echo str_pad($name, 66).' : '.($result ? 'true' : 'false').PHP_EOL;
}

$resource = is_file($resourcePath) ? file_get_contents($resourcePath) : '';
$json = is_file($jsonResourcePath) ? file_get_contents($jsonResourcePath) : '';
$coreJson = is_file($coreJsonResourcePath) ? file_get_contents($coreJsonResourcePath) : '';

check('warehouse_resource_file_exists', is_file($resourcePath));
check('warehouse_json_resource_file_exists', is_file($jsonResourcePath));
check('warehouse_resource_imports_http_json_resource', str_contains($resource, 'use Modules\\Warehouse\\Http\\Resources\\WarehouseResource;'));
check('warehouse_resource_does_not_use_alias_import', ! str_contains($resource, 'WarehouseResource as WarehouseJsonResource'));
check('warehouse_resource_json_resource_method_exists', str_contains($resource, 'public function jsonResource(): string'));
check('warehouse_resource_returns_imported_resource', str_contains($resource, 'return WarehouseResource::class;'));
check('warehouse_resource_does_not_return_alias', ! str_contains($resource, 'WarehouseJsonResource::class'));
check('warehouse_json_resource_imports_core_resource', str_contains($json, 'use Modules\\Core\\Resource\\JsonResource;'));
check('warehouse_json_resource_extends_core_alias', str_contains($json, 'class WarehouseResource extends JsonResource'));
check('warehouse_json_resource_calls_with_common_data', str_contains($json, 'return $this->withCommonData(['));
check('warehouse_json_resource_has_media_payload', str_contains($json, "'media' =>"));
check('warehouse_json_resource_has_activity_badge_payload', str_contains($json, 'incomplete_activities_for_user_count'));
check('core_json_resource_file_exists', is_file($coreJsonResourcePath));
check('core_json_resource_has_with_actions', str_contains($coreJson, 'function withActions'));
check('history_note_exists', is_file($historyPath));

$autoload = $root.'/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    $warehouseResourceClass = 'Modules\\Warehouse\\Http\\Resources\\WarehouseResource';
    $coreResourceClass = 'Modules\\Core\\Resource\\JsonResource';

    check('autoload_warehouse_json_resource_class_exists', class_exists($warehouseResourceClass));
    check('autoload_extends_core_json_resource', class_exists($warehouseResourceClass) && is_subclass_of($warehouseResourceClass, $coreResourceClass));
    check('autoload_with_actions_available', class_exists($warehouseResourceClass) && method_exists($warehouseResourceClass, 'withActions'));
}
