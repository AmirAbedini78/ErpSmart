<?php

namespace Modules\Saas\Resources;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Actions\CloneAction;
use Modules\Core\Actions\DeleteAction;
use Modules\Core\Contracts\Resources\Importable;
use Modules\Core\Contracts\Resources\Tableable;
use Modules\Core\Contracts\Resources\WithResourceRoutes;
use Modules\Core\Facades\Innoclapps;
use Modules\Core\Fields\CreatedAt;
use Modules\Core\Fields\FieldsCollection;
use Modules\Core\Fields\MultiSelect;
use Modules\Core\Fields\Text;
use Modules\Core\Fields\Textarea;
use Modules\Core\Fields\UpdatedAt;
use Modules\Core\Http\Requests\ActionRequest;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Import\Import;
use Modules\Core\Resource\Resource;
use Modules\Core\Table\Column;
use Modules\Core\Table\Table;
use Modules\Saas\Facade\Model as ModelFacade;
use Modules\Saas\Services\ModuleInitializationService;
use Modules\Saas\Transformers\QuotaResource;

class Quota extends Resource implements Importable, Tableable, WithResourceRoutes
{
    public static string $model = 'Modules\Saas\Models\Quota';

    /**
     * The column the records should be default ordered by when retrieving
     */
    public static string $orderBy = 'name';

    /**
     * Indicates whether the resource is globally searchable
     */
    public static bool $globallySearchable = true;

    /**
     * The resource displayable icon.
     */
    public static ?string $icon = 'Bars3CenterLeft';

    /**
     * The attribute to be used when the resource should be displayed.
     */
    public static string $title = 'name';

    public function table(Builder $query, ResourceRequest $request, string $identifier): Table
    {
        if (!ModuleInitializationService::isModuleActive("saas")) {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }
        return Table::make($query, $request, $identifier)
            ->withActionsColumn()
            ->withViews()
            ->withDefaultView(name: 'saas::saas.views.quotas.all', flag: 'all-quotas')
            ->orderBy('name', 'asc');
    }

    /**
     * Get the resource importable class
     */
    public function importable(): Import
    {
        return parent::importable()->lookupForDuplicatesUsing(function ($request) {
            return $this->newQueryWithTrashed()
                ->where(function (Builder $query) use ($request) {
                    $query->orWhere(array_filter([
                        'name' => $request->name,
                    ]));
                })->first();
        });
    }

    public function fields(ResourceRequest $request): array
    {
        return [
            Text::make('name', __('saas::saas.fields.quota.name'))
                ->creationRules('required')
                ->updateRules('filled'),

            Textarea::make('description', __('saas::saas.fields.quota.description')),

            MultiSelect::make('models', __('saas::saas.fields.quota.models'))
                ->creationRules('required')
                ->options(ModelFacade::getQuotableModels()->map(function (string $model) {
                    return [
                        'label' => explode('\\', $model)[count(explode('\\', $model)) - 1],
                        'value' => $model,
                    ];
                })),

            CreatedAt::make()->hidden(),

            UpdatedAt::make()->hidden(),
        ];
    }

    /**
     * Provides the resource available actions
     */
    public function actions(ResourceRequest $request): array
    {
        return [
            new \Modules\Core\Actions\BulkEditAction($this),

            DeleteAction::make()->onlyOnIndex()->canRun(function (ActionRequest $request, Model $model, int $total) {
                return $request->user()->can($total > 1 ? 'bulkDelete' : 'delete', $model);
            })->showInline()->withSoftDeletes(),
        ];
    }

    /**
     * Clone the given resource model.
     */
    public function clone(Model $model, int $userId): Model
    {
        return $model->clone($userId);
    }

    /**
     * Prepare global search query.
     */
    public function globalSearchQuery(ResourceRequest $request): Builder
    {
        return parent::globalSearchQuery($request)->select(['id', 'name', 'created_at']);
    }

    /**
     * Get the displayable label of the resource.
     */
    public static function label(): string
    {
        return __('Quotas');
    }

    /**
     * Get the displayable singular label of the resource.
     */
    public static function singularLabel(): string
    {
        return __('Quota');
    }

    public function fieldsForIndex(): FieldsCollection
    {
        $modelField = MultiSelect::make('models', __('saas::saas.fields.quota.models'))
            ->creationRules('required')
            ->excludeFromIndex()
            ->options(ModelFacade::getQuotableModels()->map(function (string $model) {
                return [
                    'label' => explode('\\', $model)[count(explode('\\', $model)) - 1],
                    'value' => $model,
                ];
            }));

        return new FieldsCollection([
            Text::make('name', __('saas::saas.fields.quota.name')),

            Text::make('description', __('saas::saas.fields.quota.description')),

            Text::make('models_display', __('saas::saas.fields.quota.models'))
                ->onlyOnIndex()
                ->inlineEditWith([$modelField])
                ->tapIndexColumn(function (Column $column) {
                    $column
                        ->select($models = ['models']) // For Edit
                        ->appends($models)
                        ->queryAs('models');
                }),

            CreatedAt::make()->hidden(),

            UpdatedAt::make()->hidden(),
        ]);
    }

    public function create(Model $model, ResourceRequest $request): Model
    {
        $this->beforeCreate($model, $request);

        $model->fill($request->all());
        $model->save();

        $this->afterCreate($model, $request);

        return $model;
    }

    /**
     * Register permissions for the resource
     */
    public function registerPermissions(): void
    {
        $this->registerCommonPermissions();

        Innoclapps::permissions(function ($manager) {
            $manager->group($this->name(), function ($manager) {
                $manager->view('export', [
                    'permissions' => [
                        'export products' => __('core::app.export.export'),
                    ],
                ]);
            });
        });
    }

    public function jsonResource(): string
    {
        return QuotaResource::class;
    }
}
