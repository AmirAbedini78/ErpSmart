<?php
/**
 * Concord CRM - https://www.concordcrm.com
 *
 * @version   1.7.0
 *
 * @link      Releases - https://www.concordcrm.com/releases
 * @link      Terms Of Service - https://www.concordcrm.com/terms
 *
 * @copyright Copyright (c) 2022-2025 KONKORD DIGITAL
 */

namespace Modules\Core;

use Akaunting\Money\Currency;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Modules\Core\Concerns\ExecutesCommands;
use Modules\Core\Facades\MailableTemplates;
use Modules\Core\Facades\Module;
use Modules\Core\Facades\Permissions;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Resource;
use Modules\Updater\DatabaseMigrator;
use Modules\Updater\UpdateFinalizer;
use Symfony\Component\Process\PhpExecutableFinder;

class Application
{
    use ExecutesCommands;

    /**
     * The application version.
     *
     * @var string
     */
    const VERSION = '1.7.0';

    /**
     * System name that will be used over the system.
     *
     * E.q. for automated actions performed by the application or logs
     *
     * @var string
     */
    const SYSTEM_NAME = 'System';

    /**
     * The API prefix for the application.
     *
     * @var string
     */
    const API_PREFIX = 'api';

    /**
     * Requires maintenance checks cache.
     */
    protected static ?bool $requiresMaintenance = null;

    /**
     * Registered resources.
     */
    protected static ?Collection $resources = null;

    /**
     * Cache of resource names keyed by the model name.
     */
    protected static array $resourcesByModel = [];

    /**
     * Provide data to views.
     */
    protected static array $provideToScript = [];

    /**
     * All the additionally registered vite entrypoints.
     */
    protected static array $vite = [];

    /**
     * All the registered notifications.
     */
    protected static array $notifications = [];

    /**
     * All the registered tools.
     */
    protected static array $tools = [];

    /**
     * All the custom registered scripts.
     */
    protected static array $scripts = [];

    /**
     * All the custom registered styles.
     */
    protected static array $styles = [];

    /**
     * Indicates whether notifications are disabled.
     */
    public static bool $disableNotifications = false;

    /**
     * The the system name.
     */
    public static function systemName(): string
    {
        return static::SYSTEM_NAME;
    }

    /**
     * Call the booting callbacks for the application.
     */
    protected function fireAppCallbacks(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $this);
        }
    }

    /**
     * Get the application url.
     */
    public static function url(): string
    {
        return url('/');
    }

    /**
     * Get the application API url.
     */
    public static function apiUrl(): string
    {
        return static::url().'/'.static::API_PREFIX;
    }

    /**
     * Register tools.
     */
    public static function tools(callable $callback): void
    {
        static::$tools[] = $callback;
    }

    /**
     * Get all of the registered tools.
     */
    public static function getTools(): Collection
    {
        return collect(static::$tools)->map(function (callable $provider) {
            return call_user_func($provider);
        })->flatten(1);
    }

    /**
     * Register the given notifications.
     */
    public static function notifications(array|string $notifications): void
    {
        static::$notifications = array_unique(
            array_merge(static::$notifications, (array) $notifications)
        );
    }

    /**
     * Get all of the registered notifications.
     *
     * @return class-string<\Modules\Core\Notification>[]
     */
    public static function getRegisteredNotifications(): array
    {
        return static::$notifications;
    }

    /**
     * Register new permissions group.
     */
    public static function permissions(string|Closure $group, ?Closure $callback = null): void
    {
        if ($group instanceof Closure) {
            Permissions::register($group);
        } else {
            Permissions::group($group, $callback);
        }
    }

    /**
     * Register mailable templates.
     */
    public static function mailableTemplates(string|array $mailableTemplates): void
    {
        MailableTemplates::register($mailableTemplates);
    }

    /**
     * Get the application favourite colors.
     */
    public static function favouriteColors(): array
    {
        return config('core.colors', []);
    }

    /**
     * Check if the application is installed.
     */
    public static function isInstalled(): bool
    {
        return \Modules\Installer\Installer::isAppInstalled();
    }

    /**
     * Get the available registered resources names.
     *
     * @return string[]
     */
    public static function getResourcesNames(): array
    {
        return static::registeredResources()->map(
            fn (Resource $resource) => $resource->name()
        )->all();
    }

    /**
     * Get all the registered resources.
     *
     * @return \Illuminate\Support\Collection<object, \Modules\Core\Resource\Resource>
     */
    public static function registeredResources()
    {
        return is_null(static::$resources) ? collect([]) : static::$resources;
    }

    /**
     * Get the resource class by a given name.
     */
    public static function resourceByName(string $name): ?Resource
    {
        return static::registeredResources()->first(
            fn (Resource $resource) => $resource::name() === $name
        );
    }

    /**
     * Get the resource class by a given model.
     */
    public static function resourceByModel(string|Model $model): ?Resource
    {
        if (is_object($model)) {
            $model = $model::class;
        }

        if (isset(static::$resourcesByModel[$model])) {
            return static::$resourcesByModel[$model];
        }

        return static::$resourcesByModel[$model] = static::registeredResources()->first(
            fn (Resource $value) => $value::$model === $model
        );
    }

    /**
     * Get the globally searchable resources.
     *
     * @return \Illuminate\Support\Collection<object, \Modules\Core\Resource\Resource>
     */
    public static function globallySearchableResources()
    {
        return static::registeredResources()->filter(
            fn (Resource $resource) => $resource::$globallySearchable
        );
    }

    /**
     * Register the given resources.
     *
     * @param  \Modules\Core\Resource\Resource[]  $resources
     */
    public static function resources(array $resources): void
    {
        static::$resources = static::registeredResources()
            ->merge($resources)->unique(function (string|Resource $resource) {
                return is_string($resource) ? $resource : $resource::class;
            })->map(function (string|Resource $resource) {
                return is_string($resource) ? new $resource : $resource;
            })->sortBy(fn (Resource $resource) => $resource::name());
    }

    /**
     * Provide data to front-end.
     */
    public static function provideToScript(array|Closure $data): void
    {
        if ($data instanceof Closure) {
            static::$provideToScript[] = $data;
        } else {
            static::$provideToScript = array_merge(static::$provideToScript, $data);
        }
    }

    /**
     * Get the data provided to script.
     */
    public static function getDataProvidedToScript(): array
    {
        return collect(static::$provideToScript)->mapWithKeys(function ($value, $key) {
            if ($value instanceof Closure) {
                $result = $value();

                return is_array($result) ? $result : [$key => $result];
            }

            return [$key => $value];
        })->toArray();
    }

    /**
     * Register vite entrypoint.
     */
    public static function vite(string|array $entryPoints, string|array $config): void
    {
        $buildDirectory = is_array($config) ? $config['buildDirectory'] : $config;

        static::$vite[$buildDirectory] = [
            'entryPoints' => (array) $entryPoints,
            'buildDirectory' => $buildDirectory,
            'hotFile' => is_array($config) && array_key_exists('hotFile', $config) ? $config['hotFile'] : storage_path('hot'),
        ];
    }

    /**
     * Get all of the Vite scripts for output.
     */
    public static function viteOutput(): string
    {
        $output = '';

        foreach (static::viteEntryPoints() as $data) {
            $vite = Vite::useHotFile($data['hotFile'])
                ->useBuildDirectory($data['buildDirectory'])
                ->useManifestFilename($manifestFileName = 'manifest.json')
                ->withEntryPoints($data['entryPoints']);

            if (is_file(public_path($data['buildDirectory'].DIRECTORY_SEPARATOR.$manifestFileName)) || is_file($data['hotFile'])) {
                $output .= $vite->toHtml();
            }
        }

        return $output;
    }

    /**
     * Determine if Vite HMR is running.
     */
    public static function isRunningViteHot(): bool
    {
        if (is_file(public_path('hot'))) {
            return true;
        }

        foreach (static::viteEntryPoints() as $data) {
            if (is_file($data['hotFile'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all of the additionally registered Vite entrypoints.
     */
    public static function viteEntryPoints(): array
    {
        return static::$vite;
    }

    /**
     * Get the Vue script src.
     */
    public static function vueSrc(): string
    {
        // ERPSMART: keep the application fully offline-capable.
        // The global Vue build is required by @concordcrm/vite-plugin-global-vue.
        // Loading it from unpkg makes the whole UI fail when internet/CDN access is unavailable.
        // See docs/ai/03-architecture/offline-frontend-assets.md.
        if (app()->isProduction() || ! static::isRunningViteHot()) {
            return asset('vendor/vue/vue.global.prod.js');
        }

        return asset('vendor/vue/vue.global.js');
    }

    /**
     * Register the given script file with the application.
     */
    public static function script(string $name, string $path): void
    {
        static::$scripts[$name] = $path;
    }

    /**
     * Get all of the additional registered scripts.
     */
    public static function scripts(): array
    {
        return static::$scripts;
    }

    /**
     * Register the given CSS file with the application.
     */
    public static function style(string $name, string $path): void
    {
        static::$styles[$name] = $path;
    }

    /**
     * Get all of the additional registered stylesheets.
     */
    public static function styles(): array
    {
        return static::$styles;
    }

    /**
     * Get the application currency.
     */
    public static function currency(string|Currency|null $currency = null): Currency
    {
        if ($currency instanceof Currency) {
            return $currency;
        }

        return new Currency($currency ?: config('core.currency') ?: 'USD');
    }

    /**
     * Get the application allowed extensions for upload.
     */
    public static function allowedUploadExtensions(): array
    {
        // Replace dots with empty in case the user add dot in the extension name
        return array_map(
            fn ($extension) => trim(Str::replaceFirst('.', '', $extension)),
            explode(',', settings('allowed_extensions') ?: '')
        );
    }

    /**
     * Check whether the app is ready for serving.
     */
    public static function readyForServing(): bool
    {
        return static::isInstalled() && ! static::requiresUpdateFinalization();
    }

    /**
     * Check whether the app is ready for serving.
     */
    public static function whenReadyForServing(callable $callback): void
    {
        if (static::readyForServing()) {
            call_user_func($callback);
        }
    }

    /**
     * Check whether update finalization is required.
     */
    public static function requiresUpdateFinalization(): bool
    {
        return app(UpdateFinalizer::class)->needed();
    }

    /**
     * Check whether the app requires maintenance.
     */
    public static function requiresMaintenance(): bool
    {
        if (is_null(static::$requiresMaintenance)) {
            static::$requiresMaintenance = static::requiresUpdateFinalization() || app(DatabaseMigrator::class)->needed();
        }

        return static::$requiresMaintenance;
    }

    /**
     * Mute all of the application communication channels.
     */
    public static function muteAllCommunicationChannels(): void
    {
        config(['mail.default' => 'array']);
        config(['broadcasting.default' => 'null']);

        static::disableNotifications();
    }

    /**
     * Disable notifications from being sent.
     */
    public static function disableNotifications(bool $value = true): void
    {
        static::$disableNotifications = $value;
    }

    /**
     * Enable notifications.
     */
    public static function enableNotifications(): void
    {
        static::disableNotifications(false);
    }

    /**
     * Get the available locales from the main lang directory and enabled module lang directories.
     */
    public static function locales(): array
    {
        $enabledModules = Module::collections();

        // Get locales from the main lang directory
        $locales = collect(File::directories(lang_path()))
            ->merge(
                // Get locales from each enabled module's lang directory
                $enabledModules->flatMap(function ($module) {
                    $moduleLangPath = module_path($module->getName(), 'lang');

                    return File::isDirectory($moduleLangPath) ? File::directories($moduleLangPath) : [];
                })
            )
            ->map(fn (string $locale) => basename($locale))
            ->reject(fn (string $locale) => $locale === 'vendor')
            ->unique()
            ->values()
            ->all();

        return apply_filters('locales', $locales);
    }

    /**
     * Flush the application state.
     */
    public static function flushState(): void
    {
        static::$resources = new Collection;
        static::$provideToScript = [];
        static::$requiresMaintenance = false;
        static::$resourcesByModel = [];
        static::$notifications = [];
        static::$scripts = [];
        static::$styles = [];
        static::$vite = [];
    }

    /**
     * Get the PHP executable path.
     */
    public static function getPhpExecutablePath(): ?string
    {
        $phpFinder = new PhpExecutableFinder;

        try {
            return $phpFinder->find();
        } catch (\Exception) {
            return null;
        }
    }
}
