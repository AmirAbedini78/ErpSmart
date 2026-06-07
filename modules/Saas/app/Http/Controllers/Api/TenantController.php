<?php

namespace Modules\Saas\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Models\TenantDatabase;
use Modules\Saas\Models\TenantModule;
use Modules\Saas\Services\Encryption\EncryptionServiceInterface;

class TenantController extends Controller
{
    public function __invoke($id)
    {
        $encryptionService = app(EncryptionServiceInterface::class);

        $tenant = Tenant::with('customer', 'package')->find($id);
        $database = TenantDatabase::where('tenant_id', $tenant->id)->withoutGlobalScopes()->first();

        $modules = TenantModule::where('tenant_id', $tenant->id)->withoutGlobalScopes()->get();
        $activatedModules = $modules->filter(function ($module) {
            return $module->is_enabled && !$module->is_core;
        });

        $deactivatedModules = $modules->filter(function ($module) {
            return !$module->is_enabled && !$module->is_core;
        });

        $deactivatedCoreModules = $modules->filter(function ($module) {
            return !$module->is_enabled && $module->is_core;
        });

        $tenant->activate_modules = $activatedModules->pluck('name')->toArray();
        $tenant->disable_modules = $deactivatedModules->pluck('name')->toArray();
        $tenant->disable_core_modules = $deactivatedCoreModules->pluck('name')->toArray();

        $tenant->db_host = $database->db_host ?? null;
        $tenant->database = $database->database ?? null;
        $tenant->db_port = $database->db_port ?? null;
        $tenant->db_user =  $database?->db_username ? $encryptionService->decrypt($database->db_username): null;
        $tenant->db_password = $database?->db_password ? $encryptionService->decrypt($database->db_password): null;

        return response()->json($tenant);
    }

}
