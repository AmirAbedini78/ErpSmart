<?php

/**
 * Final deterministic fix for Warehouse detail 500 caused by an incorrect
 * Warehouse jsonResource contract.
 *
 * It aligns Warehouse with Concord/Core resource architecture:
 * - Warehouse Resource imports Modules\Warehouse\Http\Resources\WarehouseResource
 * - jsonResource() returns WarehouseResource::class
 * - WarehouseResource extends Modules\Core\Resource\JsonResource
 * - WarehouseResource::toArray() calls withCommonData(...)
 */

$root = dirname(__DIR__);
$resourcePath = $root.'/modules/Warehouse/app/Resources/Warehouse.php';
$jsonResourcePath = $root.'/modules/Warehouse/app/Http/Resources/WarehouseResource.php';
$historyPath = $root.'/docs/ai/04-docops/history/2026-06-29-warehouse-detail-core-json-resource-contract-final-fix.md';

function backup_file(string $path, string $suffix): void
{
    if (is_file($path)) {
        copy($path, $path.'.bak-'.$suffix.'-'.date('YmdHis'));
    }
}

function replace_method(string $code, string $methodName, string $replacement, string $insertBeforeNeedle = ''): string
{
    $needle = 'public function '.$methodName.'(';
    $start = strpos($code, $needle);

    if ($start === false) {
        if ($insertBeforeNeedle !== '' && ($insertAt = strpos($code, $insertBeforeNeedle)) !== false) {
            return substr($code, 0, $insertAt).$replacement."\n".substr($code, $insertAt);
        }

        $lastBrace = strrpos($code, '}');
        if ($lastBrace === false) {
            throw new RuntimeException('Cannot insert method; class closing brace not found.');
        }

        return substr($code, 0, $lastBrace)."\n".$replacement."\n".substr($code, $lastBrace);
    }

    $brace = strpos($code, '{', $start);
    if ($brace === false) {
        throw new RuntimeException('Cannot find opening brace for '.$methodName.' method.');
    }

    $depth = 0;
    $length = strlen($code);
    for ($i = $brace; $i < $length; $i++) {
        if ($code[$i] === '{') {
            $depth++;
        } elseif ($code[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($code, 0, $start).$replacement.substr($code, $i + 1);
            }
        }
    }

    throw new RuntimeException('Cannot find closing brace for '.$methodName.' method.');
}

if (! is_file($resourcePath)) {
    throw new RuntimeException('Missing Warehouse Resource: '.$resourcePath);
}

$suffix = 'detail-core-json-final';
backup_file($resourcePath, $suffix);
backup_file($jsonResourcePath, $suffix);

$resourceCode = file_get_contents($resourcePath);

// Normalize broken/aliased imports from previous attempts.
$resourceCode = str_replace("use Modules\\Warehouse\\Http\\Resources\\WarehouseResource as WarehouseJsonResource;\n", '', $resourceCode);
$resourceCode = str_replace("use Modules\\Warehouse\\Http\\Resources\\WarehouseResource;\n", '', $resourceCode);

$warehouseModelImport = "use Modules\\Warehouse\\Models\\Warehouse as WarehouseModel;\n";
$warehouseResourceImport = "use Modules\\Warehouse\\Http\\Resources\\WarehouseResource;\n";

if (strpos($resourceCode, $warehouseModelImport) !== false) {
    $resourceCode = str_replace($warehouseModelImport, $warehouseResourceImport.$warehouseModelImport, $resourceCode);
} else {
    $namespaceLine = "namespace Modules\\Warehouse\\Resources;\n\n";
    if (strpos($resourceCode, $namespaceLine) === false) {
        throw new RuntimeException('Cannot locate Warehouse Resource namespace block.');
    }
    $resourceCode = str_replace($namespaceLine, $namespaceLine.$warehouseResourceImport, $resourceCode);
}

$jsonResourceMethod = <<<'PHP'
    public function jsonResource(): string
    {
        return WarehouseResource::class;
    }

PHP;

$resourceCode = replace_method(
    $resourceCode,
    'jsonResource',
    $jsonResourceMethod,
    'public static function label()'
);

$resourceCode = str_replace("\npublic static function label()", "\n    public static function label()", $resourceCode);
file_put_contents($resourcePath, $resourceCode);

$jsonResourceCode = <<<'PHP'
<?php

namespace Modules\Warehouse\Http\Resources;

use Illuminate\Http\Request;
use Modules\Core\Resource\JsonResource;

class WarehouseResource extends JsonResource
{
    /**
     * Transform the Warehouse resource into an array.
     *
     * Concord/Core calls resource helpers such as withActions() on JSON
     * resources returned from Resource::jsonResource(). Therefore this class
     * must extend Modules\Core\Resource\JsonResource and must pass its data
     * through withCommonData() just like first-party module resources do.
     */
    public function toArray(Request $request): array
    {
        return $this->withCommonData([
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'import_id' => $this->import_id,
            'media' => $this->whenLoaded('media'),
            'media_count' => $this->relationLoaded('media') ? $this->media->count() : 0,
            'activities' => $this->whenLoaded('activities'),
            'incomplete_activities_for_user_count' => (int) ($this->incomplete_activities_for_user_count ?? 0),
        ], $request);
    }
}
PHP;

if (! is_dir(dirname($jsonResourcePath))) {
    mkdir(dirname($jsonResourcePath), 0775, true);
}
file_put_contents($jsonResourcePath, $jsonResourceCode);

$history = <<<'MD'
# Warehouse Detail Core JSON Resource Contract Final Fix

The Warehouse detail endpoint `/api/warehouses/{id}` must use the same JSON Resource contract as first-party Concord modules.

Root cause fixed:
- Earlier attempts created `Modules\Warehouse\Http\Resources\WarehouseResource`, but `modules/Warehouse/app/Resources/Warehouse.php` imported it as `WarehouseJsonResource` and still returned `WarehouseResource::class`.
- In the `Modules\Warehouse\Resources` namespace, that class reference does not reliably point to the HTTP JSON resource class.
- The JSON resource must extend `Modules\Core\Resource\JsonResource`, not Laravel's plain JSON resource.
- The JSON resource must call `withCommonData()` so Core can merge fields, authorizations, path, timestamps, and association counts.

Fix:
- Normalize the Warehouse Resource import to `use Modules\Warehouse\Http\Resources\WarehouseResource;`.
- Make `jsonResource()` return `WarehouseResource::class` using the imported class.
- Rewrite `WarehouseResource` to extend `Modules\Core\Resource\JsonResource` and call `withCommonData()`.

After applying, clear Laravel caches and restart app/nginx before testing `/warehouses/{id}`.
MD;
file_put_contents($historyPath, $history);

echo "Warehouse detail Core JSON Resource contract final fix applied.\n";
echo "Backups created with suffix: bak-{$suffix}-".date('YmdHis')."\n";
