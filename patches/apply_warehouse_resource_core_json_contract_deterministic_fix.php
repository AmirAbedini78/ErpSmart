<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$resourceFile = $root.'/modules/Warehouse/app/Http/Resources/WarehouseResource.php';
$warehouseResourceFile = $root.'/modules/Warehouse/app/Resources/Warehouse.php';
$historyFile = $root.'/docs/ai/04-docops/history/2026-06-29-warehouse-resource-core-json-contract-deterministic-fix.md';

function ensure_dir(string $path): void
{
    if (! is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function backup_file(string $file, string $suffix): ?string
{
    if (! file_exists($file)) {
        return null;
    }

    $backup = $file.'.bak-'.$suffix.'-'.date('YmdHis');
    copy($file, $backup);

    return $backup;
}

function replace_or_insert_json_resource_method(string $contents): string
{
    $method = <<<'PHP'
    public function jsonResource(): string
    {
        return WarehouseResource::class;
    }

PHP;

    $pattern = '/\n\s*public\s+function\s+jsonResource\s*\(\s*\)\s*:\s*string\s*\{.*?\n\s*\}\s*/s';

    if (preg_match($pattern, $contents)) {
        return preg_replace($pattern, "\n".$method, $contents, 1);
    }

    $anchor = '    public function fields(ResourceRequest $request): array';
    $pos = strpos($contents, $anchor);

    if ($pos === false) {
        throw new RuntimeException('Could not find fields() anchor in Warehouse resource.');
    }

    return substr($contents, 0, $pos).$method.substr($contents, $pos);
}

if (! file_exists($warehouseResourceFile)) {
    throw new RuntimeException('Missing Warehouse Resource file: '.$warehouseResourceFile);
}

ensure_dir(dirname($resourceFile));
ensure_dir(dirname($historyFile));

$warehouseResourceBackup = backup_file($warehouseResourceFile, 'core-json-deterministic');
$resourceBackup = backup_file($resourceFile, 'core-json-deterministic');

$resourceContents = <<<'PHP'
<?php

namespace Modules\Warehouse\Http\Resources;

use Illuminate\Http\Request;
use Modules\Core\Resource\JsonResource;

class WarehouseResource extends JsonResource
{
    /**
     * Transform the Warehouse resource into an array.
     *
     * This class must extend Modules\Core\Resource\JsonResource, not Laravel's
     * plain JsonResource, because Concord/ErpSmart calls Core resource helpers
     * such as withActions(), withFields(), withAuthorizations(), etc.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name ?? $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'import_id' => $this->import_id,
            'media' => $this->whenLoaded('media'),
            'media_count' => $this->relationLoaded('media') ? $this->media->count() : 0,
            'activities' => $this->whenLoaded('activities'),
            'incomplete_activities_for_user_count' => (int) ($this->incomplete_activities_for_user_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
PHP;

file_put_contents($resourceFile, $resourceContents."\n");

$warehouseResourceContents = file_get_contents($warehouseResourceFile);

if (strpos($warehouseResourceContents, 'use Modules\Warehouse\Http\Resources\WarehouseResource;') === false) {
    $warehouseResourceContents = preg_replace(
        '/namespace Modules\\Warehouse\\Resources;\s*/',
        "namespace Modules\\Warehouse\\Resources;\n\nuse Modules\\Warehouse\\Http\\Resources\\WarehouseResource;\n",
        $warehouseResourceContents,
        1
    );
}

$warehouseResourceContents = replace_or_insert_json_resource_method($warehouseResourceContents);
file_put_contents($warehouseResourceFile, $warehouseResourceContents);

$history = <<<'MD'
# Warehouse Resource Core JSON Contract Deterministic Fix

WarehouseResource must extend `Modules\Core\Resource\JsonResource` rather than Laravel's plain JsonResource.

Reason:
- Concord/ErpSmart `ResourceRequest::toResponse()` calls Core helper methods such as `withActions()` on the resource response.
- A plain Laravel JsonResource does not provide those methods and throws `Call to undefined method ...::withActions()`.

This patch intentionally overwrites `modules/Warehouse/app/Http/Resources/WarehouseResource.php` deterministically instead of using fragile regex replacements.
MD;

file_put_contents($historyFile, $history."\n");

echo "WarehouseResource deterministic Core JSON contract fix applied.\n";
if ($resourceBackup) {
    echo "WarehouseResource backup: {$resourceBackup}\n";
}
if ($warehouseResourceBackup) {
    echo "Warehouse Resource backup: {$warehouseResourceBackup}\n";
}
