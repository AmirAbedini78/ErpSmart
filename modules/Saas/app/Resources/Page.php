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
use Modules\Core\Fields\Select;
use Modules\Core\Fields\Text;
use Modules\Core\Fields\Textarea;
use Modules\Core\Fields\UpdatedAt;
use Modules\Core\Http\Requests\ActionRequest;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Import\Import;
use Modules\Core\Resource\Resource;
use Modules\Core\Table\Table;
use Illuminate\Support\Str;
use Modules\Saas\Http\Resources\PageResource;
use Modules\Saas\Services\ModuleInit;
use Modules\Saas\Services\ModuleInitializationService;
use Modules\Saas\Transformers\PageResource as TransformersPageResource;

class Page extends Resource implements Importable, Tableable, WithResourceRoutes
{
    public static string $model = 'Modules\Saas\Models\Page';

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
            ->withDefaultView(name: 'saas::saas.views.pages.all', flag: 'all-pages')
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
        $fields = [
            Text::make('name', __('saas::saas.page.name'))
                ->creationRules('required')
                ->updateRules('filled'),

            Select::make('status', __('saas::saas.page.status'))
                ->rules(['nullable', 'in:archived,draft,published'])
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ]),

            CreatedAt::make()->hidden(),

            UpdatedAt::make()->hidden(),
        ];

        return $fields;
    }

    public function create(Model $model, ResourceRequest $request): Model
    {
        $moduleInit = new ModuleInit();
        $moduleInit->handle('saas');

        if (!ModuleInitializationService::isModuleActive("saas")) {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }

        $data = $request->all();
        $data['slug'] = Str::slug($data['name']);
        $model->fill($data);
        $model->save();
        return $model;
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
        return 'page';
    }

    /**
     * Get the displayable singular label of the resource.
     */
    public static function singularLabel(): string
    {
        return __('saas::saas.title');
    }

    public function fieldsForIndex(): FieldsCollection
    {
        return new FieldsCollection([
            Text::make('name', __('saas::saas.page.name'))
                ->creationRules('required')
                ->updateRules('filled'),

            Select::make('status', __('saas::saas.page.status'))
                ->rules(['nullable', 'in:archived,draft,published'])
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ]),

            Text::make('template_id', __('saas::saas.page.action'))
                ->creationRules('nullable')
                ->updateRules('filled'),


            CreatedAt::make()->hidden(),

            UpdatedAt::make()->hidden(),
        ]);
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
        return TransformersPageResource::class;
    }
}
