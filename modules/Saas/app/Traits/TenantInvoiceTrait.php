<?php

namespace Modules\Saas\Traits;

use Modules\Saas\Models\Package;
use Modules\Saas\Models\Tenant;

trait TenantInvoiceTrait {
    private function getInvoiceData(Tenant $tenant, Package $package): array
    {
        return [
            'total' => $package->base_price,
            'contact_id' => $tenant->contact_id,
            'should_send_notification' => true,
            'tenant_id' => $tenant->id,
            'due_date' => $tenant->expiry_date ?? null,
            'invoice_items' => [
                [
                    'name' => $package->name,
                    'total' => $package->base_price,
                    'description' => $package->description,
                    'quantity' => 1,
                    'tax' => 0,
                ],
            ],
        ];
    }
}
