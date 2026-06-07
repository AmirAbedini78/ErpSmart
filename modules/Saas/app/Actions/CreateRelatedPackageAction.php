<?php
/**
 * Concord CRM - https://www.concordcrm.com
 *
 * @version   1.5.1
 *
 * @link      Releases - https://www.concordcrm.com/releases
 * @link      Terms Of Service - https://www.concordcrm.com/terms
 *
 * @copyright Copyright (c) 2022-2024 KONKORD DIGITAL
 */

namespace Modules\Saas\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Actions\Action;
use Modules\Core\Actions\ActionFields;
use Modules\Core\Facades\Module as ModuleFacade;
use Modules\Core\Fields\BelongsTo;
use Modules\Core\Http\Requests\ActionRequest;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Invoice\Events\InvoiceCreate;
use Modules\Saas\Models\Package;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Traits\TenantInvoiceTrait;

class CreateRelatedPackageAction extends Action
{
    use TenantInvoiceTrait;
    /**
     * The action modal size. (sm, md, lg, xl, xxl)
     */
    public string $size = 'md';

    /**
     * Handle method.
     */
    public function handle(Collection $models, ActionFields $fields): void
    {
        $packageId = $fields->package_id;
        $package = Package::find($packageId);
        foreach ($models as $tenant) {
            if ($tenant instanceof Tenant) {
                if ($tenant->package_id == $packageId) {
                    continue;
                }

                $invoiceModule = ModuleFacade::find('invoice');

                if ($invoiceModule && settings()->get('invoice_module_active')) {
                    $tenant->start_date = today();

                    if ($package->trial_period && $package->trial_period > 0 ) {
                        $tenant->expiry_date = today()->addDays($package->trial_period);
                    } else {
                        $tenant->expiry_date = today();
                    }

                    InvoiceCreate::dispatch($this->getInvoiceData($tenant, $package));
                }

                $tenant->package_id = $packageId;
                $tenant->save();

            }
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    public function authorizedToRun(ActionRequest $request, $model): bool
    {
        if (is_callable($this->canRunCallback)) {
            return parent::authorizedToRun($request, $model);
        }

        return $request->user()->can('update', $model);
    }

    /**
     * Get the action fields.
     */
    public function fields(ResourceRequest $request): array
    {
        return [
            BelongsTo::make('package_id', Package::class, __('saas::saas.package'))->rules('required'),
        ];
    }

    /**
     * Get the confirmation button text.
     */
    public function confirmButtonText(): string
    {
        return __('core::app.create');
    }

    /**
     * Action name.
     */
    public function name(): string
    {
        return __('saas::saas.associate_package');
    }
}
