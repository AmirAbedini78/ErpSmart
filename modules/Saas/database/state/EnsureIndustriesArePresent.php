<?php

namespace Modules\Saas\Database\State;

use Illuminate\Support\Facades\DB;

class EnsureIndustriesArePresent extends \Modules\Contacts\Database\State\EnsureIndustriesArePresent
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        foreach ($this->industries as $industry) {
            \Modules\Contacts\Models\Industry::create(['name' => $industry]);
        }
    }

    private function present(): bool
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return DB::table('industries')->when($tenant, function ($query) use ($tenant) {
            return $query->where('tenant_id', $tenant->id);
        })->count() > 0;
    }
}
