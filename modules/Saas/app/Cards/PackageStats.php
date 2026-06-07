<?php

namespace Modules\Saas\Cards;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Modules\Core\Card\TableCard;
use Modules\Saas\Models\Package;

class PackageStats extends TableCard {
    /**
     * Provide the table items.
     *
     * @return \Illuminate\Support\Collection
     */
    public function items(Request $request): iterable
    {
        return Package::join('tenants', 'packages.id', '=', 'tenants.package_id')->select('packages.name as package', DB::raw('count(*) as count'))
            ->groupBy('packages.id', 'packages.name')->get()->map(function ($stat) {
            return [
                'count' => $stat->count,
                'package' => $stat->package,
            ];
        });
    }

    /**
     * Provide the table fields
     */
    public function fields(): array
    {

        return [
            ['key' => 'package', 'label' => __('saas::saas.package')],
            ['key' => 'count', 'label' => __('saas::saas.stats.total_tenants')],
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
        return __('saas::saas.package_stats');
    }


    /**
     * jsonSerialize
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'helpText' => __('saas::saas.stats.package'),
        ]);
    }

}
