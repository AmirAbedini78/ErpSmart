<?php

namespace Modules\Saas\Cards;

use Illuminate\Http\Request;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Core\Card\TableCard;
use Modules\Saas\Models\Package;
use Modules\Saas\Models\SubscriptionHistory;

class RevenueByPackage extends TableCard {
    /**
     * Provide the table items.
     *
     * @return \Illuminate\Support\Collection
     */
    public function items(Request $request): iterable
    {
        $range = $this->getCurrentRange($request);
        $startingDate = $this->getStartingDate($range, static::BY_MONTHS);
        $endingDate = Carbon::asAppTimezone();

        $tablePrefix = Config::get('database.connections.mysql.prefix');
        $subscriptionHistoryTable = ( new SubscriptionHistory() )->getTable();

        return Package::join($subscriptionHistoryTable, 'packages.id', '=', $subscriptionHistoryTable . '.package_id')
            ->select('packages.name as package', DB::raw('sum(' . $tablePrefix . $subscriptionHistoryTable . '.payment_amount) as revenue'))
            ->whereBetween( $subscriptionHistoryTable . '.created_at', [$startingDate, $endingDate])
            ->groupBy('packages.id', 'packages.name')->get()->map(function ($stat) {
                return [
                    'revenue' => $stat->revenue,
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
            ['key' => 'revenue', 'label' => __('saas::saas.stats.revenue')],
        ];
    }


    /**
     * Get the ranges available for the chart.
     */
    public function ranges(): array
    {
        return [
            3 => __('core::dates.periods.last_3_months'),
            6 => __('core::dates.periods.last_6_months'),
            12 => __('core::dates.periods.last_12_months'),
        ];
    }


    /**
     * Card title
     */
    public function name(): string
    {
        return __('saas::saas.revenue_by_package');
    }

    /**
     * jsonSerialize
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'helpText' => __('saas::saas.stats.revenue_by_package'),
        ]);
    }

}
