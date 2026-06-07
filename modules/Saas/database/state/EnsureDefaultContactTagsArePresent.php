<?php

namespace Modules\Saas\Database\State;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tag;

class EnsureDefaultContactTagsArePresent extends \Modules\Contacts\Database\State\EnsureDefaultContactTagsArePresent
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        foreach ($this->tags as $tag => $color) {
            $tag = Tag::findOrCreate($tag, 'contacts');

            $tag->swatch_color = $color;

            $tag->save();
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
