<?php

namespace Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Contacts\Models\Company as ModelsCompany;
use Modules\Core\Facades\Module as ModuleFacade;
use Modules\Core\Models\Country;
use Modules\Invoice\Events\InvoiceCreate;
use Modules\Saas\Enums\TenantDatabaseProvision;
use Modules\Saas\Http\Requests\TenantRegisterRequest as RequestsTenantRegisterRequest;
use Modules\Saas\Models\Customer;
use Modules\Saas\Models\Package;
use Modules\Saas\Notifications\TenantDomainIntegrationNotification;
use Modules\Saas\Services\TenantService;
use Modules\Saas\Traits\TenantInvoiceTrait;

class TenantRegisterController extends Controller
{
    use TenantInvoiceTrait;
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function showForm()
    {

        $settings = settings();
        $logo = $settings->get('logo_dark');
        $enableSubdomainSignup = $settings->get('enable_subdomain_signup');
        $enableDomainSignup = $settings->get('enable_custom_domain_signup');
        $countries = Country::all();
        $packages = Package::all();

        return view('saas::registration.register', compact('countries', 'logo', 'enableSubdomainSignup', 'enableDomainSignup', 'packages'));
    }


    public function register(RequestsTenantRegisterRequest $request)
    {
        $subdomainAndDomain = $this->tenantService->handleSubdomainAndDomain($request->only(['subdomain', 'domain', 'company_name']));

        $request->merge([
            'country_id' => $request->country?->id ?? null,
            'package_id' => $request->package?->id ?? null,
        ]);

        DB::beginTransaction();
        try {
            $contact = $this->tenantService->createContact($request->all());

            $tenant = $this->tenantService->createTenant(array_merge(
                $request->all(),
                $subdomainAndDomain,
                [
                    'contact_id' => $contact->id,
                    'name' => $request->company_name,
                    'package_id' => $request->package['id'] ?? null,
                    'db_scheme' => TenantDatabaseProvision::USE_CURRENT_ACTIVE,
                    'is_active' => true
                ]
            ));

            $this->tenantService->createTenantUser($tenant);

            if ($request->input('subdomain') && !empty($request->input('subdomain')) && settings()->get('cpanel_enabled')) {
                 $this->tenantService->creatSubDomain($tenant, $request);
            }
            if ($request->input('domain') && !empty($request->input('domain')) && settings()->get('cpanel_enabled')) {
                 $this->tenantService->createDomain($tenant, $request);
            }

            $company = ModelsCompany::create([
                'name' => $request->company_name,
            ]);

            $invoiceModule = ModuleFacade::find('invoice');

            if ($invoiceModule && settings()->get('invoice_module_active')) {
                $tenant->loadMissing('package', 'customer');

                $package = $tenant->package;
                $tenant->start_date = today();

                if ($package?->trial_period && $package->trial_period > 0 ) {
                    $tenant->expiry_date = today()->addDays($package->trial_period);
                } else {
                    $tenant->expiry_date = today();
                }

                $tenant->save();
                InvoiceCreate::dispatch($this->getInvoiceData($tenant, $package));
            }

            $contact->companies()->attach($company->id);
            DB::commit();

            if (!empty($tenant->domain))
            {
                /**
                 * @var Customer $contact
                 */
                $contact->notify(new TenantDomainIntegrationNotification($tenant));
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during tenant registration: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => "Something went wrong",
            ], 500);
        }
    }
}
