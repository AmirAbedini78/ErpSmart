<?php

namespace Modules\Saas\Resources;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Saas\Models\Customer as ContactModel;
use Modules\Core\Contracts\Resources\Tableable;
use Modules\Core\Contracts\Resources\WithResourceRoutes;
use Modules\Core\Facades\Module as ModuleFacade;
use Modules\Core\Fields\BelongsTo;
use Modules\Core\Fields\Boolean;
use Modules\Core\Fields\CreatedAt;
use Modules\Core\Fields\Date;
use Modules\Core\Fields\FieldsCollection;
use Modules\Core\Fields\MultiSelect;
use Modules\Core\Fields\Select;
use Modules\Core\Fields\Text;
use Modules\Core\Fields\UpdatedAt;
use Modules\Core\Http\Requests\ActionRequest;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Models\Model;
use Modules\Core\Module\Module;
use Modules\Core\Resource\Resource;
use Modules\Core\Table\Column;
use Modules\Core\Table\Table;
use Modules\Installer\DatabaseTest;
use Modules\Installer\PrivilegesChecker;
use Modules\Invoice\Events\InvoiceCreate;
use Modules\Saas\Actions\ActivateTenantAction;
use Modules\Saas\Actions\DeleteTenantAction;
use Modules\Saas\Actions\DownloadInstallationInstructions;
use Modules\Saas\Notifications\TenantDomainIntegrationNotification;
use Modules\Saas\Services\ModuleInit;
use Modules\Saas\Actions\CreateRelatedPackageAction;
use Modules\Saas\Cards\ExpiredTenants;
use Modules\Saas\Cards\NewTenantStats;
use Modules\Saas\Cards\TenantStatistics;
use Modules\Saas\Enums\TenantDatabaseProvision;
use Modules\Saas\Fields\Customer;
use Modules\Saas\Models\Package;
use Modules\Saas\Models\TenantDatabase;
use Modules\Saas\Models\TenantModule;
use Modules\Saas\Services\Domain\DomainServiceInterface;
use Modules\Saas\Services\Encryption\EncryptionServiceInterface;
use Modules\Saas\Services\ModuleInitializationService;
use Modules\Saas\Services\Subdomain\SubdomainServiceInterface;
use Modules\Saas\Services\TenantService;
use Modules\Saas\Traits\TenantInvoiceTrait;
use Modules\Saas\Transformers\TenantResource;
use Symfony\Component\HttpFoundation\Response;

class Tenant extends Resource implements Tableable, WithResourceRoutes
{
    use TenantInvoiceTrait;
    public static string $model = 'Modules\Saas\Models\Tenant';

    public function jsonResource(): string
    {
        return TenantResource::class;
    }

    /**
     * Get the displayable label of the resource.
     */
    public static function label(): string
    {
        return __('saas::saas.tenants');
    }

    /**
     * Get the displayable singular label of the resource.
     */
    public static function singularLabel(): string
    {
        return __('saas::saas.tenant');
    }

    public function fields(ResourceRequest $request): array
    {
        $fields = [
            Text::make('name', __('saas::saas.fields.tenant.name'))
                ->required(),

            Customer::make()
                ->labelKey('guest_display_name')
                ->onlyOnCreate()
                ->required(),

            Text::make('login_url', __('saas::saas.fields.tenant.login_url'))
                ->searchable(false)
                ->exceptOnForms()
                ->tapIndexColumn(fn (Column $column) => $column
                    ->queryAs(\Modules\Saas\Models\Tenant::urlQueryExpression('login_url'))
                ),

            Text::make('subdomain', __('saas::saas.fields.tenant.subdomain'))
                ->help(__('saas::saas.help_text.subdomain_'))
                ->disableInlineEdit()
                ->validationMessages(['regex' => __('saas::saas.validation.tenant.subdomain.regex')]),

            Text::make('domain', __('saas::saas.fields.tenant.domain'))
                ->disableInlineEdit()
                ->validationMessages(['regex' => __('saas::saas.validation.tenant.domain.regex')])
                ->help(__('saas::saas.help_text.domain')),

            MultiSelect::make('activate_modules', __('saas::saas.fields.tenant.activate_modules'))
                ->options(
                    collect(ModuleFacade::all())->filter(
                        fn($module) => !$module->isCore() && $module->isEnabled() && $module->getLowerName() !== 'saas'
                    )->map(function (Module $module) {
                        return [
                            'label' => $module->getName(),
                            'value' => $module->getLowerName(),
                        ];
                    })
                ),

            MultiSelect::make('disable_modules', __('saas::saas.fields.tenant.disable_modules'))
                ->options(
                    collect(ModuleFacade::all())->filter(
                        fn($module) => !$module->isCore() && $module->isEnabled() && $module->getLowerName() !== 'saas'
                    )->map(function (Module $module) {
                        return [
                            'label' => $module->getName(),
                            'value' => $module->getLowerName(),
                        ];
                    })
                ),

            MultiSelect::make('disable_core_modules', __('saas::saas.fields.tenant.disable_core_modules'))
                ->options(
                    collect(ModuleFacade::all())->filter(
                        fn($module) => $module->isCore() && $module->isEnabled() && $module->getLowerName() !== 'saas'
                    )->map(function (Module $module) {
                        return [
                            'label' => $module->getName(),
                            'value' => $module->getLowerName(),
                        ];
                    })
                ),

            Select::make('db_scheme', __('saas::saas.fields.tenant.db_scheme'))
                ->options(TenantDatabaseProvision::getOptions())
                ->creationRules('required')
                ->updateRules('filled'),

            Boolean::make('db_test', __('saas::saas.fields.tenant.db_test'))
                ->onlyOnForms()
                ->rules(['nullable', 'boolean'])
                ->searchable(false)
                ->excludeFromImport()
                ->excludeFromPlaceholders()
                ->excludeFromExport()
                ->hidden(),

            Date::make('expiry_date', __('saas::saas.fields.tenant.expiry_date'))
                ->searchable(false)
                ->excludeFromImport()
                ->excludeFromExport()
                ->hidden(),

            BelongsTo::make('package', Package::class, __('saas::saas.package')),
        ];
        if ($request->input('db_scheme') === 'custom') {
            $fields = array_merge($fields, [
                Text::make('db_host', __('saas::saas.fields.tenant.db_host')),
                Text::make('db_port', __('saas::saas.fields.tenant.db_port')),
                Text::make('db_user', __('saas::saas.fields.tenant.db_user')),
                Text::make('db_password', __('saas::saas.fields.tenant.db_password')),
                Text::make('database', __('saas::saas.fields.tenant.database')),

            ]);
        }

        $fields[] = CreatedAt::make()->hidden();
        $fields[] = UpdatedAt::make()->hidden();

        return $fields;
    }

    /**
     * Get resource available cards
     */
    public function cards(): array
    {
        if (app()->has('tenant')) {
            return [(new TenantStatistics())->onlyOnDashboard()];
        }

        $cards[] = (new NewTenantStats())->onlyOnDashboard();
        $invoiceModuleActive = ModuleFacade::find('invoice') && settings()->get('invoice_module_active');

        if ($invoiceModuleActive) {
            $cards[] = (new ExpiredTenants())->onlyOnDashboard();
        }

        return $cards;
    }

    public function create(Model $model, ResourceRequest $request): Model
    {
        $moduleInit = new ModuleInit();
        $moduleInit->handle('saas');

        if (!ModuleInitializationService::isModuleActive("saas")) {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }

        if ($request->input('db_scheme') === 'custom') {
            $this->testDatabaseConnection($request);
        }

        if ($request->input('db_test') === true) {
            response()->json([
                'success' => true,
                'message' => 'Database connection successful',
            ])->throwResponse();
        }

        $request->validate([
            'subdomain' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9]+([-.][a-zA-Z0-9]+)*$/'],
            'domain' => ['nullable', 'regex:/^(?!:\/\/)([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,}$/'],
            'db_scheme' => ['required'],
            'contact_id' => ['required', 'exists:contacts,id'],
            'name' => ['required', 'string'],
        ]);

        $data = $request->all();
        $data['is_active'] = true;
        $subdomain = (!$data['subdomain'] && !$data['domain']) ? Str::replace(
            ' ',
            '',
            $data['name']
        ) : $data['subdomain'];

        if (isset($subdomain)) {
            $domain = settings()->get('domain');
            $data['subdomain'] = strtolower($subdomain);
            $data['subdomain_url'] = "https://" . $data['subdomain'] . '.' . $domain;
        }

        if (isset($data['domain'])) {
            $data['domain_url'] = "https://" . $data['domain'];
        }

        $contact = ContactModel::find($data['contact_id']);

        if (!$contact->email) {
            abort(400, 'Contact must have an email');
        }

        $this->beforeCreate($model, $request);

        $model->fill($data);
        $model->save();

        if ($model->db_scheme === TenantDatabaseProvision::CUSTOM_CREDENTIAL) {
            $encryptionService = app(EncryptionServiceInterface::class);
            $data['db_username'] = $encryptionService->encrypt($data['db_user']);
            $data['db_password'] = $encryptionService->encrypt($data['db_password']);
            TenantDatabase::create(array_merge($data, ['tenant_id' => $model->id]));
        } elseif ($model->db_scheme === TenantDatabaseProvision::CREATE_SEPARATE) {
            $tenantService = app(TenantService::class);
            DB::afterCommit(function () use ($tenantService, $model) {
                $tenantService->createDatabase($model);
            });
        }

        $this->afterCreate($model, $request);

        return $model;
    }

    public function afterCreate(Model $model, ResourceRequest $request): void
    {
        $tenantService = app(TenantService::class);

        $activeModules = $request->input('activate_modules');
        if (count($activeModules) > 0) {
            foreach ($activeModules as $module) {
                TenantModule::create([
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => false,
                    'is_enabled' => true,
                ]);
            }
        }

        $disableModules = $request->input('disable_modules');
        if (count($disableModules) > 0) {
            foreach ($disableModules as $module) {
                if (in_array($module, $activeModules)) {
                    continue;
                }
                TenantModule::create([
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => false,
                    'is_enabled' => false,
                ]);
            }
        }

        $disableCoreModules = $request->input('disable_core_modules');
        if (count($disableCoreModules) > 0) {
            foreach ($disableCoreModules as $module) {
                if (in_array($module, $activeModules)) {
                    continue;
                }
                TenantModule::create([
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => true,
                    'is_enabled' => false,
                ]);
            }
        }

        $tenantService->createTenantUser($model);

        if ($request->input('subdomain') && !empty($request->input('subdomain')) && settings()->get('cpanel_enabled')) {
            $this->creatSubDomain($model, $request);
        }

        if ($request->input('domain') && !empty($request->input('domain')) && settings()->get('cpanel_enabled')) {
            $this->createDomain($model, $request);
        }

        if ($request->input('domain') && !empty($request->input('domain'))) {
            /**
             * @var \Modules\Saas\Models\Customer $contact
             */
            $contact = $model->customer;
            $contact->notify(new TenantDomainIntegrationNotification($model));
        }

        $invoiceModule = ModuleFacade::find('invoice');

        if ($model->package && $invoiceModule && settings()->get('invoice_module_active')) {
            $model->loadMissing('package', 'customer');

            $package = $model->package;
            $model->start_date = today();

            if ($package->trial_period && $package->trial_period > 0 ) {
                $model->expiry_date = today()->addDays($package->trial_period);
                $model->trial = true;
            } else {
                $model->expiry_date = today();
            }

            $model->save();
            InvoiceCreate::dispatch($this->getInvoiceData($model, $package));
        }

    }

    public function creatSubDomain(Model $model, ResourceRequest $request): void
    {
        $subdomain = $request->input('subdomain');
        if ($subdomain) {
            if (env('APP_ENV') !== 'local') {
                $subdomainService = app(SubdomainServiceInterface::class);
                try {
                    $subdomainService->createSubdomain(
                        $subdomain,
                        settings()->get('domain') ?? request()->getHost(),
                        base_path()
                    );
                } catch (Exception $exception) {
                    Log::error(
                        'Failed to create subdomain',
                        [
                            'subdomain' => $subdomain,
                            'domain' => settings()->get('domain') ?? request()->getHost(),
                            'error' => $exception->getMessage()
                        ]
                    );

                    return;
                }
            }
        }
    }

    private function createDomain(\Modules\Saas\Models\Tenant $model, ResourceRequest $request): void
    {
        $domain = $request->input('domain');
        if ($domain) {
            $model->loadMissing('customer');
            if (env('APP_ENV') !== 'local') {
                $domainService = app(DomainServiceInterface::class);
                $domainService->createDomain($domain, base_path());
            }
        }
    }

    public function fieldsForIndex(): FieldsCollection
    {
        return new FieldsCollection([
            Text::make('name', __('saas::saas.fields.tenant.name'))
                ->creationRules('required')
                ->updateRules('filled')
                ->tapIndexColumn(fn(Column $column) => $column
                    ->width('300px')->minWidth('200px')
                    ->primary()
                    ->route(!$column->isForTrashedTable() ? '/saas/tenants/{id}/edit' : '')
                ),

            Text::make('domain_url', __('saas::saas.fields.tenant.domain_url'))
                ->searchable(false)
                ->excludeFromZapierResponse()
                ->tapIndexColumn(fn (Column $column) => $column
                    ->queryAs(\Modules\Saas\Models\Tenant::urlQueryExpression('domain_url'))
                ),

            Text::make('subdomain', __('saas::saas.fields.tenant.subdomain'))
                ->hidden(),

            Text::make('domain', __('saas::saas.fields.tenant.domain'))
                ->hidden(),

            Date::make('expiry_date', __('saas::saas.fields.tenant.expiry_date'))
                ->searchable(false)
                ->excludeFromImport()
                ->excludeFromExport(),

            Boolean::make('trial', __('saas::saas.fields.tenant.trial'))
                ->disableInlineEdit(),

            Boolean::make('is_active', __('saas::saas.fields.tenant.is_active')),

            BelongsTo::make('package', Package::class, 'package')
                ->disableInlineEdit(),

            Select::make('db_scheme', __('saas::saas.fields.tenant.db_scheme'))
                ->disableInlineEdit()
                ->options(TenantDatabaseProvision::getOptions())
                ->creationRules('required')
                ->updateRules('filled'),

            Customer::make()
                ->labelKey('display_name')
                ->tapIndexColumn(function (Column $column) {
                    $column
                        ->wrap()
                        ->queryAs(ContactModel::nameQueryExpression('display_name'))
                        ->fillRowDataUsing(function (array &$row, Model $model) use ($column) {
                            $relatedModel = $model->customer;
                            $row[$column->attribute] = $relatedModel
                                ? $column->toRowData($relatedModel)
                                : null;
                        });
                }),

            CreatedAt::make()->hidden(),

            UpdatedAt::make()->hidden(),
        ]);
    }

    public function table(Builder $query, ResourceRequest $request, string $identifier): Table
    {
        if (!ModuleInitializationService::isModuleActive("saas")) {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }

        return Table::make($query, $request, $identifier)
            ->withActionsColumn()
            ->withViews()
            ->withDefaultView('saas::saas.views.tenants.all', 'all-tenants')
            ->orderBy('created_at', 'desc');
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

        try {
            $privileges = new PrivilegesChecker(
                new DatabaseTest($this->getDatabaseConnection($request))
            );

            $privileges->check();
        } catch (\Exception $e) {
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

    public function menu(): array
    {
        if (!app()->has('tenant')) {
            if (!settings()->get('saas_module_active')) {
                $menuItem = MenuItem::make('Saas', '/settings/saas')
                    ->icon('Briefcase')
                    ->position(15)
                    ->badgeVariant('info');
                $menuItem->id = 'saas-settings';
            } else {
                $menuItem = MenuItem::make('Saas', '/saas')
                    ->icon('Briefcase')
                    ->position(15)
                    ->badgeVariant('info');
                $menuItem->id = 'saas';
            }

            return [$menuItem];
        } else {
            return [];
        }
    }

    public function actions(ResourceRequest $request): array
    {
        return [
            new \Modules\Core\Actions\BulkEditAction($this),
            CreateRelatedPackageAction::make()->onlyInline(),
            DownloadInstallationInstructions::make()
                ->canRun(function (ActionRequest $request, Model $model, int $total) {
                    $domain = $model->subdomain ?? $model->domain;

                    return !is_null($domain);
                })->onlyInline(),

            ActivateTenantAction::make()
                ->onlyInline()
                ->canRun(function (ActionRequest $request, Model $model, int $total) {
                    return !$model->is_active;
                }),

            DeleteTenantAction::make()->onlyOnIndex()->canRun(function (ActionRequest $request, Model $model, int $total) {
                return $request->user()->can($total > 1 ? 'bulkDelete' : 'delete', $model);
            })->showInline(),
        ];
    }

    public function update(Model $model, ResourceRequest $request): Model
    {
        if (!ModuleInitializationService::isModuleActive("saas")) {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }

        if ($request->input('db_scheme') === 'custom') {
            $this->testDatabaseConnection($request);
        }

        if ($request->input('db_test') === true) {
            response()->json([
                'success' => true,
                'message' => 'Database connection successful',
            ])->throwResponse();
        }

        $request->merge([
            'prev_package_id' => $model->package_id,
        ]);
        $data = $request->all();

        if (isset($data['subdomain'])) {
            $domain = settings()->get('domain');
            $data['subdomain'] = strtolower($data['subdomain']);
            $data['subdomain_url'] = "https://" . $data['subdomain'] . '.' . $domain;
        }

        if (isset($data['domain'])) {
            $data['domain_url'] = "https://" . $data['domain'];
        }

        $model->fill($data);

        $this->beforeUpdate($model, $request);

        $model->save();
        $this->afterUpdate($model, $request);

        return $model;
    }

    public function beforeUpdate(Model $model, ResourceRequest $request): void
    {
        if (!$model->isDirty('db_scheme')) {
            return;
        }

        $data = $request->validated();

        if ($model->db_scheme === TenantDatabaseProvision::CUSTOM_CREDENTIAL) {
            $encryptionService = app(EncryptionServiceInterface::class);
            $data['db_username'] = $encryptionService->encrypt($data['db_user']);
            $data['db_password'] = $encryptionService->encrypt($data['db_password']);
            TenantDatabase::query()->withoutGlobalScopes()->updateOrCreate(['tenant_id' => $model->id],
                array_merge($data, ['tenant_id' => $model->id]));
        } elseif ($model->db_scheme === TenantDatabaseProvision::CREATE_SEPARATE) {
            $tenantService = app(TenantService::class);
            DB::afterCommit(function () use ($tenantService, $model) {
                $tenantService->createDatabase($model);
            });
        }
    }


    public function afterUpdate(Model $model, ResourceRequest $request): void
    {
        $activeModules = $request->input('activate_modules');
        if ($activeModules && count($activeModules) > 0) {
            foreach ($activeModules as $module) {
                TenantModule::query()->withoutGlobalScopes()->firstOrcreate([
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => false,
                    'is_enabled' => true,
                ], [
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => false,
                    'is_enabled' => true,
                ]);
            }
        }

        $disableModules = $request->input('disable_modules');
        if ($disableModules && count($disableModules) > 0) {
            foreach ($disableModules as $module) {
                if (in_array($module, $activeModules)) {
                    continue;
                }
                TenantModule::query()->withoutGlobalScopes()->firstOrcreate([
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => false,
                    'is_enabled' => false,
                ], [
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => false,
                    'is_enabled' => false,
                ]);
            }
        }

        $disableCoreModules = $request->input('disable_core_modules');
        if ($disableCoreModules && count($disableCoreModules) > 0) {
            foreach ($disableCoreModules as $module) {
                if (in_array($module, $activeModules)) {
                    continue;
                }
                TenantModule::query()->withoutGlobalScopes()->firstOrcreate([
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => true,
                    'is_enabled' => false,
                ], [
                    'tenant_id' => $model->id,
                    'name' => $module,
                    'is_core' => true,
                    'is_enabled' => false,
                ]);
            }
        }

        $invoiceModule = ModuleFacade::find('invoice');

        if ($request->prev_package_id && $request->prev_package_id != $model->package_id && $invoiceModule && settings()->get('invoice_module_active')) {
            $model->loadMissing('package', 'customer');

            $package = $model->package;
            $model->start_date = today();

            if ($package->trial_period && $package->trial_period > 0 ) {
                $model->expiry_date = today()->addDays($package->trial_period);
            } else {
                $model->expiry_date = today();
            }

            InvoiceCreate::dispatch($this->getInvoiceData($model, $package));
        }
    }

    /**
     * Clone the given resource model.
     */
    public function clone(Model $model, int $userId): Model
    {
        return $model->clone($userId)->save();
    }
}
