<?php

use Illuminate\Support\Facades\Schema;
use Modules\Core\Facades\Innoclapps;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$resource = Innoclapps::resourceByName('warehouses');
$modelClass = $resource?::$model ?? null;
$model = $modelClass ? new $modelClass : null;

$classes = [
    'core_mediable_contract' => 'Modules\\Core\\Contracts\\Resources\\Mediable',
    'spatie_has_media' => 'Spatie\\MediaLibrary\\HasMedia',
    'spatie_interacts_with_media' => 'Spatie\\MediaLibrary\\InteractsWithMedia',
    'documents_model' => 'Modules\\Documents\\Models\\Document',
];

$result = [
    'resource_exists' => $resource !== null,
    'resource_class' => $resource ? get_class($resource) : null,
    'resource_name' => $resource?->name(),
    'resource_is_associateable' => $resource && method_exists($resource, 'isAssociateable') ? $resource->isAssociateable() : null,
    'resource_interfaces' => $resource ? class_implements($resource) : [],
    'model_class' => $modelClass,
    'model_traits' => $model ? class_uses_recursive($model) : [],
    'model_methods_probe' => $model ? [
        'notes' => method_exists($model, 'notes'),
        'documents' => method_exists($model, 'documents'),
        'media' => method_exists($model, 'media'),
        'attachments' => method_exists($model, 'attachments'),
        'files' => method_exists($model, 'files'),
    ] : [],
    'class_exists' => array_map('class_exists', $classes),
    'interface_exists' => array_map('interface_exists', $classes),
    'schema' => [
        'media_table' => Schema::hasTable('media') ? Schema::getColumnListing('media') : null,
        'documents_table' => Schema::hasTable('documents') ? Schema::getColumnListing('documents') : null,
        'documentables_table' => Schema::hasTable('documentables') ? Schema::getColumnListing('documentables') : null,
        'noteables_table' => Schema::hasTable('noteables') ? Schema::getColumnListing('noteables') : null,
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
