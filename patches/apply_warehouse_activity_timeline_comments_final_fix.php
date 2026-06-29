<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

function patch_backup(string $path, string $suffix): void
{
    if (is_file($path)) {
        copy($path, $path.'.bak-'.$suffix.'-'.date('YmdHis'));
    }
}

function patch_write(string $path, string $contents): void
{
    file_put_contents($path, $contents);
}

$suffix = 'warehouse-activity-timeline-comments-final';

$warehouseResourcePath = base_path('modules/Warehouse/app/Resources/Warehouse.php');
$createComponentPath = base_path('modules/Activities/resources/js/components/RelatedActivityCreate.vue');
$relatedActivityPath = base_path('modules/Activities/resources/js/components/RelatedActivity.vue');

foreach ([$warehouseResourcePath, $createComponentPath, $relatedActivityPath] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }
}

patch_backup($warehouseResourcePath, $suffix);
patch_backup($createComponentPath, $suffix);
patch_backup($relatedActivityPath, $suffix);

// 1) Allow Warehouse to be used as via_resource for Activity comments.
// CommentController accepts via_resource only from resources implementing PipesComments.
$resource = file_get_contents($warehouseResourcePath);

if (! str_contains($resource, 'use Modules\\Comments\\Contracts\\PipesComments;')) {
    $resource = str_replace(
        "use Modules\\Activities\\Actions\\CreateRelatedActivityAction;\n",
        "use Modules\\Activities\\Actions\\CreateRelatedActivityAction;\nuse Modules\\Comments\\Contracts\\PipesComments;\n",
        $resource
    );
}

if (! str_contains($resource, 'PipesComments')) {
    fwrite(STDERR, "Failed to inject PipesComments import.\n");
    exit(1);
}

$implementsPattern = 'class Warehouse extends Resource implements AcceptsCustomFields, AcceptsUniqueCustomFields, Cloneable, Exportable, Importable, Mediable, Tableable, WithResourceRoutes';
if (str_contains($resource, $implementsPattern)) {
    $resource = str_replace(
        $implementsPattern,
        'class Warehouse extends Resource implements AcceptsCustomFields, AcceptsUniqueCustomFields, Cloneable, Exportable, Importable, Mediable, PipesComments, Tableable, WithResourceRoutes',
        $resource
    );
} elseif (! preg_match('/class\s+Warehouse\s+extends\s+Resource\s+implements[^{]*\bPipesComments\b/s', $resource)) {
    fwrite(STDERR, "Could not locate Warehouse resource implements list for PipesComments.\n");
    exit(1);
}

patch_write($warehouseResourcePath, $resource);

// 2) Ensure related Activity creation sends the relationship in the same top-level
// form shape used by first-party CreateRelatedActivityAction: warehouses => [id].
// Keep the nested associations object too, because the popover uses that shape.
$create = file_get_contents($createComponentPath);

$oldInitial = <<<'VUE'
const { form } = useForm(
  {
    is_completed: false,
    associations: {
      [props.viaResource]: [props.viaResourceId],
    },
  },
  {
    resetOnSuccess: true,
  }
)
VUE;

$newInitial = <<<'VUE'
const { form } = useForm(
  {
    is_completed: false,
    [props.viaResource]: [props.viaResourceId],
    associations: {
      [props.viaResource]: [props.viaResourceId],
    },
  },
  {
    resetOnSuccess: true,
  }
)
VUE;

if (str_contains($create, $oldInitial)) {
    $create = str_replace($oldInitial, $newInitial, $create);
} elseif (! str_contains($create, '[props.viaResource]: [props.viaResourceId]')) {
    fwrite(STDERR, "Could not update RelatedActivityCreate initial form.\n");
    exit(1);
}

$needle = "async function create() {\n";
$ensureBlock = <<<'VUE'
async function create() {
  form.fill(props.viaResource, [props.viaResourceId])
  form.fill('associations', {
    ...(form.associations || {}),
    [props.viaResource]: [props.viaResourceId],
  })

VUE;

if (! str_contains($create, 'form.fill(props.viaResource, [props.viaResourceId])')) {
    if (! str_contains($create, $needle)) {
        fwrite(STDERR, "Could not locate RelatedActivityCreate create() function.\n");
        exit(1);
    }
    $create = str_replace($needle, $ensureBlock, $create);
}

patch_write($createComponentPath, $create);

// 3) When a comment is created from a zero-comment activity, increment comments_count
// so the collapsable comments section becomes visible immediately.
$related = file_get_contents($relatedActivityPath);

if (! str_contains($related, 'comments_count: commentsCount + 1')) {
    $related = str_replace(
        "comments: [\$event],\n",
        "comments: [\$event],\n                comments_count: commentsCount + 1,\n",
        $related
    );
}

patch_write($relatedActivityPath, $related);

// 4) Write a small history note.
$historyPath = base_path('docs/ai/04-docops/history/2026-06-29-warehouse-activity-timeline-comments-final-fix.md');
if (! is_dir(dirname($historyPath))) {
    mkdir(dirname($historyPath), 0775, true);
}
file_put_contents($historyPath, <<<'MD'
# Warehouse Activity Timeline & Comments Final Fix

This patch fixes the remaining Warehouse activity integration issues after the detail, activity create, and association flows were restored.

## Fixes

- `Modules\Warehouse\Resources\Warehouse` now implements `Modules\Comments\Contracts\PipesComments`.
  - This allows `/api/activities/{id}/comments?via_resource=warehouses&via_resource_id={id}` to pass CommentController validation.
- `RelatedActivityCreate.vue` sends both:
  - top-level `warehouses: [warehouseId]`
  - nested `associations: { warehouses: [warehouseId] }`
  so ResourceController can persist the `activityables` pivot and the UI popover still has its expected shape.
- `RelatedActivity.vue` increments `comments_count` after comment creation so comments become visible immediately even when the activity previously had zero comments.

## Notes

Existing activities created before this patch may be orphaned from Warehouse if no `activityables` pivot exists. Use the explicit repair helper with a known activity id:

```bash
docker compose exec app php patches/repair_warehouse_activity_link.php 13 <activity_id>
```
MD);

echo "Warehouse activity timeline/comments final fix applied.\n";
echo "Backups suffix: {$suffix}\n";
