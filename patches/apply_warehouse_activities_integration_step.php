<?php

$root = dirname(__DIR__);

function path_join(string $root, string $path): string
{
    return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
}

function read_file_or_fail(string $path): string
{
    if (! is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fwrite(STDERR, "Cannot read file: {$path}\n");
        exit(1);
    }

    return $content;
}

function write_file(string $path, string $content): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }

    file_put_contents($path, $content);
}

function backup_file(string $path): void
{
    if (is_file($path)) {
        $backup = $path.'.bak-warehouse-activities-'.date('YmdHis');
        copy($path, $backup);
    }
}

function ensure_import(string $content, string $import, string $afterNeedle = null): string
{
    if (str_contains($content, $import)) {
        return $content;
    }

    if ($afterNeedle && str_contains($content, $afterNeedle)) {
        return str_replace($afterNeedle, $afterNeedle."\n".$import, $content);
    }

    return preg_replace('/(<script setup>\s*)/m', "$1\n".$import."\n", $content, 1) ?? $content;
}

function insert_before_first(string $content, string $needle, string $insert): string
{
    if (str_contains($content, trim($insert))) {
        return $content;
    }

    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "Could not find insertion needle.\nNeedle: {$needle}\n");
        exit(1);
    }

    return substr($content, 0, $pos).$insert.substr($content, $pos);
}

function insert_after_first(string $content, string $needle, string $insert): string
{
    if (str_contains($content, trim($insert))) {
        return $content;
    }

    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "Could not find insertion needle.\nNeedle: {$needle}\n");
        exit(1);
    }

    $pos += strlen($needle);

    return substr($content, 0, $pos).$insert.substr($content, $pos);
}

$viewPath = path_join($root, 'modules/Warehouse/resources/js/views/WarehousesView.vue');
$modelPath = path_join($root, 'modules/Warehouse/app/Models/Warehouse.php');
$resourcePath = path_join($root, 'modules/Warehouse/app/Resources/Warehouse.php');
$providerPath = path_join($root, 'modules/Warehouse/app/Providers/WarehouseServiceProvider.php');
$activityModelPath = path_join($root, 'modules/Activities/app/Models/Activity.php');

foreach ([$viewPath, $modelPath, $resourcePath, $providerPath, $activityModelPath] as $file) {
    backup_file($file);
}

// 1) Frontend: add Activities tab and panel to Warehouse detail.
$view = read_file_or_fail($viewPath);
$view = ensure_import(
    $view,
    "import ActivitiesTab from '@/Activities/components/RecordTabActivity.vue'",
    "import ResourceMediaPanel from '@/Core/components/Resource/ResourceMediaPanel.vue'"
);
$view = ensure_import(
    $view,
    "import ActivitiesTabPanel from '@/Activities/components/RecordTabActivityPanel.vue'",
    "import ActivitiesTab from '@/Activities/components/RecordTabActivity.vue'"
);

if (! str_contains($view, '<ActivitiesTab')) {
    $needle = "\n\n            <ITab>\n              <Icon icon=\"PaperClip\" />";
    $insert = "\n\n            <ActivitiesTab\n              :resource-name=\"resourceName\"\n              :resource-id=\"safeResource.id\"\n              :resource=\"safeResource\"\n            />";
    $view = insert_before_first($view, $needle, $insert);
}

if (! str_contains($view, '<ActivitiesTabPanel')) {
    $needle = "\n\n          <ITabPanel>\n            <ResourceMediaPanel";
    $insert = "\n\n          <ActivitiesTabPanel\n            scroll-element=\"#main\"\n            :resource-name=\"resourceName\"\n            :resource-id=\"safeResource.id\"\n            :resource=\"safeResource\"\n          />";
    $view = insert_before_first($view, $needle, $insert);
}

// Normalize activities defaults to prevent the tab from reading undefined arrays/counts.
if (! str_contains($view, 'incomplete_activities_for_user_count')) {
    if (! preg_match('/function\s+normalizeResource\s*\(([^)]*)\)/', $view, $matches)) {
        fwrite(STDERR, "Could not detect normalizeResource argument in WarehousesView.vue\n");
        exit(1);
    }

    $resourceVar = trim($matches[1]);
    $patterns = [
        "/(notes_count:\s*Number\({$resourceVar}\\.notes_count\s*\|\|\s*0\),)/",
        "/(media_count:\s*Number\({$resourceVar}\\.media_count\s*\|\|\s*\(Array\.isArray\({$resourceVar}\\.media\)\s*\?\s*{$resourceVar}\\.media\.length\s*:\s*0\)\),)/s",
    ];

    $inserted = false;
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $view)) {
            $view = preg_replace(
                $pattern,
                "$1\n    activities: Array.isArray({$resourceVar}.activities) ? {$resourceVar}.activities : [],\n    incomplete_activities_for_user_count: Number({$resourceVar}.incomplete_activities_for_user_count || 0),",
                $view,
                1
            ) ?? $view;
            $inserted = true;
            break;
        }
    }

    if (! $inserted) {
        // Fallback: put defaults right after path normalization.
        $pathNeedle = "path: {$resourceVar}.path || resourcePath.value,";
        if (str_contains($view, $pathNeedle)) {
            $view = str_replace(
                $pathNeedle,
                $pathNeedle."\n    activities: Array.isArray({$resourceVar}.activities) ? {$resourceVar}.activities : [],\n    incomplete_activities_for_user_count: Number({$resourceVar}.incomplete_activities_for_user_count || 0),",
                $view
            );
            $inserted = true;
        }
    }

    if (! $inserted) {
        fwrite(STDERR, "Could not insert activities normalization in WarehousesView.vue\n");
        exit(1);
    }
}

write_file($viewPath, $view);

// 2) Warehouse model: use HasActivities.
$model = read_file_or_fail($modelPath);
if (! str_contains($model, 'use Modules\\Activities\\Concerns\\HasActivities;')) {
    $model = str_replace(
        "namespace Modules\\Warehouse\\Models;\n\n",
        "namespace Modules\\Warehouse\\Models;\n\nuse Modules\\Activities\\Concerns\\HasActivities;\n",
        $model
    );
}

if (! preg_match('/use\s+[^;]*HasActivities[^;]*;/s', $model)) {
    if (str_contains($model, "use HasMedia,\n        HasTimeline,")) {
        $model = str_replace(
            "use HasMedia,\n        HasTimeline,",
            "use HasActivities,\n        HasMedia,\n        HasTimeline,",
            $model
        );
    } elseif (str_contains($model, 'use HasMedia,')) {
        $model = str_replace('use HasMedia,', 'use HasActivities, HasMedia,', $model);
    } else {
        fwrite(STDERR, "Could not add HasActivities trait to Warehouse model.\n");
        exit(1);
    }
}
write_file($modelPath, $model);

// 3) Activity model: add warehouses() morphedByMany relation.
$activity = read_file_or_fail($activityModelPath);
if (! str_contains($activity, 'function warehouses(')) {
    $method = <<<'PHP_METHOD'

    /**
     * Warehouses associated with this activity.
     */
    public function warehouses(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(\Modules\Warehouse\Models\Warehouse::class, 'activityable');
    }
PHP_METHOD;

    $lastBrace = strrpos($activity, '}');
    if ($lastBrace === false) {
        fwrite(STDERR, "Could not locate end of Activity model class.\n");
        exit(1);
    }

    $activity = substr($activity, 0, $lastBrace).$method."\n".substr($activity, $lastBrace);
}
write_file($activityModelPath, $activity);

// 4) Warehouse resource: add related activity action and display query eager loading/counts.
$resource = read_file_or_fail($resourcePath);
if (! str_contains($resource, 'use Modules\\Activities\\Actions\\CreateRelatedActivityAction;')) {
    $resource = str_replace(
        "namespace Modules\\Warehouse\\Resources;\n\n",
        "namespace Modules\\Warehouse\\Resources;\n\nuse Modules\\Activities\\Actions\\CreateRelatedActivityAction;\n",
        $resource
    );
}

if (! str_contains($resource, 'function displayQuery(')) {
    $method = <<<'PHP_METHOD'

    public function displayQuery(): Builder
    {
        return parent::displayQuery()
            ->with([
                'activities' => fn (Builder $query) => $query->with(['media', 'comments']),
                'media',
            ])
            ->withCount([
                'incompleteActivitiesForUser as incomplete_activities_for_user_count',
            ]);
    }
PHP_METHOD;

    if (str_contains($resource, "\n\n    public function panels(ResourceRequest ")) {
        $resource = str_replace("\n\n    public function panels(ResourceRequest ", $method."\n\n    public function panels(ResourceRequest ", $resource);
    } elseif (str_contains($resource, "\n\n    public function fields(ResourceRequest ")) {
        $resource = str_replace("\n\n    public function fields(ResourceRequest ", $method."\n\n    public function fields(ResourceRequest ", $resource);
    } else {
        fwrite(STDERR, "Could not insert displayQuery in Warehouse resource.\n");
        exit(1);
    }
}

if (! str_contains($resource, 'CreateRelatedActivityAction::make()->onlyInline()')) {
    $resource = str_replace(
        "return [\n            Action::make()->floatResourceInEditMode(),",
        "return [\n            CreateRelatedActivityAction::make()->onlyInline(),\n\n            Action::make()->floatResourceInEditMode(),",
        $resource
    );
}
write_file($resourcePath, $resource);

// 5) Warehouse provider: accept activities via_resource=warehouses and warehouses[] field.
$provider = read_file_or_fail($providerPath);
if (! str_contains($provider, '$this->registerActivitiesViaResourceValidation();')) {
    $provider = str_replace(
        '$this->registerNotesViaResourceValidation();',
        '$this->registerNotesViaResourceValidation();' . "\n        " . '$this->registerActivitiesViaResourceValidation();',
        $provider
    );
}

if (! str_contains($provider, 'function registerActivitiesViaResourceValidation')) {
    $method = <<<'PHP_METHOD'

    /**
     * Allow the Activities resource to accept Warehouse as via_resource.
     *
     * RelatedActivityCreate posts to /api/activities?via_resource=warehouses&via_resource_id={id}
     * and may include warehouses: [{id}] depending on the Resource form payload.
     */
    protected function registerActivitiesViaResourceValidation(): void
    {
        if (! function_exists('add_filter')) {
            return;
        }

        add_filter('http.request.create_resource_request.activities.rules', function (array $rules, CreateResourceRequest $request) {
            $viaResource = $request->query('via_resource', $request->input('via_resource'));

            if ($viaResource !== 'warehouses') {
                return $rules;
            }

            $rules['via_resource'] = ['required_with:via_resource_id', Rule::in(['warehouses'])];
            $rules['via_resource_id'] = ['required_with:via_resource', 'integer', Rule::exists('warehouses', 'id')];
            $rules['warehouses'] = ['nullable', 'array'];
            $rules['warehouses.*'] = ['integer', Rule::exists('warehouses', 'id')];

            return $rules;
        }, 50, 2);
    }
PHP_METHOD;

    if (str_contains($provider, "\n\n    /**\n     * Configure the module.")) {
        $provider = str_replace("\n\n    /**\n     * Configure the module.", $method."\n\n    /**\n     * Configure the module.", $provider);
    } else {
        fwrite(STDERR, "Could not insert registerActivitiesViaResourceValidation in WarehouseServiceProvider.\n");
        exit(1);
    }
}
write_file($providerPath, $provider);

// 6) Documentation.
$contractDoc = path_join($root, 'docs/ai/03-architecture/module-builder-activities-contract.md');
$historyDoc = path_join($root, 'docs/ai/04-docops/history/2026-06-28-warehouse-activities-integration.md');

write_file($contractDoc, <<<'MD'
# Module Builder Activities Contract

Status: canonical

For CRM-style resources that need related activities, follow the first-party ConcordCRM pattern used by Contacts, Companies, and Deals.

## Required backend contract

1. The resource model must use `Modules\Activities\Concerns\HasActivities`.
2. `Modules\Activities\Models\Activity` must expose a concrete `morphedByMany` relation for the resource plural name, for example `warehouses()`.
3. The resource class should include `CreateRelatedActivityAction::make()->onlyInline()` before edit/delete actions.
4. The resource `displayQuery()` should eager load `activities` and expose `incomplete_activities_for_user_count`.
5. If the related create flow uses `via_resource`, the module service provider must allow `via_resource=warehouses` and validate `via_resource_id`.

## Required frontend contract

1. Use `RecordTabActivity.vue` as the tab label/count component.
2. Use `RecordTabActivityPanel.vue` as the panel.
3. Pass `resourceName`, `resourceId`, `resource`, and `scroll-element="#main"`.
4. Normalize the resource shape so `activities` is always an array and `incomplete_activities_for_user_count` is always numeric.

## Do not use

- Do not create a custom activities table for each module.
- Do not create custom Warehouse-only activity UI unless the first-party panel cannot support the need.
- Do not bypass the `activityables` pivot contract.
MD);

write_file($historyDoc, <<<'MD'
# Warehouse Activities Integration

Date: 2026-06-28
Status: applied

Warehouse now follows the first-party Activities contract:

- `Warehouse` model uses `HasActivities`.
- `Activity` model knows the `warehouses()` morph relation.
- `Warehouse` resource exposes `CreateRelatedActivityAction::make()->onlyInline()`.
- `Warehouse` resource display query eager loads activities and incomplete count.
- `WarehousesView.vue` renders `ActivitiesTab` and `ActivitiesTabPanel`.
- Warehouse service provider validates `via_resource=warehouses` for related activity creation.

This is the canonical implementation for future module-builder/RAG guidance.
MD);

echo "Warehouse activities integration applied.\n";
