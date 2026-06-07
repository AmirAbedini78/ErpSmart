<?php

namespace Modules\Saas\Listeners;

use Modules\Invoice\Events\InvoiceCreated as InvoiceCreatedEvent;
use Modules\Saas\Models\Tenant;

class InvoiceCreated
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoiceCreatedEvent $event): void
    {
        $invoiceData = $event->invoiceData;
        $invoice = $event->invoice;

        if (isset($invoiceData['tenant_id'])) {
            if (app()->has('tenant')) {
                $tenant = app('tenant');
            } else {
                $tenant = Tenant::with('package')->where('id', $invoiceData['tenant_id'])->first();
            }

            if (! $tenant) {
                return;
            }

            $tenant->update(['invoice_id' => $invoice->id]);
            $tenant->save();
        }
    }
}
