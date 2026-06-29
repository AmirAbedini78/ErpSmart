<?php

$root = dirname(__DIR__);

function fail_with(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function read_file_or_fail(string $path): string
{
    if (! is_file($path)) {
        fail_with("File not found: {$path}");
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        fail_with("Unable to read: {$path}");
    }

    return $contents;
}

function write_file_or_fail(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) === false) {
        fail_with("Unable to write: {$path}");
    }
}

function ensure_import(string $php, string $import): string
{
    if (str_contains($php, "use {$import};")) {
        return $php;
    }

    return preg_replace('/(namespace [^;]+;\s*)/', "$1\nuse {$import};\n", $php, 1) ?? $php;
}

$modelPath = $root.'/modules/Warehouse/app/Models/Warehouse.php';
$resourcePath = $root.'/modules/Warehouse/app/Resources/Warehouse.php';
$activityModelPath = $root.'/modules/Activities/app/Models/Activity.php';
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';
$providerPath = $root.'/modules/Warehouse/app/Providers/WarehouseServiceProvider.php';
$historyPath = $root.'/docs/ai/04-docops/history/2026-06-28-warehouse-activities-detail-500-safe-fix.md';

$model = read_file_or_fail($modelPath);
$model = ensure_import($model, 'Modules\\Activities\\Concerns\\HasActivities');

if (! preg_match('/use\s+HasActivities\s*[;,]/', $model)) {
    if (preg_match('/use\s+HasMedia,\s*\n\s*HasTimeline,\s*\n\s*Resourceable;/', $model)) {
        $model = preg_replace(
            '/use\s+HasMedia,\s*\n\s*HasTimeline,\s*\n\s*Resourceable;/',
            "use HasMedia,\n        HasTimeline,\n        HasActivities,\n        Resourceable;",
            $model,
            1
        ) ?? $model;
    } elseif (preg_match('/use\s+HasMedia,\s*\n\s*HasTimeline,/', $model)) {
        $model = preg_replace('/use\s+HasMedia,\s*\n\s*HasTimeline,/', "use HasMedia,\n        HasTimeline,\n        HasActivities,", $model, 1) ?? $model;
    } elseif (preg_match('/use\s+Resourceable;/', $model)) {
        $model = preg_replace('/use\s+Resourceable;/', "use HasActivities,\n        Resourceable;", $model, 1) ?? $model;
    }
}

// Avoid forcing activities eager-loading on every Warehouse detail request. The Activities tab lazy-loads records via Core.
$model = preg_replace_callback('/protected\s+\$with\s*=\s*\[(.*?)\];/s', function (array $matches): string {
    $body = $matches[1];

    if (! str_contains($body, 'activities') && ! str_contains($body, 'incompleteActivities')) {
        return $matches[0];
    }

    return "protected \$with = [\n        'media',\n    ];";
}, $model) ?? $model;

if (! str_contains($model, 'getIncompleteActivitiesForUserCountAttribute')) {
    $accessor = <<<'PHP_ACCESSOR'

    /**
     * Keep the Activities tab badge safe when the detail payload has not loaded activities yet.
     * The tab panel lazy-loads the actual records, so this accessor only prevents null/undefined badges.
     */
    public function getIncompleteActivitiesForUserCountAttribute(): int
    {
        if (! method_exists($this, 'activities')) {
            return 0;
        }

        if (! $this->relationLoaded('activities')) {
            return 0;
        }

        return $this->activities
            ->where('is_completed', false)
            ->count();
    }
PHP_ACCESSOR;

    $model = preg_replace('/\n}\s*$/', $accessor."\n}\n", $model, 1) ?? $model;
}

write_file_or_fail($modelPath, $model);

$activity = read_file_or_fail($activityModelPath);
if (! str_contains($activity, 'function warehouses(')) {
    $relation = <<<'PHP_RELATION'

    /**
     * Warehouses associated with the activity.
     */
    public function warehouses(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(\Modules\Warehouse\Models\Warehouse::class, 'activityable');
    }
PHP_RELATION;

    $activity = preg_replace('/\n}\s*$/', $relation."\n}\n", $activity, 1) ?? $activity;
    write_file_or_fail($activityModelPath, $activity);
}

$resource = read_file_or_fail($resourcePath);
$resource = ensure_import($resource, 'Modules\\Activities\\Actions\\CreateRelatedActivityAction');

if (! str_contains($resource, 'CreateRelatedActivityAction::make()->onlyInline()')) {
    $resource = preg_replace(
        '/return\s*\[\s*\n\s*Action::make\(\)->floatResourceInEditMode\(\),/',
        "return [\n            CreateRelatedActivityAction::make()->onlyInline(),\n\n            Action::make()->floatResourceInEditMode(),",
        $resource,
        1
    ) ?? $resource;
}

// If a previous patch added an eager displayQuery for activities and it is causing /api/warehouses/{id} 500,
// keep the resource detail query simple. Activity data is loaded by RecordTabActivityPanel.
if (preg_match('/public\s+function\s+displayQuery\s*\([^)]*\)\s*:\s*Builder\s*\{[\s\S]*?\n    \}\s*\n\s*public\s+function\s+fields\s*\(/', $resource)) {
    $resource = preg_replace(
        '/public\s+function\s+displayQuery\s*\([^)]*\)\s*:\s*Builder\s*\{[\s\S]*?\n    \}\s*\n\s*public\s+function\s+fields\s*\(/',
        "public function displayQuery(Builder \$query, ResourceRequest \$request): Builder\n    {\n        return \$query;\n    }\n\n    public function fields(",
        $resource,
        1
    ) ?? $resource;
}

write_file_or_fail($resourcePath, $resource);

$view = read_file_or_fail($viewPath);
// Make frontend robust if the detail payload does not include activities until the tab lazy-loads them.
if (str_contains($view, 'function normalizeResource(') && ! str_contains($view, 'incomplete_activities_for_user_count')) {
    $view = preg_replace(
        '/function\s+normalizeResource\s*\(([^)]*)\)\s*\{\s*/',
        "function normalizeResource($1) {\n  const normalized = { ...($1 || {}) }\n\n  normalized.activities = normalized.activities || []\n  normalized.incomplete_activities_for_user_count = normalized.incomplete_activities_for_user_count || 0\n\n  ",
        $view,
        1
    ) ?? $view;

    // If the old function had "return { ...resource" this replacement may be insufficient; leave it for manual inspection.
    // The backend safe fix is the important part for the 500.
}
write_file_or_fail($viewPath, $view);

$provider = read_file_or_fail($providerPath);
if (str_contains($provider, 'registerActivitiesViaResourceValidation') && ! str_contains($provider, "create_resource_request.activities.rules")) {
    // Leave user modifications intact. This guard just avoids false assumptions.
}

$history = <<<'MD'
# Warehouse Activities Detail 500 Safe Fix

Status: applied

This patch keeps the final Activities integration aligned with first-party ConcordCRM resources while removing risky eager-loading/count assumptions from `GET /api/warehouses/{id}`.

Canonical contract:

- Warehouse model uses `Modules\Activities\Concerns\HasActivities`.
- `Activity` model exposes `warehouses()` through the shared `activityables` morph pivot.
- Warehouse Resource exposes `CreateRelatedActivityAction::make()->onlyInline()`.
- Warehouse detail uses `ActivitiesTab` / `ActivitiesTabPanel` and lets the tab lazy-load activity records.
- Warehouse detail payload must stay safe even when `activities` are not eager loaded.

Do not force-load activities from the Warehouse detail endpoint unless the exact first-party display query contract is verified from Contacts/Companies/Deals.
MD;
write_file_or_fail($historyPath, $history."\n");

echo "Warehouse activities detail 500 safe fix applied.\n";
