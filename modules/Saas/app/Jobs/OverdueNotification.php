<?php

namespace Modules\Saas\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Notifications\TenantOverdueNotification;

class OverdueNotification implements ShouldQueue
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
        $overdueDays = settings()->get('overdue_days');

        if (!$overdueDays || $overdueDays < 1) {
            return;
        }

        $overdueDate = today()->subDays($overdueDays)->toDateString();
        $tenants = Tenant::with(['package', 'customer'])->whereDate('expiry_date', $overdueDate)->get();
        foreach ($tenants as $tenant)
        {
            $customer = $tenant->customer;

            if ($customer) {
                $customer->notify(new TenantOverdueNotification($tenant, $tenant->package));
            }
        }
    }
}
