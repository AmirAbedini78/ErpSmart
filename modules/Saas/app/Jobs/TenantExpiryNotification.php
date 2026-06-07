<?php

namespace Modules\Saas\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Notifications\TenantExpiryNotification as ExpiryNotification;

class TenantExpiryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenants = Tenant::where('expiry_date', '=', today()->toDateString())->with(['customer', 'package'])->get();

        foreach ($tenants as $tenant)
        {
            $customer = $tenant->customer;

            if ($customer) {
                $customer->notify(new ExpiryNotification($tenant, $tenant->package));
            }
        }
    }
}
