<?php

namespace Modules\Saas\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Actions\Action;
use Modules\Core\Actions\ActionFields;
use Modules\Core\Fields\Radio;
use Modules\Core\Http\Requests\ActionRequest;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Deals\Models\Stage;
use Modules\Saas\Services\Subdomain\SubdomainServiceInterface;

class DeleteTenantAction extends Action
{
    /**
     * Indicates whether this action is destroyable.
     */
    protected bool $destroyable = true;

    /**
     * Indicates whether the action support soft deletes.
     */
    protected bool $withSoftDeletes = false;

    /**
     * Handle method.
     */
    public function handle(Collection $models, ActionFields $fields)
    {
        /** @var \Modules\Core\Resource\Resource */
        $resource = $models->first()->resource();

        /** @var \Modules\Core\Http\Requests\ResourceRequest */
        $request = app(ResourceRequest::class)->setResource($resource->name());
        $subdomainService = app(SubdomainServiceInterface::class);

        foreach ($models as $model) {

            if (settings()->get('cpanel_enabled') && isset($model->subdomain))
                $subdomainService->deleteSubdomain($model->subdomain);

            Stage::query()->withoutGlobalScopes()->where('tenant_id', $model->id)->delete();
            $resource->delete($model, $request);
        }
    }

    /**
     * Set that the action supports soft deletes.
     */
    public function withSoftDeletes(): static
    {
        $this->withSoftDeletes = true;

        return $this;
    }

    /**
     * Provide the action fields.
     */
    public function fields(ResourceRequest $request): array
    {
        if (! $this->withSoftDeletes) {
            return [];
        }

        return [
            Radio::make('action_type')->options(
                [
                    [
                        'value' => 'move_to_trash',
                        'label' => __('core::app.soft_deletes.move_to_trash'),
                        'description' => __('core::app.soft_deletes.move_to_trash_info'),
                    ],
                    [
                        'value' => 'permanently_delete',
                        'label' => __('core::app.soft_deletes.force_delete'),
                        'description' => __('core::app.soft_deletes.force_delete_info'),
                    ],
                ],
            )->withDefaultValue('move_to_trash'),
        ];
    }

    /**
     * Determine if the action is executable for the given request.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    public function authorizedToRun(ActionRequest $request, $model): bool
    {
        if (is_callable($this->canRunCallback)) {
            return parent::authorizedToRun($request, $model);
        }

        return throw new LogicException('Provide authorization callback for the "delete" action.');
    }

    /**
     * Provide action human readable name.
     */
    public function name(): string
    {
        return $this->name ?: __('core::app.delete');
    }
}
