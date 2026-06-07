<?php

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Modules\Core\Facades\Module;
use Modules\Deals\Models\Stage;
use Modules\Saas\Services\TenantMigrationService;

return Module::configure('saas')
    ->enabled(function (Application $app) {
        if (array_key_exists('module:clear-compiled', \Artisan::all())) {
            Config::set('core.commands.optimize', 'module:clear-compiled');
        }
        settings()->set(['run_optimize_command' => true])->save();
        settings()->set(['domain' => request()->getHost()])->save();

        settings()->set(['mysql_root_username' => 'root'])->save();
        settings()->set(['mysql_root_host' => env('DB_HOST', 'localhost')])->save();
        settings()->set(['mysql_root_port' => env('DB_PORT', '3306')])->save();
    })
    ->disabled(function (Application $app) {
        if (array_key_exists("module:clear-compiled", \Artisan::all()))
            Config::set("core.commands.optimize", 'module:clear-compiled');
    })
    ->deleting(function (Application $app) {
        if (\Illuminate\Support\Facades\Schema::hasColumn('stages', 'tenant_id'))
            Stage::query()->withoutGlobalScopes()->where('tenant_id', '!=',null)->delete();

        \Illuminate\Support\Facades\DB::table('tenants')->delete();

        $tenantMigrationService = app(TenantMigrationService::class);
        $tenantMigrationService->removeTenantIdFromTables();
    })
    ->deleted(function (Application $app) {
        if (array_key_exists('module:clear-compiled', \Artisan::all())) {
            Config::set('core.commands.optimize', 'module:clear-compiled');
        }

        settings()->forget('saas_module_active')->save();
        settings()->forget('saas_activation_code')->save();
        settings()->forget('saas_verification_id')->save();
        settings()->forget('saas_last_verified_at')->save();
        settings()->forget('saas_product_token')->save();
        settings()->forget('saas_heartbeat')->save();
        settings()->forget('run_optimize_command')->save();
        settings()->forget('add_tenant_id_column')->save();
    });
