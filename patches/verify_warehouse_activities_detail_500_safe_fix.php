<?php

$root = dirname(__DIR__);

function read_file(string $path): string
{
    return is_file($path) ? (file_get_contents($path) ?: '') : '';
}

$model = read_file($root.'/modules/Warehouse/app/Models/Warehouse.php');
$resource = read_file($root.'/modules/Warehouse/app/Resources/Warehouse.php');
$activity = read_file($root.'/modules/Activities/app/Models/Activity.php');
$view = read_file($root.'/modules/Warehouse/resources/js/views/WarehousesView.vue');
$history = read_file($root.'/docs/ai/04-docops/history/2026-06-28-warehouse-activities-detail-500-safe-fix.md');

$checks = [
    'warehouse_model_imports_has_activities' => str_contains($model, 'Modules\\Activities\\Concerns\\HasActivities'),
    'warehouse_model_uses_has_activities' => preg_match('/use\s+HasActivities\s*[;,]/', $model) === 1,
    'warehouse_with_does_not_force_activities' => ! preg_match('/protected\s+\$with\s*=\s*\[[\s\S]*activities[\s\S]*\];/', $model),
    'warehouse_has_safe_incomplete_count_accessor' => str_contains($model, 'getIncompleteActivitiesForUserCountAttribute'),
    'activity_model_has_warehouses_relation' => str_contains($activity, 'function warehouses()') && str_contains($activity, 'activityable') && str_contains($activity, 'Modules\\Warehouse\\Models\\Warehouse'),
    'resource_imports_create_related_activity_action' => str_contains($resource, 'Modules\\Activities\\Actions\\CreateRelatedActivityAction'),
    'resource_has_create_related_activity_action' => str_contains($resource, 'CreateRelatedActivityAction::make()->onlyInline()'),
    'resource_detail_query_not_forcing_activity_count' => ! str_contains($resource, 'incomplete_activities_for_user_count') || str_contains($resource, 'return $query;'),
    'view_uses_activities_tab' => str_contains($view, 'ActivitiesTab') || str_contains($view, '<ActivitiesTab'),
    'view_uses_activities_panel' => str_contains($view, 'ActivitiesTabPanel') || str_contains($view, '<ActivitiesTabPanel'),
    'history_note_exists' => str_contains($history, 'Warehouse Activities Detail 500 Safe Fix'),
];

foreach ($checks as $name => $passed) {
    echo str_pad($name, 52).': '.($passed ? 'true' : 'false').PHP_EOL;
}
