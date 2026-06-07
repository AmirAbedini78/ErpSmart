<?php

namespace Modules\Saas\Services;


use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Contacts\Models\Contact;
use Modules\Core\Database\State\DatabaseState;
use Modules\Core\Facades\Module as ModuleFacade;
use Modules\Core\Models\Model;
use Modules\Core\Settings\DefaultSettings;
use Modules\Saas\Database\State\EnsureActivityTypesArePresent;
use Modules\Saas\Database\State\EnsureCallOutcomesArePresent;
use Modules\Saas\Database\State\EnsureDefaultBrandIsPresent;
use Modules\Saas\Database\State\EnsureDefaultContactTagsArePresent;
use Modules\Saas\Database\State\EnsureDefaultPipelineIsPresent;
use Modules\Saas\Database\State\EnsureDocumentTypesArePresent;
use Modules\Saas\Database\State\EnsureIndustriesArePresent;
use Modules\Saas\Database\State\EnsureInvoicePaidStatusExists;
use Modules\Saas\Database\State\EnsureSourcesArePresent;
use Modules\Saas\Enums\TenantDatabaseProvision;
use Modules\Saas\Models\Customer;
use Modules\Saas\Models\Tenant;
use Modules\Saas\Models\TenantDatabase;
use Modules\Saas\Notifications\TenantSignupNotification;
use Modules\Saas\Services\Database\DatabaseServiceInterface;
use Modules\Saas\Services\Encryption\EncryptionServiceInterface;
use Modules\Updater\DatabaseMigrator;
use Modules\Users\Models\User;
use Modules\Saas\Services\Subdomain\SubdomainServiceInterface;
use Modules\Saas\Services\Domain\DomainServiceInterface;
use Illuminate\Support\Facades\Log;
use Modules\Saas\Http\Requests\TenantRegisterRequest;

class TenantService
{
    public function __construct(private DatabaseMigrator $migrator) {}

    public function tenantMigration(Tenant $tenant, Collection $users)
    {
        $tenant->loadMissing('package');
        $package = $tenant->package;

        $dbScheme = $tenant->db_scheme == TenantDatabaseProvision::USE_FROM_PACKAGE ? $package->db_scheme : $tenant->db_scheme;
        if (
            $dbScheme == TenantDatabaseProvision::CUSTOM_CREDENTIAL
            || $dbScheme == TenantDatabaseProvision::CREATE_SEPARATE
            || $dbScheme == TenantDatabaseProvision::TABLE_PREFIX
        ) {
            $this->migrateDatabase($users);
        } elseif ($dbScheme == TenantDatabaseProvision::USE_CURRENT_ACTIVE) {
            DatabaseState::register([
                EnsureActivityTypesArePresent::class,
                EnsureCallOutcomesArePresent::class,
                EnsureDefaultBrandIsPresent::class,
                EnsureDefaultContactTagsArePresent::class,
                EnsureDefaultPipelineIsPresent::class,
                EnsureDocumentTypesArePresent::class,
                EnsureIndustriesArePresent::class,
                EnsureSourcesArePresent::class,
            ]);

            $invoiceModule = ModuleFacade::find('invoice');
            $modules = $tenant->modules->where('name', 'invoice')->where('is_enabled', false)->toArray();

            if ($invoiceModule && $modules && count($modules) <= 0) {
                DatabaseState::register([
                    EnsureInvoicePaidStatusExists::class,
                ]);
            }

            DatabaseState::seed();
        }
    }

    public function migrateDatabase(Collection $users)
    {
        $this->migrator->run();
        DatabaseState::seed();

        if (! $users->isEmpty()) {
            foreach ($users as $user) {
                $userArr = $user->toArray();
                unset($userArr['tenant_id']);
                unset($userArr['id']);

                User::create(array_merge($userArr, [
                    'password' => $user->password,
                ]));
            }
        }
    }

    public function createDatabase(Tenant $tenant)
    {
        $tenantName = Str::limit(Str::snake($tenant->name), 30, '');
        $dbName = 'db_' . $tenantName;
        $password = $this->generateStrongPassword();

        $databaseService = app(DatabaseServiceInterface::class);
        $encryptionService = app(EncryptionServiceInterface::class);

        $databaseService->createDatabase($dbName);

        if (settings()->get('mysql_root_create_user')) {
            $databaseService->createDatabaseUser($tenantName, $password);
            $databaseService->assignUserToDatabase($dbName, $tenantName);
        }

        TenantDatabase::query()->withoutGlobalScopes()->updateOrCreate(['tenant_id' => $tenant->id], [
            'db_host' => settings()->get('db_host') ?: '127.0.0.1',
            'db_port' => settings()->get('db_port') ?: 3306,
            'database' => $dbName,
            'db_username' => $encryptionService->encrypt($tenantName),
            'db_password' => $encryptionService->encrypt($password),
        ]);
    }

    public function generateStrongPassword($length = 16)
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $specialChars = '!@#$%^&*()-_=+[]{}|;:,.<>?';

        // Combine all character sets
        $allCharacters = $uppercase . $lowercase . $numbers . $specialChars;

        // Ensure that the password has at least one character from each set
        $password = $uppercase[rand(0, strlen($uppercase) - 1)] .
            $lowercase[rand(0, strlen($lowercase) - 1)] .
            $numbers[rand(0, strlen($numbers) - 1)] .
            $specialChars[rand(0, strlen($specialChars) - 1)] .
            substr(str_shuffle($allCharacters), 0, $length - 4);

        return str_shuffle($password); // Shuffle to randomize the character positions
    }

    public function createTenantUser(Model $model)
    {
        $contact = Contact::find($model->contact_id);
        $password = $this->generateStrongPassword();

        $user = User::create([
            'name' => $contact->getGuestDisplayName(),
            'email' => $contact->email,
            'timezone' => auth()->user()->timezone ?? 'UTC',
            'password' => Hash::make($password),
            'super_admin' => true,
            'access_api' => true,
            'time_format' => DefaultSettings::get('time_format'),
            'date_format' => DefaultSettings::get('date_format'),
        ]);
        $user->tenant_id = $model->id;
        $user->save();

        $user->notify(new TenantSignupNotification($model, $password));
    }


    public function createTenant(array $data): Tenant
    {
        $tenant = new Tenant();
        $tenant->fill($data);
        $tenant->save();

        return $tenant;
    }

    public function createContact(array $data): Customer
    {
        return Customer::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'job_title' => $data['job_title'] ?? null,
            'state' => $data['state']?? null,
            'country_id' => $data['country_id']?? null,
            'street' => $data['street']?? null,
            'uuid' => (string) Str::uuid(),
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
        ]);
    }

    public function handleSubdomainAndDomain(array $data): array
    {
        $subdomain_url = null;
        $domain_url = null;

        $subdomain = (!isset($data['subdomain']) && !isset($data['domain'])) ? Str::replace(
            ' ',
            '',
            $data['company_name']
        ) : $data['subdomain'];

        if (isset($subdomain)) {
            $domain = settings()->get('domain') ?? request()->getHost();
            $subdomain_url = "https://" . strtolower($subdomain) . '.' . $domain;
        }

        if (!empty($data['domain'])) {
            $domain_url = "https://" . strtolower($data['domain']);
        }

        return compact('subdomain_url', 'domain_url', 'subdomain');
    }

    public function creatSubDomain(Model $model, TenantRegisterRequest $request): void
    {
        $subdomain = $request->input('subdomain');
        if ($subdomain) {
            if (env('APP_ENV') !== 'local') {
                $subdomainService = app(SubdomainServiceInterface::class);
                try {
                    $subdomainService->createSubdomain($subdomain, settings()->get('domain') ?? request()->getHost(), base_path());
                } catch (Exception $exception) {
                    Log::error('Failed to create subdomain', ['subdomain' => $subdomain, 'domain' => settings()->get('domain') ?? request()->getHost(), 'error' => $exception->getMessage()]);
                    return;
                }
            }
            $model->is_active = true;
            $model->save();
        }
    }
    public function createDomain(Model $model, TenantRegisterRequest $request): void
    {
        $domain = $request->input('domain');
        if ($domain) {
            if (env('APP_ENV') !== 'local') {
                $domainService = app(DomainServiceInterface::class);
                $domainService->createDomain($domain, base_path());
            }
            $model->is_active = true;
            $model->save();
        }
    }

    public function deleteTenant(Tenant $tenant): void
    {
        $tenant->loadMissing('package');
        $package = $tenant->package;

        $dbScheme = $tenant->db_scheme == TenantDatabaseProvision::USE_FROM_PACKAGE ? $package->db_scheme : $tenant->db_scheme;
        if (
            $dbScheme == TenantDatabaseProvision::CUSTOM_CREDENTIAL
            || $dbScheme == TenantDatabaseProvision::CREATE_SEPARATE
        ) {
            $tenant->loadMissing('database');
            $this->clearDatabase($tenant->database, $dbScheme);
        } elseif ($dbScheme == TenantDatabaseProvision::USE_CURRENT_ACTIVE) {
            DatabaseState::register([
                EnsureActivityTypesArePresent::class,
                EnsureCallOutcomesArePresent::class,
                EnsureDefaultBrandIsPresent::class,
                EnsureDefaultContactTagsArePresent::class,
                EnsureDefaultPipelineIsPresent::class,
                EnsureDocumentTypesArePresent::class,
                EnsureIndustriesArePresent::class,
                EnsureSourcesArePresent::class,
            ]);
            DatabaseState::seed();
        }
    }

    public function clearDatabase(Tenant $tenant, $dbScheme): void
    {
        $encryptionService = app(EncryptionServiceInterface::class);
        $databaseService = app(DatabaseServiceInterface::class);

        $landLordDbConfig = DB::connection()->getConfig();

        if ($dbScheme == TenantDatabaseProvision::CREATE_SEPARATE) {
            $databaseService->removeTenantDatabaseAndUser($tenant->database->database, $this->addPrefix($encryptionService->decrypt($tenant->database->db_username)));
        } else {
            $this->resetDBConnection($this->getTenantDBConfig($landLordDbConfig, $tenant, $dbScheme));

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Artisan::call('migrate:reset', [
                '--force' => true,
            ]);
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

    }

    private function resetDBConnection($dbConfig)
    {
        config()->set('database.connections.'.DB::connection()->getName(), $dbConfig);

        DB::purge(DB::connection()->getName());
        DB::reconnect(DB::connection()->getName());
    }

    private function getTenantDBConfig($dbConfig, $tenant, $dbScheme)
    {
        $encryptionService = app(EncryptionServiceInterface::class);

        if ($dbScheme === TenantDatabaseProvision::TABLE_PREFIX) {
            $dbConfig['table_prefix'] = $tenant->id.'_';

            return $dbConfig;
        }

        $dbConfig['host'] = $tenant->database->db_host;
        $dbConfig['port'] = $tenant->database->db_port;
        $dbConfig['database'] = $this->addPrefix($tenant->database->database);
        $dbConfig['username'] = $this->addPrefix($encryptionService->decrypt($tenant->database->db_username));
        $dbConfig['password'] = $encryptionService->decrypt($tenant->database->db_password);

        return $dbConfig;
    }

    public function addPrefix($text)
    {
        $cpanelUsername = settings()->get('cpanel_username');
        if (empty($cpanelUsername) || !settings()->get('cpanel_enabled')) {
            return $text;
        }

        return str_starts_with($text, $cpanelUsername) ? $text : $cpanelUsername.'_'.$text;
    }
}
