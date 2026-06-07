<?php

namespace Modules\Saas\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Billable\Models\Product;
use Modules\Saas\Models\Package;
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
class PackagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any packages.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Package $package): bool
    {
        if ($user->can('view all packages')) {
            return true;
        }
        if ($user->can('view all products')) {
            return true;
        }
        if ((int) $package->created_by === (int) $user->id) {
            return true;
        }

        if ($user->can('view team products')) {
            return $user->managesAnyTeamsOf($package->created_by);
        }

        if ((int) $package->created_by === (int) $user->id) {
            return true;
        }

        if ($user->can('view team packages')) {
            return $user->managesAnyTeamsOf($package->created_by);
        }

        return false;
    }

    /**
     * Determine if the given user can create packages.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Package $product): bool
    {
        if ($user->can('edit all packages')) {
            return true;
        }

        if ($user->can('edit own packages') && (int) $user->id === (int) $product->created_by) {
            return true;
        }

        if ($user->can('edit team packages') && $user->managesAnyTeamsOf($product->created_by)) {
            return true;
        }

        if ($user->can('edit all products')) {
            return true;
        }

        if ($user->can('edit own products') && (int) $user->id === (int) $product->created_by) {
            return true;
        }

        if ($user->can('edit team products') && $user->managesAnyTeamsOf($product->created_by)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Package $product): bool
    {
        if ($user->can('delete any product')) {
            return true;
        }

        if ($user->can('delete own packages') && (int) $user->id === (int) $product->created_by) {
            return true;
        }

        if ($user->can('delete team packages') && $user->managesAnyTeamsOf($product->created_by)) {
            return true;
        }

        if ($user->can('delete any product')) {
            return true;
        }

        if ($user->can('delete own products') && (int) $user->id === (int) $product->created_by) {
            return true;
        }

        if ($user->can('delete team products') && $user->managesAnyTeamsOf($product->created_by)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user bulk delete packages.
     */
    public function bulkDelete(User $user, ?Package $product = null)
    {
        if (! $product) {
            return $user->can('bulk delete packages');
        }

        if ($product && $user->can('bulk delete packages')) {
            return $this->delete($user, $product);
        }

        return false;
    }

    /**
     * Determine whether the user can export packages.
     */
    public function export(User $user): bool
    {
        return $user->can('export packages');
    }
}
