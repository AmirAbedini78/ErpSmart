<?php

declare(strict_types=1);

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

require base_path('vendor/autoload.php');
$app = require base_path('bootstrap/app.php');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$warehouseId = isset($argv[1]) ? (int) $argv[1] : 0;
$activityId = isset($argv[2]) ? (int) $argv[2] : 0;

if ($warehouseId <= 0 || $activityId <= 0) {
    fwrite(STDERR, "Usage: php patches/repair_warehouse_activity_link.php <warehouse_id> <activity_id>\n");
    exit(1);
}

$warehouse = Modules\Warehouse\Models\Warehouse::find($warehouseId);
$activity = Modules\Activities\Models\Activity::find($activityId);

if (! $warehouse) {
    fwrite(STDERR, "Warehouse not found: {$warehouseId}\n");
    exit(1);
}

if (! $activity) {
    fwrite(STDERR, "Activity not found: {$activityId}\n");
    exit(1);
}

$activity->warehouses()->syncWithoutDetaching([$warehouse->getKey()]);

echo "Linked activity {$activityId} to warehouse {$warehouseId}.\n";
echo "Warehouse activities count: ".$warehouse->fresh()->activities()->count()."\n";
echo "Activity warehouses count: ".$activity->fresh()->warehouses()->count()."\n";
