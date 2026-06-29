<?php

declare(strict_types=1);

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

$checks = [];

$warehouseResourcePath = base_path('modules/Warehouse/app/Resources/Warehouse.php');
$createComponentPath = base_path('modules/Activities/resources/js/components/RelatedActivityCreate.vue');
$relatedActivityPath = base_path('modules/Activities/resources/js/components/RelatedActivity.vue');
$historyPath = base_path('docs/ai/04-docops/history/2026-06-29-warehouse-activity-timeline-comments-final-fix.md');

$resource = is_file($warehouseResourcePath) ? file_get_contents($warehouseResourcePath) : '';
$create = is_file($createComponentPath) ? file_get_contents($createComponentPath) : '';
$related = is_file($relatedActivityPath) ? file_get_contents($relatedActivityPath) : '';

$checks['warehouse_resource_exists'] = is_file($warehouseResourcePath);
$checks['warehouse_imports_pipes_comments'] = str_contains($resource, 'use Modules\\Comments\\Contracts\\PipesComments;');
$checks['warehouse_implements_pipes_comments'] = preg_match('/class\s+Warehouse\s+extends\s+Resource\s+implements[^{]*\bPipesComments\b/s', $resource) === 1;
$checks['related_activity_create_has_top_level_via_resource'] = str_contains($create, '[props.viaResource]: [props.viaResourceId]');
$checks['related_activity_create_ensures_top_level_before_post'] = str_contains($create, 'form.fill(props.viaResource, [props.viaResourceId])');
$checks['related_activity_create_keeps_nested_associations'] = str_contains($create, "form.fill('associations'") && str_contains($create, '...(form.associations || {})');
$checks['related_activity_comment_increments_count'] = str_contains($related, 'comments_count: commentsCount + 1');
$checks['history_note_exists'] = is_file($historyPath);

try {
    require base_path('vendor/autoload.php');
    $app = require base_path('bootstrap/app.php');
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $warehouseResource = Modules\Core\Facades\Innoclapps::resourceByName('warehouses');
    $checks['runtime_warehouse_is_pipes_comments'] = $warehouseResource instanceof Modules\Comments\Contracts\PipesComments;

    $activityResource = Modules\Core\Facades\Innoclapps::resourceByName('activities');
    $checks['runtime_activity_sees_warehouses_associateable'] = in_array('warehouses', $activityResource->associateableRelations(), true);
} catch (Throwable $e) {
    $checks['runtime_bootstrap_error'] = get_class($e).': '.$e->getMessage();
}

foreach ($checks as $name => $result) {
    printf("%-62s : %s\n", $name, $result === true ? 'true' : ($result === false ? 'false' : $result));
}
