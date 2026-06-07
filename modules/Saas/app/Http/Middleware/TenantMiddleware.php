<?php

namespace Modules\Saas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Core\Facades\Innoclapps;
use Modules\Core\Models\Setting;
use Modules\Core\Settings\Contracts\Manager;
use Modules\Saas\Helpers\SettingManager;
use Modules\Saas\Services\ModuleInit;
use Modules\Saas\Enums\TenantDatabaseProvision;
use Modules\Saas\Services\Encryption\EncryptionServiceInterface;
use Modules\Saas\Services\TenantService;
use Modules\Users\Models\User;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{

    public function handle()
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        if ($tenant) {

            if (! $tenant->is_active) {
                abort(403, __('saas::saas.tenant_is_not_active'));
            }

            if ($tenant->hasExpired()) {
                abort(403, __('saas::saas.tenant_has_expired'));
            }

            if ($tenant->package_id === null) {
                abort(403, __('saas::saas.tenant_package_not_found'));
            }

            $users = collect();

            $package = $tenant->package;
            $dbScheme = $tenant->db_scheme == TenantDatabaseProvision::USE_FROM_PACKAGE ? $package->db_scheme : $tenant->db_scheme;

            if ($dbScheme === TenantDatabaseProvision::CUSTOM_CREDENTIAL && ! $tenant->database) {
                abort(403, __('saas::saas.tenant_database_not_found'));
            }

            $landLordDbConfig = DB::connection()->getConfig();

            if ($dbScheme === TenantDatabaseProvision::CUSTOM_CREDENTIAL || $dbScheme === TenantDatabaseProvision::CREATE_SEPARATE) {
                $users = User::where('tenant_id', $tenant->id)->get();
                $this->resetDBConnection($this->getTenantDBConfig($landLordDbConfig, $tenant, $dbScheme));
            } elseif ($dbScheme === TenantDatabaseProvision::TABLE_PREFIX) {
                $users = User::where('tenant_id', $tenant->id)->get();
                $config = unserialize(serialize($landLordDbConfig));
                $config['prefix'] = $tenant->id.'_';
                $this->resetDBConnection($config);
            }

            if ($tenant->first_login) {
                $tenantService = app(TenantService::class);
                $tenantService->tenantMigration($tenant, $users);

                if ($dbScheme === TenantDatabaseProvision::CUSTOM_CREDENTIAL || $dbScheme === TenantDatabaseProvision::CREATE_SEPARATE || $dbScheme === TenantDatabaseProvision::TABLE_PREFIX) {
                    $this->resetDBConnection($landLordDbConfig);
                }
                $tenant->first_login = false;
                $tenant->save();

                if ($dbScheme === TenantDatabaseProvision::CUSTOM_CREDENTIAL || $dbScheme === TenantDatabaseProvision::CREATE_SEPARATE || $dbScheme === TenantDatabaseProvision::TABLE_PREFIX) {
                    $this->resetDBConnection($this->getTenantDBConfig($landLordDbConfig, $tenant, $dbScheme));
                }

                SettingManager::seedToDatabase();
                settings()->set(['run_optimize_command' => true])->save();
            }

            $settings = app(Manager::class);
            $settings->refresh();

        }

    }

    private function resetDBConnection($dbConfig)
    {
        config()->set('database.connections.'.DB::connection()->getName(), $dbConfig);

        DB::purge(DB::connection()->getName());
        DB::reconnect(DB::connection()->getName());
    }

    private function getTenantDBConfig($dbConfig, $tenant, $dbScheme)
    {
        $encryptionService = app(EncryptionServiceInterface::class);

        if ($dbScheme === TenantDatabaseProvision::TABLE_PREFIX) {
            $dbConfig['table_prefix'] = $tenant->id.'_';

            return $dbConfig;
        }

        $dbConfig['host'] = $tenant->database->db_host;
        $dbConfig['port'] = $tenant->database->db_port;
        $dbConfig['database'] = $this->addPrefix($tenant->database->database);
        $dbConfig['username'] = $this->addPrefix($encryptionService->decrypt($tenant->database->db_username));
        $dbConfig['password'] = $encryptionService->decrypt($tenant->database->db_password);

        return $dbConfig;
    }

    public function addPrefix($text)
    {
        $cpanelUsername = settings()->get('cpanel_username');
        if (empty($cpanelUsername) || !settings()->get('cpanel_enabled')) {
            return $text;
        }

        return str_starts_with($text, $cpanelUsername) ? $text : $cpanelUsername.'_'.$text;
    }
}
