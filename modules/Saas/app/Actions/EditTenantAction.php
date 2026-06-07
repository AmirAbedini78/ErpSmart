<?php

namespace Modules\Saas\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Actions\Action;
use Modules\Core\Actions\ActionFields;
use Modules\Core\Facades\Module as ModuleFacade;
use Modules\Core\Fields\MultiSelect;
use Modules\Core\Fields\Text;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Core\Module\Module;
use Modules\Saas\Fields\Customer;

class EditTenantAction extends Action
{
    public string $size = 'md';

    public function handle(Collection $models, ActionFields $fields): void
    {

    }

    public function fields(ResourceRequest $request): array
    {
        return [
            Text::make('name', __('saas::saas.fields.tenant.name'))
                ->required(),

            Customer::make()
                ->labelKey('guest_display_name')
                ->required(),

            Text::make('subdomain', __('saas::saas.fields.tenant.subdomain'))
                ->help('Enter only the subdomain prefix (e.g., enter "blog" for blog.example.com). If no domain and subdomain are specified, the random subdomain will be used.')
                ->validationMessages(['regex' => __('saas::saas.validation.tenant.subdomain.regex')]),

            Text::make('domain', __('saas::saas.fields.tenant.domain'))
                ->validationMessages(['regex' => __('saas::saas.validation.tenant.domain.regex')]),

            MultiSelect::make('activate_modules', __('saas::saas.fields.tenant.activate_modules'))
                ->options(collect(ModuleFacade::all())->filter(fn($module) => !$module->isCore() && $module->isEnabled() && $module->getLowerName() !== 'saas')->map(function (Module $module) {
                    return [
                        'label' => $module->getName(),
                        'value' => $module->getLowerName(),
                    ];
                })),

            MultiSelect::make('disable_modules', __('saas::saas.fields.tenant.disable_modules'))
                ->options(collect(ModuleFacade::all())->filter(fn($module) => !$module->isCore() && $module->isEnabled() && $module->getLowerName() !== 'saas')->map(function (Module $module) {
                    return [
                        'label' => $module->getName(),
                        'value' => $module->getLowerName(),
                    ];
                })),

            MultiSelect::make('disable_core_modules', __('saas::saas.fields.tenant.disable_core_modules'))
                ->options(collect(ModuleFacade::all())->filter(fn($module) => $module->isCore() && $module->isEnabled() && $module->getLowerName() !== 'saas')->map(function (Module $module) {
                    return [
                        'label' => $module->getName(),
                        'value' => $module->getLowerName(),
                    ];
                })),

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
