<?php
/**
 * Fix WarehouseResource to extend Concord/Core Resource JsonResource instead of
 * Laravel's plain Illuminate JsonResource.
 *
 * Why: ResourceRequest::toResponse() decorates returned resource objects with
 * Core methods such as withActions(). Plain Illuminate JsonResource does not have
 * that method, so /api/warehouses/{id} fails after adding a custom jsonResource().
 */

$root = dirname(__DIR__);
$resourcePath = $root.'/modules/Warehouse/app/Http/Resources/WarehouseResource.php';
$resourceDir = dirname($resourcePath);

if (! is_dir($resourceDir)) {
    mkdir($resourceDir, 0775, true);
}

if (! file_exists($resourcePath)) {
    $content = <<<'PHP'
<?php

namespace Modules\Warehouse\Http\Resources;

use Illuminate\Http\Request;
use Modules\Core\Http\Resources\MediaResource;
use Modules\Core\Resource\JsonResource;

class WarehouseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'display_name' => $this->name,
            'path' => '/warehouses/'.$this->id,
            'notes' => $this->whenLoaded('notes'),
            'notes_count' => (int) ($this->notes_count ?? 0),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'media_count' => (int) ($this->media_count ?? ($this->relationLoaded('media') ? $this->media->count() : 0)),
            'activities' => $this->whenLoaded('activities'),
            'incomplete_activities_for_user_count' => (int) ($this->incomplete_activities_for_user_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
PHP;

    file_put_contents($resourcePath, $content);
    echo "WarehouseResource created with Core JsonResource contract.\n";
    exit(0);
}

$content = file_get_contents($resourcePath);
$backup = $resourcePath.'.bak-core-json-contract-'.date('YmdHis');
copy($resourcePath, $backup);

// Remove wrong imports that make the resource extend Laravel's plain JsonResource.
$content = preg_replace('/^use\s+Illuminate\\Http\\Resources\\Json\\JsonResource;\s*\R/m', '', $content);
$content = preg_replace('/^use\s+Modules\\Core\\Http\\Resources\\JsonResource;\s*\R/m', '', $content);

// Ensure the correct Core Resource JsonResource is imported.
if (! preg_match('/^use\s+Modules\\Core\\Resource\\JsonResource;\s*$/m', $content)) {
    $content = preg_replace('/^(namespace\s+Modules\\Warehouse\\Http\\Resources;\s*\R)/m', "$1\nuse Modules\\Core\\Resource\\JsonResource;\n", $content, 1);
}

// Keep MediaResource import if the file references it.
if (str_contains($content, 'MediaResource::') && ! preg_match('/^use\s+Modules\\Core\\Http\\Resources\\MediaResource;\s*$/m', $content)) {
    $content = preg_replace('/^(namespace\s+Modules\\Warehouse\\Http\\Resources;\s*\R(?:\R|use\s+[^;]+;\s*\R)*)/m', "$1use Modules\\Core\\Http\\Resources\\MediaResource;\n", $content, 1);
}

// Normalize class extension to the imported Core JsonResource alias.
$content = preg_replace('/class\s+WarehouseResource\s+extends\s+\\?Illuminate\\Http\\Resources\\Json\\JsonResource\b/', 'class WarehouseResource extends JsonResource', $content);
$content = preg_replace('/class\s+WarehouseResource\s+extends\s+\\?Modules\\Core\\Http\\Resources\\JsonResource\b/', 'class WarehouseResource extends JsonResource', $content);
$content = preg_replace('/class\s+WarehouseResource\s+extends\s+\\?Modules\\Core\\Resource\\JsonResource\b/', 'class WarehouseResource extends JsonResource', $content);

// If the class extends a bare JsonResource, the corrected import above controls it.
if (! preg_match('/class\s+WarehouseResource\s+extends\s+JsonResource\b/', $content)) {
    $content = preg_replace('/class\s+WarehouseResource(\s*\{)/', 'class WarehouseResource extends JsonResource$1', $content, 1);
}

file_put_contents($resourcePath, $content);

echo "WarehouseResource Core JSON contract fix applied.\n";
echo "Backup: {$backup}\n";
