<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$resourcePath = $root.'/modules/Warehouse/app/Resources/Warehouse.php';
$modelPath = $root.'/modules/Warehouse/app/Models/Warehouse.php';
$activityPath = $root.'/modules/Activities/app/Models/Activity.php';
$historyPath = $root.'/docs/ai/04-docops/history/2026-06-28-warehouse-activities-display-query-signature-fix.md';

$resource = file_exists($resourcePath) ? file_get_contents($resourcePath) : '';
$model = file_exists($modelPath) ? file_get_contents($modelPath) : '';
$activity = file_exists($activityPath) ? file_get_contents($activityPath) : '';
$history = file_exists($historyPath) ? file_get_contents($historyPath) : '';

$displayQueryBody = '';
if (preg_match('/public function displayQuery\s*\([^)]*\)\s*:\s*Builder\s*\{([\s\S]*?)\n\s*\}/', $resource, $m)) {
    $displayQueryBody = $m[1];
}

$checks = [
    'resource_exists' => file_exists($resourcePath),
    'model_exists' => file_exists($modelPath),
    'display_query_signature_compatible' => preg_match('/public function displayQuery\s*\(\s*\)\s*:\s*Builder/', $resource) === 1,
    'display_query_has_no_request_params' => preg_match('/public function displayQuery\s*\(\s*Builder\s+\$query|ResourceRequest\s+\$request\s*\)\s*:\s*Builder/', $resource) !== 1,
    'display_query_returns_parent' => str_contains($displayQueryBody, 'return parent::displayQuery();'),
    'display_query_not_forcing_activities' => ! str_contains($displayQueryBody, 'activities') && ! str_contains($displayQueryBody, 'with(') && ! str_contains($displayQueryBody, 'withCount('),
    'warehouse_imports_has_activities' => str_contains($model, 'use Modules\\Activities\\Concerns\\HasActivities;'),
    'warehouse_model_uses_has_activities_trait' => preg_match('/\n\s*use HasActivities;\s*/', $model) === 1,
    'warehouse_has_safe_incomplete_count_accessor' => str_contains($model, 'getIncompleteActivitiesForUserCountAttribute'),
    'activity_model_has_warehouses_relation' => str_contains($activity, 'function warehouses(') && str_contains($activity, "morphedByMany(\\Modules\\Warehouse\\Models\\Warehouse::class, 'activityable'"),
    'history_note_exists' => str_contains($history, 'Display Query Signature Fix'),
];

foreach ($checks as $name => $passed) {
    printf("%-52s : %s\n", $name, $passed ? 'true' : 'false');
}

if (in_array(false, $checks, true)) {
    exit(1);
}

