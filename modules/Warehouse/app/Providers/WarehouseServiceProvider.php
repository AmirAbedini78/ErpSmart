<?php

namespace Modules\Warehouse\Providers;

use Closure;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Facades\Innoclapps;
use Modules\Core\Support\ModuleServiceProvider;

class WarehouseServiceProvider extends ModuleServiceProvider
{
    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        $this->registerCommands();
    }

    /**
     * Register any module services.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Configure the module.
     */
    protected function setup(): void
    {
        Innoclapps::vite('resources/js/app.js', [
            'buildDirectory' => 'modules/'.$this->moduleNameLower().'/build',
            'hotFile' => storage_path('hot-'. $this->moduleNameLower())
        ]);
    }

    /**
     * Register module commands.
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Schedule module tasks.
     */
    protected function scheduleTasks(Schedule $schedule): void
    {
        // $schedule->safeCommand('inspire')->hourly();
    }

    /**
     * Provide the data to share on the front-end.
     */
    protected function scriptData() : Closure|array
    {
        return [
            'warehouse' => []
        ];
    }

    /**
     * Provide the module name.
     */
    protected function moduleName(): string
    {
        return 'Warehouse';
    }

    /**
     * Provide the module name in lowercase.
     */
    protected function moduleNameLower(): string
    {
        return 'warehouse';
    }
}
