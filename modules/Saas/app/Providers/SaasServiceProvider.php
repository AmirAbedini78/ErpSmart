<?php

namespace Modules\Saas\Providers;

use Closure;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Facades\Innoclapps;
use Modules\Core\Facades\Module as ModuleFacade;
use Modules\Core\Settings\Contracts\Manager as ManagerContract;
use Modules\Core\Settings\SettingsMenu as SettingsMenuManager;
use Modules\Core\Settings\SettingsMenuItem;
use Modules\Core\Support\ModuleServiceProvider;
use Modules\Invoice\Events\InvoiceCreated;
use Modules\Invoice\Events\InvoicePaidEvent;
use Modules\Saas\Database\CustomConnectionFactory;
use Modules\Saas\Database\SettingsDriver\DatabaseStore;
use Modules\Saas\Mail\TenantDomainIntegration;
use Modules\Saas\Mail\TenantExpiry;
use Modules\Saas\Mail\TenantOverdue;
use Modules\Saas\Models\Setting;
use Modules\Saas\Services\Apache\ApacheService;
use Modules\Saas\Actions\DetectTenantAction;
use Modules\Saas\Facade\Model;
use Modules\Saas\Http\Middleware\TenantMiddleware;
use Modules\Saas\Http\Middleware\TenantModuleMiddleware;
use Modules\Saas\Http\Middleware\TenantRegistrationMiddleware;
use Modules\Saas\Jobs\CreateTenantInvoice;
use Modules\Saas\Jobs\OverdueNotification;
use Modules\Saas\Jobs\TenantExpiryNotification;
use Modules\Saas\Listeners\InvoiceCreated as InvoiceCreateListener;
use Modules\Saas\Listeners\InvoicePaid as InvoicePaidListener;
use Modules\Saas\Mail\TenantSignup;
use Modules\Saas\Models\Scopes\TenantScope;
use Modules\Saas\Notifications\TenantSignupNotification;
use Modules\Saas\Observers\QuotaObserver;
use Modules\Saas\Resources\Package;
use Modules\Saas\Resources\Page;
use Modules\Saas\Resources\Quota;
use Modules\Saas\Resources\Tenant;
use Modules\Saas\Services\Cpanel\CpanelService;
use Modules\Saas\Services\Database\DatabaseService;
use Modules\Saas\Services\Database\DatabaseServiceInterface;
use Modules\Saas\Services\Domain\DomainServiceInterface;
use Modules\Saas\Services\Encryption\CustomEncryptionService;
use Modules\Saas\Services\Encryption\EncryptionServiceInterface;
use Modules\Saas\Services\Subdomain\SubdomainServiceInterface;
use Modules\Saas\Services\TenantMigrationService;
use Modules\Saas\Settings\SettingMenuStatic;
use Modules\Saas\Settings\SettingsMenu;

class SaasServiceProvider extends ModuleServiceProvider
{
    protected array $resources = [
        Tenant::class,
        Package::class,
        Quota::class,
        Page::class,
    ];

    protected array $mailableTemplates = [
        TenantSignup::class,
        TenantDomainIntegration::class,
        TenantExpiry::class,
        TenantOverdue::class,
    ];

    protected array $notifications = [
        TenantSignupNotification::class,
    ];

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerViews();
        $router = $this->app['router'];
        $router->aliasMiddleware('tenant.registration', TenantRegistrationMiddleware::class);
        Innoclapps::permissions(function ($manager) {
            $group = ['name' => 'tenants', 'as' => 'tenants'];

            $manager->group($group, function ($manager) {
                $manager->view('view', [
                    'as' => __('core::role.capabilities.view'),
                    'permissions' => [
                        'view own tenants' => __('core::role.capabilities.owning_only'),
                        'view all tenants' => __('core::role.capabilities.all', ['resourceName' => 'Tenants']),
                        'view team tenants' => __('users::team.capabilities.team_only'),
                    ],
                ]);

                $manager->view('edit', [
                    'as' => __('core::role.capabilities.edit'),
                    'permissions' => [
                        'edit own tenants' => __('core::role.capabilities.owning_only'),
                        'edit all tenants' => __('core::role.capabilities.all', ['resourceName' => 'Tenants']),
                        'edit team tenants' => __('users::team.capabilities.team_only'),
                    ],
                ]);

                $manager->view('delete', [
                    'as' => __('core::role.capabilities.delete'),
                    'revokeable' => true,
                    'permissions' => [
                        'delete own tenants' => __('core::role.capabilities.owning_only'),
                        'delete any tenants' => __('core::role.capabilities.all', ['resourceName' => 'Tenants']),
                        'delete team tenants' => __('users::team.capabilities.team_only'),
                    ],
                ]);

                $manager->view('bulk_delete', [
                    'permissions' => [
                        'bulk delete tenants' => __('core::role.capabilities.bulk_delete'),
                    ],
                ]);
            });
        });
    }

    /**
     * Register any module services.
     */
    public function register(): void
    {
        CustomConnectionFactory::resolveDatabaseConnections();

        $this->app->singleton(SettingsMenuManager::class);
        $this->app->extend(SettingsMenuManager::class, function ($app) {
            $version = \Modules\Core\Application::VERSION;
            if ($version === '1.5.0')
                return new SettingMenuStatic();

            return new SettingsMenu;
        });

        $this->registerResources();

        (new DetectTenantAction)->handle();

        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Configure the module.
     */
    protected function setup(): void
    {
        Innoclapps::vite('resources/js/app.js', 'modules/' . $this->moduleNameLower() . '/build');
    }

    /**
     * Register module commands.
     */
    protected function registerCommands(): void
    {
        $this->app->bind(EncryptionServiceInterface::class, CustomEncryptionService::class);

        $this->app->bind(SubdomainServiceInterface::class, function ($app) {
            if (settings()->get('cpanel_enabled')) {
                return new CpanelService;
            }

            return new ApacheService();
        });

        $this->app->bind(DomainServiceInterface::class, function ($app) {
            if (settings()->get('cpanel_enabled')) {
                return new CpanelService;
            }

            return new ApacheService;
        });

        $this->app->bind(DatabaseServiceInterface::class, function ($app) {
            if (settings()->get('cpanel_enabled')) {
                return new CpanelService;
            }

            return new DatabaseService;
        });

        Event::listen(MigrationsEnded::class, function (MigrationsEnded $event) {
            if (! $this->app->has('tenant')) {
                settings()->set(['add_tenant_id_column' => true])->save();

                $tenantMigrationService = app(TenantMigrationService::class);
                $tenantMigrationService->addTenantIdToTables();
                settings()->set(['add_tenant_id_column' => false])->save();
            }
        });

        if (! $this->app->has('tenant') && settings()->get('add_tenant_id_column')) {
            $tenantMigrationService = app(TenantMigrationService::class);
            $tenantMigrationService->addTenantIdToTables();
            settings()->set(['add_tenant_id_column' => false])->save();
        }

        if ($this->app->has('tenant')) {
            $host = request()->getHost();
            $baseDomain = $this->extractBaseDomain($host);

            config()->set('settings.drivers.database.driver', \Modules\Saas\Settings\Stores\DatabaseStore::class);
            $this->app->extend(ManagerContract::class, function ($manager, $app) {
                foreach ($app['config']->get('settings.drivers', []) as $driver => $params) {
                    $manager->registerStore($driver, $params);
                }

                return $manager->driver('database');
            });

            Config::set('sanctum.stateful', array_merge(config('sanctum.stateful'), [
                $baseDomain,
                '.' . $baseDomain,
                $host,
            ]));
        }

        $models = Model::getAllModels();
        $cannotBeGlobalScoped = [
            \Modules\Saas\Models\Tenant::class,
            \Modules\Saas\Models\Package::class,
            \Modules\Saas\Models\Quota::class,
            \Modules\Core\Models\CacheModel::class
        ];

        foreach ($models as $model) {
            if (in_array($model, $cannotBeGlobalScoped)) {
                continue;
            }
            $model::addGlobalScope(new TenantScope);
            $model::creating(function ($model) {
                if (app()->has('tenant') && Schema::hasColumn($model->getTable(), 'tenant_id')) {
                    $model->tenant_id = app('tenant')->id;
                }
            });
            $model::observe(QuotaObserver::class);
        }

        $kernel = $this->app->make(Kernel::class);
//        $kernel->pushMiddleware(TenantMiddleware::class);
        $kernel->appendMiddlewareToGroup('web', TenantModuleMiddleware::class);
        $kernel->appendMiddlewareToGroup('api', TenantModuleMiddleware::class);

        $invoiceModule = ModuleFacade::find('invoice');
        if ($invoiceModule && settings()->get('invoice_module_active')) {
            Event::listen(InvoiceCreated::class, InvoiceCreateListener::class);
            Event::listen(InvoicePaidEvent::class, InvoicePaidListener::class);
        }

        (new TenantMiddleware())->handle();
    }

    private function extractBaseDomain($host)
    {
        $parts = explode('.', $host);
        $count = count($parts);

        return $count >= 2 ? $parts[$count - 2] . '.' . $parts[$count - 1] : $host;
    }

    /**
     * Schedule module tasks.
     */
    protected function scheduleTasks(Schedule $schedule): void
    {
        $invoiceModule = ModuleFacade::find('invoice');
        if ($invoiceModule && settings()->get('invoice_module_active')) {
            $schedule->job(CreateTenantInvoice::class)->withoutOverlapping()->daily();
        }

        $schedule->job(TenantExpiryNotification::class)->withoutOverlapping()->daily();
        $schedule->job(OverdueNotification::class)->withoutOverlapping()->daily();
    }

    /**
     * Provide the data to share on the front-end.
     */
    protected function scriptData(): Closure|array
    {
        return [
            'saas' => [],
        ];
    }

    /**
     * Provide the module name.
     */
    protected function moduleName(): string
    {
        return 'Saas';
    }

    /**
     * Provide the module name.
     */
    protected function name(): string
    {
        return 'Saas';
    }

    /**
     * Provide the module name in lowercase.
     */
    protected function moduleNameLower(): string
    {
        return 'saas';
    }

    /**
     * Register the settings menu items for the resource
     */
    public function settingsMenu(): array
    {
        $version = \Modules\Core\Application::VERSION;

        if ($this->app->has('tenant')) {
            return [];
        }

        if ($version == '1.5.0') {
            return [
                SettingsMenuItem::make(__('saas::saas.setting.title'), '/settings/saas')
                    ->icon('WrenchScrewdriver')
                    ->order(41)
            ];
        } else {
            return [
                SettingsMenuItem::make($this->name(), __('saas::saas.setting.title'))
                    ->path('/Saas')
                    ->icon('WrenchScrewdriver')
                    ->order(41),
            ];
        }
    }

}
