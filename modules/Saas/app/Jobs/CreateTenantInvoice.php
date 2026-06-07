<?php

namespace Modules\Saas\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Core\Facades\Module as ModuleFacade;
use Modules\Invoice\Events\InvoiceCreate;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Traits\TenantInvoiceTrait;

class CreateTenantInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantInvoiceTrait;

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
        $dueDays = intval(settings()->get('before_expiry_notification')) ?? 3;

        $tenants = Tenant::where('expiry_date', '=', today()->addDays($dueDays)->toDateString())->with(['customer', 'package'])->get();
        foreach ($tenants as $tenant)
        {
            $invoiceModule = ModuleFacade::find('invoice');
            if ($invoiceModule && settings()->get('invoice_module_active')) {
                InvoiceCreate::dispatch($this->getInvoiceData($tenant, $tenant->package));
            }
        }
    }
}
