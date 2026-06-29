<?php

$root = dirname(__DIR__);

function path_join(string ...$parts): string
{
    return preg_replace('#/+#', '/', implode('/', $parts));
}

function read_file_or_fail(string $path): string
{
    if (! is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }

    return file_get_contents($path);
}

function backup_file(string $path, string $suffix): void
{
    if (is_file($path)) {
        copy($path, $path.'.bak-'.$suffix);
    }
}

function ensure_contains(string &$content, string $needle, string $insertAfter, string $insertion): void
{
    if (str_contains($content, $needle)) {
        return;
    }

    if (! str_contains($content, $insertAfter)) {
        fwrite(STDERR, "Could not find insertion point: {$insertAfter}\n");
        exit(1);
    }

    $content = str_replace($insertAfter, $insertAfter.$insertion, $content);
}

$suffix = 'warehouse-activity-associations-json-resource-fix-'.date('YmdHis');

$warehouseResourcePath = path_join($root, 'modules/Warehouse/app/Resources/Warehouse.php');
$activityModelPath = path_join($root, 'modules/Activities/app/Models/Activity.php');
$warehouseJsonDir = path_join($root, 'modules/Warehouse/app/Http/Resources');
$warehouseJsonResourcePath = path_join($warehouseJsonDir, 'WarehouseResource.php');
$historyPath = path_join($root, 'docs/ai/04-docops/history/2026-06-28-warehouse-activity-associations-json-resource-fix.md');

backup_file($warehouseResourcePath, $suffix);
backup_file($activityModelPath, $suffix);
backup_file($warehouseJsonResourcePath, $suffix);

if (! is_dir($warehouseJsonDir)) {
    mkdir($warehouseJsonDir, 0775, true);
}

file_put_contents($warehouseJsonResourcePath, <<<'PHP_RESOURCE'
<?php

namespace Modules\Warehouse\Http\Resources;

use Illuminate\Http\Request;
use Modules\Core\Http\Resources\JsonResource;

/** @mixin \Modules\Warehouse\Models\Warehouse */
class WarehouseResource extends JsonResource
{
    /**
     * Transform the Warehouse model into the lightweight shape required by
     * Core association pickers and Resource JSON responses.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name ?: $this->name,
            'path' => '/warehouses/'.$this->id,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
PHP_RESOURCE);

$warehouseResource = read_file_or_fail($warehouseResourcePath);

ensure_contains(
    $warehouseResource,
    'use Modules\\Warehouse\\Http\\Resources\\WarehouseResource as WarehouseJsonResource;',
    "use Modules\\Warehouse\\Models\\Warehouse as WarehouseModel;\n",
    "use Modules\\Warehouse\\Http\\Resources\\WarehouseResource as WarehouseJsonResource;\n"
);

if (! str_contains($warehouseResource, 'public function jsonResource(): string')) {
    $anchor = <<<'PHP_ANCHOR'
    public function globalSearchQuery(ResourceRequest $request): Builder
    {
        return parent::globalSearchQuery($request)->select(['id', 'name', 'code', 'created_at']);
    }
PHP_ANCHOR;

    $method = <<<'PHP_METHOD'

    public function jsonResource(): string
    {
        return WarehouseJsonResource::class;
    }
PHP_METHOD;

    if (! str_contains($warehouseResource, $anchor)) {
        fwrite(STDERR, "Could not find globalSearchQuery anchor in Warehouse resource.\n");
        exit(1);
    }

    $warehouseResource = str_replace($anchor, $anchor.$method, $warehouseResource);
}

file_put_contents($warehouseResourcePath, $warehouseResource);

$activityModel = read_file_or_fail($activityModelPath);

$activityModel = str_replace(
    "return ['contacts', 'companies', 'deals'];",
    "return ['contacts', 'companies', 'deals', 'warehouses'];",
    $activityModel
);

$activityModel = str_replace(
    "foreach (['contacts', 'companies', 'deals'] as $".'relation) {',
    "foreach (['contacts', 'companies', 'deals', 'warehouses'] as $".'relation) {',
    $activityModel
);

// Do not add Warehouse to the next_activity cleanup model list, because the
// Warehouse table does not own next_activity_id / next_activity_date columns.
file_put_contents($activityModelPath, $activityModel);

file_put_contents($historyPath, <<<'MD'
# Warehouse Activity Associations JSON Resource Fix

Status: canonical follow-up fix for Warehouse Activities.

Problem:
- Activity creation worked for Warehouse.
- Opening/editing an Activity association popover called `/api/associations/activities/{id}` and failed with `Class name must be a valid object or a string` in `Resource::createJsonResource()`.

Root cause:
- Once Activity gained the `warehouses()` relation, Core association discovery correctly considered Warehouse an associateable resource for Activity.
- Warehouse Resource did not yet expose a JSON resource class, so Core could not serialize Warehouse records for the association picker.

Fix:
- Add `Modules\Warehouse\Http\Resources\WarehouseResource`.
- Add `Warehouse::jsonResource()` to return that class.
- Extend Activity pivot touch/detach relation lists to include `warehouses`.
- Keep `next_activity_id` cleanup limited to built-in models because the Warehouse table does not include next-activity columns.

Builder/RAG rule:
- Any custom resource that becomes associateable must provide a JSON resource class before it is exposed through Core association endpoints.
MD);

echo "Warehouse activity associations JSON resource fix applied.\n";
