<?php

namespace Modules\Saas\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

class CustomQueryBuilder extends Builder
{
    public function insert(array $values): bool
    {
        $table = $this->from;

        if (app()->has('tenant') && Schema::hasColumn($table, 'tenant_id')) {
            $tenant = app('tenant');

            foreach ($values as $key => $value) {
                $value['tenant_id'] = $tenant->id;
                $values[$key] = $value;
            }
        }

        return parent::insert($values);
    }
}
