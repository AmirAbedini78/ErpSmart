<?php

namespace Modules\Saas\Database\State;

use Illuminate\Support\Facades\DB;

class EnsureDefaultPipelineIsPresent extends \Modules\Deals\Database\State\EnsureDefaultPipelineIsPresent
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        $pipeline = \Modules\Deals\Models\Pipeline::create([
            'name' => 'Sales Pipeline',
            'flag' => 'default',
        ]);

        $pipeline->stages()->createMany($this->stages);
    }

    private function present(): bool
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return DB::table('pipelines')->where('flag', 'default')->when($tenant, function ($query) use ($tenant) {
            return $query->where('tenant_id', $tenant->id);
        })->count() > 0;
    }
}
