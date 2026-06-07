<?php

namespace Modules\Saas\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Saas\Models\Tenant;
use Modules\Users\Models\User;

class TenantPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenant $invoice): bool
    {
        if ($user->can('view all tenants')) {
            return true;
        }

        if ($user->can('view all tenant stats')) {
            return true;
        }

        if ($user->can('view own tenants') && (int) $user->id === (int) $invoice->created_by) {
            return true;
        }

        if ($invoice->created_by && $user->can('view team tenants')) {
            return $user->managesAnyTeamsOf($invoice->created_by);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tenant $invoice): bool
    {
        if ($user->can('edit all tenants')) {
            return true;
        }

        if ($user->can('edit own tenants') && (int) $user->id === (int) $invoice->created_by) {
            return true;
        }

        if ($invoice->created_by && $user->can('edit team tenants') && $user->managesAnyTeamsOf($invoice->created_by)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Tenant $invoice): bool
    {
        if ($user->can('delete any tenant')) {
            return true;
        }

        if ($user->can('delete own tenants') && (int) $user->id === (int) $invoice->user_id) {
            return true;
        }

        if ($invoice->user_id && $user->can('delete team tenants') && $user->managesAnyTeamsOf($invoice->user_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user bulk delete tenants.
     */
    public function bulkDelete(User $user, ?Tenant $invoice = null)
    {
        if (! $invoice) {
            return $user->can('bulk delete tenants');
        }

        if ($invoice && $user->can('bulk delete tenants')) {
            return $this->delete($user, $invoice);
        }

        return false;
    }
}
