<?php
$root = dirname(__DIR__);
$resourcePath = $root.'/modules/Warehouse/app/Http/Resources/WarehouseResource.php';
$warehouseResourcePath = $root.'/modules/Warehouse/app/Resources/Warehouse.php';

function check($name, $ok) {
    printf("%-58s : %s\n", $name, $ok ? 'true' : 'false');
}

$exists = file_exists($resourcePath);
$content = $exists ? file_get_contents($resourcePath) : '';
$warehouseResource = file_exists($warehouseResourcePath) ? file_get_contents($warehouseResourcePath) : '';

check('warehouse_json_resource_exists', $exists);
check('imports_core_resource_json_resource', (bool) preg_match('/^use\s+Modules\\Core\\Resource\\JsonResource;\s*$/m', $content));
check('does_not_import_laravel_plain_json_resource', ! preg_match('/^use\s+Illuminate\\Http\\Resources\\Json\\JsonResource;\s*$/m', $content));
check('does_not_import_wrong_core_http_json_resource', ! preg_match('/^use\s+Modules\\Core\\Http\\Resources\\JsonResource;\s*$/m', $content));
check('class_extends_json_resource_alias', (bool) preg_match('/class\s+WarehouseResource\s+extends\s+JsonResource\b/', $content));
check('warehouse_resource_uses_warehouse_json_resource', str_contains($warehouseResource, 'Modules\\Warehouse\\Http\\Resources\\WarehouseResource') || str_contains($warehouseResource, 'use Modules\\Warehouse\\Http\\Resources\\WarehouseResource;'));
check('warehouse_resource_returns_warehouse_json_resource', str_contains($warehouseResource, 'return WarehouseResource::class;') || str_contains($warehouseResource, 'return \\Modules\\Warehouse\\Http\\Resources\\WarehouseResource::class;'));

$hasCoreFile = file_exists($root.'/modules/Core/app/Resource/JsonResource.php');
check('core_json_resource_file_exists', $hasCoreFile);
if ($hasCoreFile) {
    $core = file_get_contents($root.'/modules/Core/app/Resource/JsonResource.php');
    check('core_json_resource_has_with_actions', str_contains($core, 'function withActions') || str_contains($core, 'public function withActions'));
}
