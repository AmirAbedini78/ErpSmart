<?php

$root = dirname(__DIR__);

function path_join(string $root, string $path): string
{
    return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
}

function contents(string $path): string
{
    return is_file($path) ? (file_get_contents($path) ?: '') : '';
}

$view = contents(path_join($root, 'modules/Warehouse/resources/js/views/WarehousesView.vue'));
$model = contents(path_join($root, 'modules/Warehouse/app/Models/Warehouse.php'));
$resource = contents(path_join($root, 'modules/Warehouse/app/Resources/Warehouse.php'));
$provider = contents(path_join($root, 'modules/Warehouse/app/Providers/WarehouseServiceProvider.php'));
$activity = contents(path_join($root, 'modules/Activities/app/Models/Activity.php'));
$contractDoc = contents(path_join($root, 'docs/ai/03-architecture/module-builder-activities-contract.md'));
$historyDoc = contents(path_join($root, 'docs/ai/04-docops/history/2026-06-28-warehouse-activities-integration.md'));

$checks = [
    'view_imports_activities_tab' => str_contains($view, "@/Activities/components/RecordTabActivity.vue"),
    'view_imports_activities_panel' => str_contains($view, "@/Activities/components/RecordTabActivityPanel.vue"),
    'view_renders_activities_tab' => str_contains($view, '<ActivitiesTab'),
    'view_renders_activities_panel' => str_contains($view, '<ActivitiesTabPanel'),
    'view_passes_resource_to_activities' => str_contains($view, ':resource="safeResource"') && str_contains($view, ':resource-name="resourceName"') && str_contains($view, ':resource-id="safeResource.id"'),
    'view_normalizes_activities_array' => str_contains($view, 'activities: Array.isArray('),
    'view_normalizes_incomplete_count' => str_contains($view, 'incomplete_activities_for_user_count'),
    'warehouse_model_imports_has_activities' => str_contains($model, 'use Modules\\Activities\\Concerns\\HasActivities;'),
    'warehouse_model_uses_has_activities' => preg_match('/use\s+[^;]*HasActivities[^;]*;/s', $model) === 1,
    'activity_model_has_warehouses_relation' => str_contains($activity, 'function warehouses(') && str_contains($activity, 'Modules\\Warehouse\\Models\\Warehouse::class') && str_contains($activity, "'activityable'"),
    'resource_imports_create_activity_action' => str_contains($resource, 'use Modules\\Activities\\Actions\\CreateRelatedActivityAction;'),
    'resource_has_create_related_activity_action' => str_contains($resource, 'CreateRelatedActivityAction::make()->onlyInline()'),
    'resource_has_float_edit_action' => str_contains($resource, 'Action::make()->floatResourceInEditMode()'),
    'resource_has_display_query' => str_contains($resource, 'function displayQuery('),
    'resource_eager_loads_activities' => str_contains($resource, "'activities' => fn"),
    'resource_counts_incomplete_activities' => str_contains($resource, 'incompleteActivitiesForUser as incomplete_activities_for_user_count'),
    'provider_calls_activities_validation' => str_contains($provider, '$this->registerActivitiesViaResourceValidation();'),
    'provider_has_activities_validation_method' => str_contains($provider, 'function registerActivitiesViaResourceValidation'),
    'provider_validates_activities_via_resource' => str_contains($provider, 'create_resource_request.activities.rules') && str_contains($provider, "Rule::in(['warehouses'])"),
    'provider_validates_warehouses_payload' => str_contains($provider, "\$rules['warehouses']") && str_contains($provider, "\$rules['warehouses.*']"),
    'docs_contract_exists' => str_contains($contractDoc, 'Module Builder Activities Contract') && str_contains($contractDoc, 'Status: canonical'),
    'docs_history_exists' => str_contains($historyDoc, 'Warehouse Activities Integration') && str_contains($historyDoc, 'Status: applied'),
];

$max = max(array_map('strlen', array_keys($checks)));
$failed = false;
foreach ($checks as $name => $ok) {
    printf("%-45s : %s\n", $name, $ok ? 'true' : 'false');
    if (! $ok) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
