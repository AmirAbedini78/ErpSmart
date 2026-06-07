<?php

namespace Modules\Saas\Database\State;

use Illuminate\Support\Facades\DB;

class EnsureDefaultBrandIsPresent extends \Modules\Brands\Database\State\EnsureDefaultBrandIsPresent
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        \Modules\Brands\Models\Brand::create([
            'name' => config('app.name'),
            'display_name' => config('app.name'),
            'is_default' => true,
            'config' => [
                'primary_color' => '#4f46e5',
            ],
        ]);
    }

    private function present(): bool
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return DB::table('brands')
            ->where('is_default', true)
            ->when($tenant, function ($query) use ($tenant) {
                return $query->where('tenant_id', $tenant->id);
            })
            ->count() > 0;
    }
}
