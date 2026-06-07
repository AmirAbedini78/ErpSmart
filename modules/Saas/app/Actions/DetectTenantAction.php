<?php

namespace Modules\Saas\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Saas\Models\Tenant;

class DetectTenantAction
{
    public function handle()
    {
        $request = app()->make('request');
        $domain = $request->getHost();

        try {
            $tenant = Tenant::where('domain_url', "https://" . $domain)->orWhere('subdomain_url', "https://" . $domain)->with(['database', 'package.quotas', 'modules'])->first();
        } catch (\Exception $e) {
            return;
        }

        if ($tenant) {
            app()->instance('tenant', $tenant);
        }

    }
}
