<?php

namespace Modules\Saas\Listeners;

use Carbon\Carbon;
use Modules\Invoice\Events\InvoicePaidEvent;
use Modules\Saas\Models\SubscriptionHistory;
use Modules\Saas\Models\Tenant;

class InvoicePaid
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
    public function handle(InvoicePaidEvent $event): void
    {
        $invoice = $event->invoice;

        if (app()->has('tenant')) {
            $tenant = app('tenant');
        } else {
            $tenant = Tenant::with('package')->where('invoice_id', $invoice->id)->first();
        }

        if (! $tenant) {
            return;
        }

        $startDate = $tenant->hasExpired() ? today() : $tenant->expiry_date->addDay();
        $tenant->update([
            'invoice_id' => null,
            'trial' => false,
            'start_date' => $startDate,
            'expiry_date' => $this->getExpiryDate($tenant->package->reocurring_period, $startDate->toDateString()),
        ]);
        $tenant->save();

        $subscriptionHistory = new SubscriptionHistory;
        $subscriptionHistory->fill($tenant->toArray());
        $subscriptionHistory->tenant_id = $tenant->id;
        $subscriptionHistory->payment_amount = $invoice->total;
        $subscriptionHistory->save();
    }

    public function getExpiryDate(string $recurringPeriod, string $startDate): Carbon
    {
        $startDate = Carbon::parse($startDate);

        return match ($recurringPeriod) {
            'day' => $startDate->addDay(),
            'week' => $startDate->addWeek(),
            'month' => $startDate->addMonth(),
            'year' => $startDate->addYear(),
            default => throw new \InvalidArgumentException('Invalid recurring period'),
        };
    }
}
