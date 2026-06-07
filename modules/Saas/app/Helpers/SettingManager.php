<?php

namespace Modules\Saas\Helpers;

use Modules\Core\Environment;
use Modules\Core\Models\Setting;
use Modules\Core\Settings\DefaultSettings;

class SettingManager
{
    protected static $settings = null;

    /**
     * Load settings from JSON if database is not used.
     */
    public static function loadSettings()
    {
        if (self::$settings === null) {
            $driver = config('settings.driver', 'json');
            if ($driver === 'database') {
                self::$settings = Setting::where('tenant_id', app('tenant')->id ?? null)->pluck('value', 'key')->toArray();
            } else {
                $path = storage_path('settings.json');
                self::$settings = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
            }
        }

        return self::$settings;
    }

    /**
     * Get a setting value.
     */
    public static function get($key, $default = null)
    {
        $settings = self::loadSettings();

        return $settings[$key] ?? $default;
    }

    /**
     * Set a setting value and store it accordingly.
     */
    public static function set($key, $value)
    {
        $driver = config('settings.driver', 'json');

        if ($driver === 'database') {
            Setting::updateOrCreate(
                ['tenant_id' => app('tenant')->id ?? null, 'key' => $key],
                ['value' => $value]
            );
        } else {
            $path = storage_path('settings.json');
            $settings = self::loadSettings();
            $settings[$key] = $value;
            file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Seed default settings from JSON to the database.
     */
    public static function seedToDatabase()
    {
        $tenant = app('tenant');
        $settings = DefaultSettings::get();

        $modules = $tenant->modules->where('is_enabled', true);
        if ($modules->count() > 0) {
            foreach ($modules as $module)
            {

                $settings[$module . '_module_active'] = settings()->get($module . '_module_active');
                $settings[$module . '_activation_code'] = settings()->get($module . '_activation_code');
                $settings[$module . '_verification_id'] = settings()->get($module . '_verification_id');
                $settings[$module . '_license_type'] = settings()->get($module . '_license_type');
                $settings[$module . '_last_verified_at'] = settings()->get($module . '_last_verified_at');
                $settings[$module . '_product_token'] = settings()->get($module . '_product_token');
                $settings[$module . '_heartbeat'] = null;
            }
        }

        $settings = array_merge($settings, [
            '_env_captured_at' => now()->toISOString(),
            '_app_url' => config('app.url'),
            '_prev_app_url' => settings('_app_url'),
            '_server_ip' => Environment::getServerIp(),
            '_server_hostname' => gethostname() ?: '',
            '_php_version' => PHP_VERSION,
            '_db_driver' => Environment::getDatabaseDriver(),
            '_db_driver_version' => Environment::getDatabaseDriverVersion(),
            '_version' => \Modules\Core\Application::VERSION,
        ]);

        settings()->set($settings)->save();
    }
}
