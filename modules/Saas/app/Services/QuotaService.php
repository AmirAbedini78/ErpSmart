<?php

namespace Modules\Saas\Services;

use Modules\Saas\Models\Tenant;

class QuotaService
{
    public function isModelQuotaControlled(Tenant $tenant, string $modelNamespace): bool
    {
        $quotas = $tenant->package?->quotas->flatMap(function ($quota) {
            return collect($quota['models'])->mapWithKeys(fn ($model) => [
                $model => $quota['pivot']['limit'],
            ]);
        })
            ->groupBy(fn ($limit, $model) => $model)
            ->map(fn ($group) => $group->max());

        return isset($quotas[$modelNamespace]);
    }

    public function canCreateNewRecord(Tenant $tenant, string $modelNamespace): bool
    {
        $quotas = $tenant->package->quotas->flatMap(function ($quota) {
            return collect($quota['models'])->mapWithKeys(fn ($model) => [
                $model => $quota['pivot']['limit'],
            ]);
        })
            ->groupBy(fn ($limit, $model) => $model)
            ->map(fn ($group) => $group->max());

        if (! isset($quotas[$modelNamespace])) {
            return true;
        }

        $limit = $quotas[$modelNamespace];

        // If limit is -1 or null, assume unlimited
        if ($limit === -1 || $limit === null) {
            return true;
        }

        $currentCount = $this->countRecords($tenant, $modelNamespace);

        return $currentCount < $limit;
    }

    protected function countRecords(Tenant $tenant, string $modelNamespace): int
    {
        $model = new $modelNamespace;

        return $model->count();
    }
}
