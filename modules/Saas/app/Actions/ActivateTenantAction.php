<?php

namespace Modules\Saas\Actions;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Core\Actions\Action;
use Modules\Core\Actions\ActionFields;
use Modules\Core\Facades\Innoclapps;
use Modules\Core\Fields\Radio;
use Modules\Core\Http\Requests\ActionRequest;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Core\Models\Model;
use Modules\Saas\Enums\TenantDatabaseProvision;
use Modules\Saas\Services\Domain\DomainServiceInterface;
use Modules\Saas\Services\Subdomain\SubdomainServiceInterface;
use Modules\Saas\Services\TenantService;
use Modules\Users\Models\User;

class ActivateTenantAction extends Action {
    /**
     * The action modal size. (sm, md, lg, xl, xxl)
     */
    public string $size = 'md';

    /**
     * Handle method.
     */
    public function handle(Collection $models, ActionFields $fields): void
    {
        $tenantService = app(TenantService::class);

        $actionType = $fields->action_type;
        foreach ($models as $model)
        {
            if ($actionType == 'run_all_actions')
            {

                $model->load('database');
                if ($model->db_scheme === TenantDatabaseProvision::CREATE_SEPARATE && !$model->database)
                {
                    $tenantService->createDatabase($model);
                }

                $tenantUsers = User::query()->where('tenant_id', $model->id)->withoutGlobalScopes()->get();
                if ($tenantUsers->isEmpty())
                {
                    $tenantService->createTenantUser($model);
                }

                if (!empty($model->subdomain))
                {
                    $this->creatSubDomain($model, $model->subdomain);
                }

                if (!empty($model->domain))
                {
                    $this->createDomain($model, $model->domain);
                }

            }

            $model->is_active = true;
            $model->save();
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function authorizedToRun(ActionRequest $request, $model): bool
    {
        if (is_callable($this->canRunCallback))
        {
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
            Radio::make('action_type')->options(
                [
                    [
                        'value' => 'run_all_actions',
                        'label' => __('saas::saas.activate_tenant.run_all_actions'),
                        'description' => __('saas::saas.activate_tenant.run_all_actions_info'),
                    ],
                    [
                        'value' => 'only_activate_tenant',
                        'label' => __('saas::saas.activate_tenant.only_activate_tenant'),
                        'description' => __('saas::saas.activate_tenant.only_activate_tenant_info'),
                    ],
                ],
            )->withDefaultValue('run_all_actions'),
        ];
    }

    /**
     * Get the confirmation button text.
     */
    public function confirmButtonText(): string
    {
        return __('saas::saas.confirm_action');
    }

    /**
     * Action name.
     */
    public function name(): string
    {
        return __('saas::saas.activate_tenant.name');
    }

    private function creatSubDomain(Model $model, $subdomain): void
    {
        if (!$subdomain && env('APP_ENV') === 'local') return;
        $subdomainService = app(SubdomainServiceInterface::class);
        try
        {
            $subdomainService->createSubdomain($subdomain, settings()->get('domain') ?? request()->getHost(), base_path());
        } catch (Exception $exception)
        {
            Log::error('Failed to create subdomain', ['subdomain' => $subdomain, 'domain' => settings()->get('domain') ?? request()->getHost(), 'error' => $exception->getMessage()]);

            return;
        }
    }

    private function createDomain(Model $model, ResourceRequest $request): void
    {
        $domain = $request->input('domain');
        if ($domain)
        {
            if (env('APP_ENV') !== 'local')
            {
                $domainService = app(DomainServiceInterface::class);
                $domainService->createDomain($domain, base_path());
            }
        }
    }

    /**
     * Run action based on the request data.
     *
     * @return mixed
     */
    public function run(ActionRequest $request, Builder $query)
    {
        $ids = $request->input('ids', []);
        $fields = $request->resolveFields();

        $this->total = count($ids);

        /**
         * Ensure multiple models cannot be executed on sole actions.
         */
        if ($this->sole === true && $this->total > 1) {
            return static::error('Please run this action only on one resource.');
        }

        /**
         * Find all models and exclude any models that are not authorized to be handled in this action
         */
        $models = $this->filterForExecution(
            $this->findModelsForExecution($ids, $query),
            $request
        );

        /**
         * All models excluded? In this case, the user is probably not authorized to run the action
         */
        if ($models->count() === 0) {
            return static::error(__('saas::saas.cannot_activate_tenant'));
        } elseif ($models->count() > (int) config('core.actions.disable_notifications_more_than')) {
            Innoclapps::muteAllCommunicationChannels();
        }

        $response = $this->handle($models, $fields);

        return is_null($response) ?
            static::success(__('core::actions.run_successfully')) :
            $response;
    }
}
