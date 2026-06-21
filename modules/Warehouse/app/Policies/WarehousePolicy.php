<?php

namespace Modules\Warehouse\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Users\Models\User;
use Modules\Warehouse\Models\Warehouse;

class WarehousePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can access the warehouse list/API index.
     *
     * Warehouse is global master data. It does not have owner/team columns yet,
     * therefore only the global "view all warehouses" permission is meaningful.
     */
    public function viewAny(User $user): bool
    {
        return $this->canAny($user, [
            'view all warehouses',
        ]);
    }

    /**
     * Determine whether the user can view a warehouse record.
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->canAny($user, [
            'view all warehouses',
        ]);
    }

    /**
     * Determine whether the user can create warehouses.
     */
    public function create(User $user): bool
    {
        return $this->canAny($user, [
            'create warehouses',
        ]);
    }

    /**
     * Determine whether the user can update a warehouse.
     */
    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->canAny($user, [
            'edit all warehouses',
        ]);
    }

    /**
     * Determine whether the user can delete a warehouse.
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $this->canAny($user, [
            'delete any warehouse',
        ]);
    }

    /**
     * Determine whether the user can bulk delete warehouses.
     */
    public function bulkDelete(User $user, ?Warehouse $warehouse = null): bool
    {
        if (! $this->canAny($user, ['bulk delete warehouses'])) {
            return false;
        }

        return $warehouse ? $this->delete($user, $warehouse) : true;
    }

    /**
     * Determine whether the user can export warehouses.
     */
    public function export(User $user): bool
    {
        return $this->canAny($user, [
            'export warehouses',
        ]);
    }

    /**
     * Reserved for future Warehouse-specific import endpoints.
     *
     * The Core Resource JSON currently exposes import when the resource is
     * Importable and Gate::allows('create', Warehouse::class) is true. The
     * separate permission is registered so the Builder has an explicit import
     * capability to map in future versions.
     */
    public function import(User $user): bool
    {
        return $this->canAny($user, [
            'import warehouses',
        ]);
    }

    /**
     * Check one or more permission names without duplicating policy code.
     */
    protected function canAny(User $user, array $permissions): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
