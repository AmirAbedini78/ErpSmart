<?php

namespace Modules\Saas\Cards;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use Modules\Core\Card\TableCard;
use Modules\Invoice\Models\Invoice;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Models\TenantUsage;
use Modules\Users\Models\User;

class TenantStatistics extends TableCard {
    /**
     * Provide the table items.
     *
     * @return \Illuminate\Support\Collection
     */
    public function items(Request $request): iterable
    {
        $tenant = app()->has('tenant')? app()->make('tenant') : null;

        if(!$tenant)
            abort(403);

        $usage = TenantUsage::select('tenant_id', 'count', 'model')->get()->mapWithKeys(function ($stat) {
            return [
                $stat->model => [
                    'tenant_id' => $stat->tenant_id,
                    'count' => $stat->count,
                    'model' => $stat->model,
                ]
            ];
        });

        return $this->getPackageLimit($tenant)->map(function ($stat, $key) use ($usage) {
            $model = explode('\\', $key);
            $usageData = $usage[$key] ?? [];

            return [
                'total' => $stat,
                'usage' => $usageData['count'] ?? 0,
                'remaining' => $stat - ($usageData['count'] ?? 0),
                'entity' => $model[count($model) - 1],
            ];
        })->values();

    }

    /**
     * Provide the table fields
     */
    public function fields(): array
    {

        return [
            ['key' => 'entity', 'label' => __('saas::saas.stats.tenants.entity')],
            ['key' => 'total', 'label' => __('saas::saas.stats.tenants.total')],
            ['key' => 'usage', 'label' => __('saas::saas.stats.tenants.usage')],
            ['key' => 'remaining', 'label' => __('saas::saas.stats.tenants.remaining')],
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
        return __('saas::saas.tenantStatistics');
    }


    /**
     * jsonSerialize
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'helpText' => __('saas::saas.stats.tenants.help'),
        ]);
    }


    public function getPackageLimit(Tenant $tenant)
    {
        return $tenant->package?->quotas->flatMap(function ($quota) {
            return collect($quota['models'])->mapWithKeys(fn ($model) => [
                $model => $quota['pivot']['limit'],
            ]);
        })
            ->groupBy(fn ($limit, $model) => $model)
            ->map(fn ($group) => $group->max());
    }

}
