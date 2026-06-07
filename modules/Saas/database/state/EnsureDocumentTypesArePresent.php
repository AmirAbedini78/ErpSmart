<?php

namespace Modules\Saas\Database\State;

use Illuminate\Support\Facades\DB;

class EnsureDocumentTypesArePresent extends \Modules\Documents\Database\State\EnsureDocumentTypesArePresent
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        foreach ($this->types as $name => $color) {
            $model = \Modules\Documents\Models\DocumentType::create([
                'name' => $name,
                'swatch_color' => $color,
                'flag' => strtolower($name),
            ]);

            if ($model->flag === 'proposal') {
                $model::setDefault($model->getKey());
            }
        }
    }

    public function present(): bool
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return DB::table('document_types')->when($tenant, function ($query) use ($tenant) {
            return $query->where('tenant_id', $tenant->id);
        })->count() > 0;
    }
}
