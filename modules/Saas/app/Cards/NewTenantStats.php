<?php

namespace Modules\Saas\Cards;

use Illuminate\Http\Request;
use Modules\Core\Charts\Progression;
use Modules\Saas\Models\Tenant;

class NewTenantStats extends Progression
{

    public function calculate(Request $request)
    {
        return $this->countByDays($request, Tenant::class);
    }


    /**
     * The card name
     */
    public function name(): string
    {
        return __('saas::saas.new_tenant_stats');
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'helpText' => __('saas::saas.stats.new_tenant_stats'),
        ]);
    }

    /**
     * Get the ranges available for the chart.
     */
    public function ranges(): array
    {
        return [
            7 => __('core::dates.periods.7_days'),
            15 => __('core::dates.periods.15_days'),
            30 => __('core::dates.periods.30_days'),
            60 => __('core::dates.periods.60_days'),
            90 => __('core::dates.periods.90_days'),
            365 => __('core::dates.periods.365_days'),
        ];
    }
}
