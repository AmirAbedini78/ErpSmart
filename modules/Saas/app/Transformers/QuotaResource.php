<?php

namespace Modules\Saas\Transformers;

use Illuminate\Http\Request;
use Modules\Core\Resource\JsonResource;

class QuotaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
