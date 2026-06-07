<?php

namespace Modules\Saas\Settings;

use Illuminate\Support\Collection;
use Modules\Core\Settings\SettingsMenu as BaseSettingsMenu;

/**
 * An extended settings menu that allows unregistering (removing) items.
 */
class SettingsMenu extends BaseSettingsMenu
{
    protected array $removedIds = [];

    public function forget(string $id): void
    {
        // Add the ID to our list of removed items.
        $this->removedIds[] = $id;

        // Clear the cached resolved items so that the change takes effect immediately.
        $this->resolved = null;
    }

    public function all(): Collection
    {
        return parent::all()->reject(function ($item) {
            // Specify the IDs of the items you want to remove
            return in_array($item->getId(), $this->removedIds);
        });
    }
}
