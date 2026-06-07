<?php

namespace Modules\Saas\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;
use Modules\Saas\Enums\TenantDatabaseProvision;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        if (! $this->hasTenantColumn($model->getTable())) {
            return;
        }

        if (! $tenant) {
            $builder->whereNull($model->getTable().'.tenant_id');

            return;
        }

        $tenant->loadMissing('package');
        $package = $tenant->package;
        $dbScheme = $tenant->db_scheme == TenantDatabaseProvision::USE_FROM_PACKAGE ? $package->db_scheme : $tenant->db_scheme;

        if ($dbScheme !== TenantDatabaseProvision::USE_CURRENT_ACTIVE || ! $this->hasTenantColumn($model->getTable())) {
            return;
        }

        $builder->where($model->getTable().'.tenant_id', $tenant->id);
    }

    private function hasTenantColumn(string $table): bool
    {
        config()->has('database.table.'.$table) ?: config()->set('database.table.'.$table, Schema::hasColumn($table, 'tenant_id'));

        return config()->get('database.table.'.$table);
    }
}
