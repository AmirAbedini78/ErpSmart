<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$resourceFile = $root.'/modules/Warehouse/app/Http/Resources/WarehouseResource.php';
$warehouseResourceFile = $root.'/modules/Warehouse/app/Resources/Warehouse.php';
$coreJsonResourceFile = $root.'/modules/Core/app/Resource/JsonResource.php';
$historyFile = $root.'/docs/ai/04-docops/history/2026-06-29-warehouse-resource-core-json-contract-deterministic-fix.md';

function read_file(string $file): string
{
    return file_exists($file) ? file_get_contents($file) : '';
}

function line(string $name, bool $ok): void
{
    printf("%-62s : %s\n", $name, $ok ? 'true' : 'false');
}

$resource = read_file($resourceFile);
$warehouseResource = read_file($warehouseResourceFile);
$core = read_file($coreJsonResourceFile);

line('warehouse_json_resource_exists', file_exists($resourceFile));
line('imports_core_resource_json_resource', str_contains($resource, 'use Modules\Core\Resource\JsonResource;'));
line('does_not_import_laravel_plain_json_resource', ! str_contains($resource, 'use Illuminate\Http\Resources\Json\JsonResource;'));
line('class_extends_json_resource_alias', (bool) preg_match('/class\s+WarehouseResource\s+extends\s+JsonResource\b/', $resource));
line('warehouse_json_resource_has_to_array', (bool) preg_match('/function\s+toArray\s*\(/', $resource));
line('warehouse_json_resource_has_display_name', str_contains($resource, "'display_name'"));
line('warehouse_json_resource_has_media', str_contains($resource, "'media'") && str_contains($resource, "'media_count'"));
line('warehouse_json_resource_has_activity_badge_count', str_contains($resource, "'incomplete_activities_for_user_count'"));
line('core_json_resource_file_exists', file_exists($coreJsonResourceFile));
line('core_json_resource_has_with_actions', str_contains($core, 'function withActions'));
line('warehouse_resource_imports_json_resource', str_contains($warehouseResource, 'use Modules\Warehouse\Http\Resources\WarehouseResource;'));
line('warehouse_resource_has_json_resource_method', (bool) preg_match('/public\s+function\s+jsonResource\s*\(\s*\)\s*:\s*string/', $warehouseResource));
line('warehouse_resource_returns_warehouse_json_resource', str_contains($warehouseResource, 'return WarehouseResource::class;'));
line('history_note_exists', file_exists($historyFile));
