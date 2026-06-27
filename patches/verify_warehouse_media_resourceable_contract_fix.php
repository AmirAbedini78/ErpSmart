<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$record = \Modules\Warehouse\Models\Warehouse::find(13) ?: \Modules\Warehouse\Models\Warehouse::query()->first();
$resource = \Modules\Core\Facades\Innoclapps::resourceByName('warehouses');

$checks = [
    'resource_exists' => $resource !== null,
    'resource_is_mediable' => $resource instanceof \Modules\Core\Contracts\Resources\Mediable,
    'record_exists' => $record !== null,
    'model_implements_resourceable_contract' => $record instanceof \Modules\Core\Contracts\Resources\Resourceable,
    'model_uses_resourceable_trait' => $record ? in_array(\Modules\Core\Resource\Resourceable::class, class_uses_recursive($record), true) : false,
    'model_has_media_relation' => $record ? method_exists($record, 'media') : false,
    'model_get_media_directory' => $record ? $record->getMediaDirectory() : null,
    'model_get_media_tags' => $record ? $record->getMediaTags() : null,
];

foreach ($checks as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } elseif ($value === null) {
        $value = 'null';
    }

    echo str_pad($key, 46).' : '.$value.PHP_EOL;
}
