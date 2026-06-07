<?php

namespace Modules\Saas\Resources;

use Illuminate\Database\Eloquent\Builder;
use Modules\Billable\Models\Product as ModelsProduct;
use Modules\Core\Actions\DeleteAction;
use Modules\Core\Contracts\Resources\Importable;
use Modules\Core\Contracts\Resources\Tableable;
use Modules\Core\Contracts\Resources\WithResourceRoutes;
use Modules\Core\Facades\Innoclapps;
use Modules\Core\Fields\Boolean;
use Modules\Core\Fields\CreatedAt;
use Modules\Core\Fields\FieldsCollection;
use Modules\Core\Fields\Numeric;
use Modules\Core\Fields\RelationshipCount;
use Modules\Core\Fields\Select;
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
use Modules\Saas\Services\ModuleInit;
use Modules\Saas\Cards\PackageStats;
use Modules\Saas\Cards\RevenueByPackage;
use Modules\Saas\Enums\TenantDatabaseProvision;
use Modules\Saas\Models\Quota;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Installer\DatabaseTest;
use Modules\Installer\PrivilegesChecker;
use Modules\Saas\Services\ModuleInitializationService;
use Modules\Saas\Transformers\PackageResource;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\Actions\Action;

class Package extends Resource implements Importable, Tableable, WithResourceRoutes {
    public static string $model = 'Modules\Saas\Models\Package';

    /**
     * The column the records should be default ordered by when retrieving
     */
    public static string $orderBy = 'name';


        /**
     * Indicates whether the resource has detail view.
     */
    public static bool $hasDetailView = true;

    /**
     * Get the displayable label of the resource.
     */
    public static function label(): string
    {
        return __('saas::saas.packages');
    }

    /**
     * Get the displayable singular label of the resource.
     */
    public static function singularLabel(): string
    {
        return __('saas::saas.package');
    }

    public function table(Builder $query, ResourceRequest $request, string $identifier): Table
    {
        if (!ModuleInitializationService::isModuleActive("saas"))
        {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }

        return Table::make($query, $request, $identifier)
            ->withActionsColumn()
            ->withViews()
            ->withDefaultView(name: 'saas::saas.views.packages.all', flag: 'all-packages')
            ->orderBy('name', 'asc');
    }

    public function jsonResource(): string
    {
        return PackageResource::class;
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

        $quotas = Quota::all();

        $fields = [

            Text::make('name', __('saas::saas.fields.package.name'))
                ->required(),

            Textarea::make('description', __('saas::saas.fields.package.description'))
                ->required(),

            Numeric::make('base_price', __('saas::saas.fields.package.base_price'))
                ->help((settings()->get('invoice_module_active')) ? null : __('saas::saas.fields.package.base_price_help_without_invoice'))
                ->importRules('required')
                ->currency()
                ->primary(),

            Numeric::make('trial_period', __('saas::saas.fields.package.trial_period'))
                ->rules(['required', 'numeric', 'decimal:0,3', 'min:0'])
                ->tapIndexColumn(fn(Column $column) => $column->hidden()),

            Select::make('reocurring_period', __('saas::saas.fields.package.reocurring_period'))
                ->required()
                ->options([
                    'day' => 'Day',
                    'week' => 'Week',
                    'month' => 'Month',
                    'year' => 'Year',
                ]),

            Select::make('db_scheme', __('saas::saas.fields.tenant.db_scheme'))
                ->options(TenantDatabaseProvision::getPackageOptions())
                ->creationRules('required')
                ->updateRules('filled'),

            Boolean::make('db_test', __('saas::saas.fields.tenant.db_test'))
                ->onlyOnForms()
                ->rules(['nullable', 'boolean'])
                ->searchable(false)
                ->excludeFromImport()
                ->excludeFromExport()
                ->hidden(),

            ...$quotas->map(function ($quota) {
                return [
                    Numeric::make("limit_{$quota->id}", "{$quota->name}")
                        ->precision(- 1)
                        ->required()
                        ->prependText('limit'),
                ];
            })->flatten(1)->toArray(),

        ];
        if ($request->input('db_scheme') === 'custom')
        {
            $fields = array_merge($fields, [
                Text::make('db_host', __('saas::saas.fields.tenant.db_host'))->rules('required'),
                Text::make('db_port', __('saas::saas.fields.tenant.db_port'))->rules('required'),
                Text::make('db_user', __('saas::saas.fields.tenant.db_user'))->rules('required'),
                Text::make('db_password', __('saas::saas.fields.tenant.db_password'))->rules('required'),
                Text::make('database', __('saas::saas.fields.tenant.database'))->rules('required'),

            ]);
        }

        $fields[] = CreatedAt::make()->hidden();
        $fields[] = UpdatedAt::make()->hidden();

        return $fields;
    }

    public function create(Model $model, ResourceRequest $request): Model
    {
        $moduleInit = new ModuleInit();
        $moduleInit->handle('saas');

        if (!ModuleInitializationService::isModuleActive("saas"))
        {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }

        if ($request->input('db_scheme') === 'custom')
        {
            $this->testDatabaseConnection($request);
        }

        if ($request->input('db_test') === true)
        {
            response()->json([
                'success' => true,
                'message' => 'Database connection successful',
            ])->throwResponse();
        }

        $quotas = Quota::all();
        $rules = [
            ...$quotas->map(function ($quota) {
                return [
                    "limit_{$quota->id}" => ['required', 'numeric', 'min:-1'],
                ];
            })->toArray()
        ];

        $validated = $request->validate(array_merge($rules[0], [
            'db_scheme' => ['required'],
            'name' => ['required', 'string'],
            'description' => ['required', 'string'],
            'base_price' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            'trial_period' => ['required', 'numeric', 'min:0'],
            'reocurring_period' => ['required', 'in:day,week,month,year']
        ]));

        $data = $request->all();
        $model->fill($request->all());
        $model->save();

        $this->afterCreate($model, $request);

        return $model;
    }

    public function update(Model $model, ResourceRequest $request): Model
    {
        $moduleInit = new ModuleInit();
        $moduleInit->handle('saas');

        if (!ModuleInitializationService::isModuleActive("saas"))
        {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }

        if ($request->input('db_scheme') === 'custom')
        {
            $this->testDatabaseConnection($request);
        }

        if ($request->input('db_test') === true)
        {
            response()->json([
                'success' => true,
                'message' => 'Database connection successful',
            ])->throwResponse();
        }

        $quotas = Quota::all();
        $rules = [
            ...$quotas->map(function ($quota) {
                return [
                    "limit_{$quota->id}" => ['required', 'numeric', 'min:-1'],
                ];
            })->toArray()
        ];

        $validated = $request->validate(array_merge($rules[0], [
            'db_scheme' => ['required'],
            'name' => ['required', 'string'],
            'description' => ['required', 'string'],
            'base_price' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            'trial_period' => ['required', 'numeric', 'min:0'],
            'reocurring_period' => ['required', 'in:day,week,month,year'],
        ]));

        $model->fill($request->all());
        $model->save();

        $this->afterCreate($model, $request);

        return $model;
    }

    public function afterCreate(Model $model, ResourceRequest $request): void
    {
        $quotaLimits = collect($request->all())->filter(function ($value, $key) {
            return str_starts_with($key, 'limit_');
        });

        foreach ($quotaLimits as $key => $limit)
        {
            $quotaId = (int) str_replace('limit_', '', $key);

            $quota = DB::table('package_quotas')->where([
                'quota_id' => $quotaId,
                'package_id' => $model->id,
            ])->first();

            if (!$quota) {
                $model->quotas()->attach($quotaId, ['limit' => $limit]);
            } else {
                DB::table('package_quotas')->where([
                    'quota_id' => $quotaId,
                    'package_id' => $model->id,
                ])->update(['limit' => $limit]);
            }

        }
    }

    /**
     * Provides the resource available actions
     */
    public function actions(ResourceRequest $request): array
    {
        return [
            DeleteAction::make()->onlyOnIndex()->canRun(function (ActionRequest $request, Model $model, int $total) {
                return $request->user()->can($total > 1 ? 'bulkDelete' : 'delete', $model);
            })->showInline(),
        ];
    }

    /**
     * Prepare global search query.
     */
    public function globalSearchQuery(ResourceRequest $request): Builder
    {
        return parent::globalSearchQuery($request)->select(['id', 'name', 'created_at']);
    }

    public function fieldsForIndex(): FieldsCollection
    {
        return new FieldsCollection([
            Text::make('name', __('saas::saas.fields.package.name'))
                ->creationRules('required')
                ->updateRules('filled')
                ->disableInlineEdit()
                ->primary(),

            Textarea::make('description', __('saas::saas.fields.package.description'))
                ->disableInlineEdit()
                ->creationRules('required')
                ->updateRules('filled'),

            Numeric::make('base_price', __('saas::saas.fields.package.base_price'))
                ->disableInlineEdit()
                ->help((settings()->get('invoice_module_active')) ? null : __('saas::saas.fields.package.base_price_help_without_invoice'))
                ->importRules('required')
                ->currency(),

            Text::make('trial_period', __('saas::saas.fields.package.trial_period'))
                ->disableInlineEdit(),

            Select::make('reocurring_period', __('saas::saas.fields.package.reocurring_period'))
                ->disableInlineEdit()
                ->rules(['required', 'in:day,week,month,year'])
                ->options([
                    'day' => 'Day',
                    'week' => 'Week',
                    'month' => 'Month',
                    'year' => 'Year',
                ]),

            Select::make('db_scheme', __('saas::saas.fields.tenant.db_scheme'))
                ->disableInlineEdit()
                ->options(TenantDatabaseProvision::getPackageOptions()),

            RelationshipCount::make('tenants', __('saas::saas.fields.package.total_tenants')),
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
    public function testDatabaseConnection(Request $request): void
    {
        $request->validate([
            'db_scheme' => ['required'],
            'database' => ['required'],
            'db_host' => ['required'],
            'db_port' => ['required'],
            'db_user' => ['required'],
            'db_password' => ['required'],
        ]);

        try
        {
            $privileges = new PrivilegesChecker(
                new DatabaseTest($this->getDatabaseConnection($request))
            );

            $privileges->check();
        } catch (\Exception $e)
        {
            response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST)->throwResponse();
        }
    }

    /**
     * Get the database connection.
     */
    protected function getDatabaseConnection(Request $request): Connection
    {
        $config = [
            'driver' => 'mysql',
            'host' => $request->db_host,
            'database' => $request->database,
            'username' => $request->db_user,
            'password' => $request->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => config('database.connections.mysql.prefix'),
        ];

        $connectionKey = 'install' . md5(json_encode($config));

        Config::set('database.connections.' . $connectionKey, $config);

        /**
         * @var \Illuminate\Database\Connection
         */
        $connection = DB::connection($connectionKey);

        // Triggers PDO init, in case of errors, will fail and throw exception
        $connection->getPdo();

        return $connection;
    }

    public function cards(): array
    {
        if (app()->has('tenant')) {
            return [];
        }

        $cards[] = (new PackageStats())->onlyOnDashboard();

        if ((settings()->get('invoice_module_active')))
            $cards[] = (new RevenueByPackage())->onlyOnDashboard();

        return $cards;

    }

}
