<?php

namespace Modules\Saas\Database\State;

use Illuminate\Support\Facades\DB;

class EnsureActivityTypesArePresent extends \Modules\Activities\Database\State\EnsureActivityTypesArePresent
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        foreach ($this->types as $name => $options) {
            $model = \Modules\Activities\Models\ActivityType::create([
                'name' => $name,
                'swatch_color' => $options[0],
                'icon' => $options[1],
                'flag' => strtolower($name),
            ]);

            if ($model->flag === 'task') {
                $model::setDefault($model->getKey());
            }
        }
    }

    private function present(): bool
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return DB::table('activity_types')->when($tenant, function ($query) use ($tenant) {
            return $query->where('tenant_id', $tenant->id);
        })->count() > 0;
    }
}
