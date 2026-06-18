<?php

namespace Modules\Warehouse\Policies;

use Modules\Users\Models\User;
use Modules\Warehouse\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return true;
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return true;
    }
}
