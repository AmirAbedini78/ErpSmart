<?php

namespace Modules\Saas\Database\State;

use Illuminate\Support\Facades\DB;

class EnsureCallOutcomesArePresent extends \Modules\Calls\Database\State\EnsureCallOutcomesArePresent
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        foreach ($this->outcomes as $name => $color) {
            \Modules\Calls\Models\CallOutcome::create(['name' => $name, 'swatch_color' => $color]);
        }
    }

    private function present(): bool
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return DB::table('call_outcomes')->when($tenant, function ($query) use ($tenant) {
            return $query->where('tenant_id', $tenant->id);
        })->count() > 0;
    }
}
