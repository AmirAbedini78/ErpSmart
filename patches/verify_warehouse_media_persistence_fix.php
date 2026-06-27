<?php
/**
 * Verify Warehouse Media persistence contract.
 *
 * Run from project root:
 *   docker compose exec app php patches/verify_warehouse_media_persistence_fix.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$resource = Modules\Core\Facades\Innoclapps::resourceByName('warehouses');
$record = Modules\Warehouse\Models\Warehouse::query()->latest('id')->first();

$result = [
    'resource_exists' => $resource !== null,
    'resource_is_mediable' => $resource instanceof Modules\Core\Contracts\Resources\Mediable,
    'record_exists' => $record !== null,
    'model_uses_eager_media' => in_array('media', (new Modules\Warehouse\Models\Warehouse)->getWith(), true),
    'record_relation_loaded_media' => $record ? $record->relationLoaded('media') : false,
    'record_media_count' => $record ? $record->media->count() : null,
    'serialized_has_media_key' => $record ? array_key_exists('media', $record->toArray()) : false,
];

foreach ($result as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } elseif ($value === null) {
        $value = 'null';
    }

    echo str_pad($key, 34).' : '.$value.PHP_EOL;
}
