<?php

namespace Modules\Saas\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Saas\Models\Quota;
use Modules\Users\Models\User;

/**
 * Concord CRM - https://www.concordcrm.com
 *
 * @version   1.5.1
 *
 * @link      Releases - https://www.concordcrm.com/releases
 * @link      Terms Of Service - https://www.concordcrm.com/terms
 *
 * @copyright Copyright (c) 2022-2024 KONKORD DIGITAL
 */
class QuotaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any quotas.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Quota $quota): bool
    {
        if ($user->can('view all quotas')) {
            return true;
        }

        if ((int) $quota->created_by === (int) $user->id) {
            return true;
        }

        if ($user->can('view team quotas')) {
            return $user->managesAnyTeamsOf($quota->created_by);
        }

        return false;
    }

    /**
     * Determine if the given user can create quotas.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Quota $quota): bool
    {
        if ($user->can('edit all quotas')) {
            return true;
        }

        if ($user->can('edit own quotas') && (int) $user->id === (int) $quota->created_by) {
            return true;
        }

        if ($user->can('edit team quotas') && $user->managesAnyTeamsOf($quota->created_by)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Quota $quota): bool
    {
        if ($user->can('delete any product')) {
            return true;
        }

        if ($user->can('delete own quotas') && (int) $user->id === (int) $quota->created_by) {
            return true;
        }

        if ($user->can('delete team quotas') && $user->managesAnyTeamsOf($quota->created_by)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user bulk delete quotas.
     */
    public function bulkDelete(User $user, ?Quota $quota = null)
    {
        if (! $quota) {
            return $user->can('bulk delete quotas');
        }

        if ($quota && $user->can('bulk delete quotas')) {
            return $this->delete($user, $quota);
        }

        return false;
    }

    /**
     * Determine whether the user can export quotas.
     */
    public function export(User $user): bool
    {
        return $user->can('export quotas');
    }
}
