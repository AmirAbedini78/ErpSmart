<?php

namespace Modules\Saas\Settings;

use Modules\Core\Settings\SettingsMenu as BaseSettingsMenu;

class SettingMenuStatic extends BaseSettingsMenu {
    protected static array $removedIds = [];

    public static function forget(string $id): void
    {
        // Add the ID to our list of removed items.
        static::$removedIds[] = $id;

        // Clear the cached resolved items so that the change takes effect immediately.
        static::$resolved = null;
    }

    public static function all(): array
    {
        $allItems = static::all();

        foreach ($allItems as $key => $item) {
            if (in_array($item->getId(), static::$removedIds)) {
                unset($allItems[$key]);
            }
        }

        return $allItems;
    }
}
