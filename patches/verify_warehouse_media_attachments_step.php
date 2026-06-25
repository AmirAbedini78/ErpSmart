<?php
/**
 * Verification helper for Warehouse media/attachments contract.
 * Run from project root:
 *   docker compose exec app php patches/verify_warehouse_media_attachments_step.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$resource = \Modules\Core\Facades\Innoclapps::resourceByName('warehouses');
$model = new \Modules\Warehouse\Models\Warehouse;

$checks = [
    'resource_exists' => $resource !== null,
    'resource_class' => $resource ? get_class($resource) : null,
    'resource_is_mediable' => $resource instanceof \Modules\Core\Contracts\Resources\Mediable,
    'model_uses_has_media' => in_array(\Modules\Core\Common\Media\HasMedia::class, class_uses_recursive($model), true),
    'model_has_media_relation' => method_exists($model, 'media'),
    'model_has_get_media_directory' => method_exists($model, 'getMediaDirectory'),
    'model_has_get_media_tags' => method_exists($model, 'getMediaTags'),
    'media_controller_exists' => class_exists(\Modules\Core\Http\Controllers\Api\Resource\MediaController::class),
];

print_r($checks);

if (in_array(false, $checks, true)) {
    exit(1);
}
