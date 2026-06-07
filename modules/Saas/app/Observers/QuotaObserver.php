<?php

namespace Modules\Saas\Observers;

use Modules\Core\Models\Model;
use Modules\Saas\Models\TenantUsage;
use Modules\Saas\Models\TenantStat;
use Modules\Saas\Services\QuotaService;

class QuotaObserver
{
    public function __construct(protected QuotaService $quotaService) {}

    /**
     * Handle the QuotaObserver "created" event.
     */
    public function creating(Model $model): void
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return;
        }

        $modelNamespace = get_class($model);

        if (! $this->quotaService->isModelQuotaControlled($tenant, $modelNamespace)) {
            return;
        }

        if (! $this->quotaService->canCreateNewRecord($tenant, $modelNamespace)) {
            abort(403, "Quota exceeded for {$model->getTable()}. Please upgrade your package to create more records.");
        }
    }

    /**
     * Handle the QuotaObserver "created" event.
     */
    public function created(Model $model): void
    {

        $tenant = app()->has('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return;
        }

        $modelNamespace = get_class($model);
        if (! $this->quotaService->isModelQuotaControlled($tenant, $modelNamespace)) {
            return;
        }
        
        $tenantStat = TenantUsage::where('model', $modelNamespace)
            ->first();

        if ($tenantStat) {
            $tenantStat->increment('count');
        } else {
            TenantUsage::create([
                'tenant_id' => $tenant->id,
                'model' => $modelNamespace,
                'count' => 1,
            ]);
        }
    }

}
