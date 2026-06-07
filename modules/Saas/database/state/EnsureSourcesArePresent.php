<?php

namespace Modules\Saas\Database\State;

use Illuminate\Support\Facades\DB;

class EnsureSourcesArePresent extends \Modules\Contacts\Database\State\EnsureSourcesArePresent
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        foreach ($this->sources as $source) {
            \Modules\Contacts\Models\Source::create([
                'name' => $source,
                'flag' => $source === 'Web Form' ? 'web-form' : null,
            ]);
        }
    }

    private function present(): bool
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return DB::table('sources')->when($tenant, function ($query) use ($tenant) {
            return $query->where('tenant_id', $tenant->id);
        })->count() > 0;
    }
}
