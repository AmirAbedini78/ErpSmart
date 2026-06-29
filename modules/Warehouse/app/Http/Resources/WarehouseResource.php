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