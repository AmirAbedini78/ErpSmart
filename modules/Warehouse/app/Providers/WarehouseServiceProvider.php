<?php

namespace Modules\Warehouse\Providers;

use Closure;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Support\ModuleServiceProvider;

class WarehouseServiceProvider extends ModuleServiceProvider
{


    protected array $resources = [
        \Modules\Warehouse\Resources\Warehouse::class,
    ];

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
        $this->registerResources();
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Configure the module.
     */
    protected function setup(): void
    {
        // Warehouse frontend is currently bundled through the root resources/js/app.js file.
        // Do not register a separate module Vite entry until the module build pipeline is finalized.
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
