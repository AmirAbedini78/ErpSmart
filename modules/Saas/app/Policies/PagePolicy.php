<?php

namespace Modules\Saas\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Saas\Models\Page;
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
class PagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any pages.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the page.
     */
    public function view(User $user, Page $page): bool
    {
        if ($user->can('view all pages')) {
            return true;
        }

        if ((int) $page->created_by === (int) $user->id) {
            return true;
        }

        if ($user->can('view team pages')) {
            return $user->managesAnyTeamsOf($page->created_by);
        }

        return false;
    }

    /**
     * Determine if the given user can create pages.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the page.
     */
    public function update(User $user, page $page): bool
    {
        if ($user->can('edit all pages')) {
            return true;
        }

        if ($user->can('edit own pages') && (int) $user->id === (int) $page->created_by) {
            return true;
        }

        if ($user->can('edit team pages') && $user->managesAnyTeamsOf($page->created_by)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the page.
     */
    public function delete(User $user, page $page): bool
    {
        if ($user->can('delete own pages') && (int) $user->id === (int) $page->created_by) {
            return true;
        }

        if ($user->can('delete team pages') && $user->managesAnyTeamsOf($page->created_by)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user bulk delete pages.
     */
    public function bulkDelete(User $user, ?page $page = null)
    {
        if (! $page) {
            return $user->can('bulk delete pages');
        }

        if ($page && $user->can('bulk delete pages')) {
            return $this->delete($user, $page);
        }

        return false;
    }

    /**
     * Determine whether the user can export pages.
     */
    public function export(User $user): bool
    {
        return $user->can('export pages');
    }
}
