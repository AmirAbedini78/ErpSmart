<?php

namespace Modules\Saas\Cards;

use Illuminate\Http\Request;
use Modules\Core\Card\TableCard;
use Modules\Saas\Models\Tenant;

class ExpiredTenants extends TableCard {
    /**
     * Provide the table items.
     *
     * @return \Illuminate\Support\Collection
     */
    public function items(Request $request): iterable
    {
        return Tenant::join('packages', 'tenants.package_id', '=', 'packages.id')
            ->where('expiry_date', '<=', today()->toDateString())
            ->select('packages.name as package', 'tenants.expiry_date', 'tenants.name as tenant')
            ->get();
    }

    /**
     * Provide the table fields
     */
    public function fields(): array
    {

        return [
            ['key' => 'tenant', 'label' => __('saas::saas.tenant')],
            ['key' => 'expiry_date', 'label' => __('saas::saas.fields.tenant.expiry_date')],
            ['key' => 'package', 'label' => __('saas::saas.package')],
        ];
    }


    /**
     * Get the ranges available for the chart.
     */
    public function ranges(): array
    {
        return [

        ];
    }


    /**
     * Card title
     */
    public function name(): string
    {
        return __('saas::saas.expired_tenants');
    }

}
