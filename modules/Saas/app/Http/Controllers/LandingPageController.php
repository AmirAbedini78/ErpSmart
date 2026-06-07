<?php

namespace Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Core\Models\Setting;
use Modules\Saas\Models\Page;
use Modules\Saas\Models\Package;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Blade;

class LandingPageController extends Controller
{

    protected static $settings = null;

    public function show()
    {
        $settings = settings();

        if ($settings->get('enable_landing_page_url')) {

            if ($settings->get('mode') === 'proxy') {

                $landingPageUrl = $settings->get('landing_page_url');

                $htmlContent = $this->getUrlContent($landingPageUrl);


                return response($htmlContent)->header('Content-Type', 'text/html');
            }


            if ($settings->get('mode') === 'redirection') {

                return redirect($settings->get('landing_page_url'));
            }
        }
        $page = Page::findOrFail($settings->get('landing_page'));
        $logoPath = $settings->get('logo_dark');
        $fullLogoUrl = asset($logoPath);

        $indexContent = $page->html;
        $cssContent = $page->css;
        $packages = Package::with('quotas')->get()->map(function ($package) {
            return [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'base_price' => money($package->base_price, settings()->get('currency')),
                'recurring_period' => $package->reocurring_period,
                'trial_period' => $package->trial_period,
                'has_domain' => $package->has_domain,
                'has_subdomain' => $package->has_subdomain,
                'db_scheme' => $package->db_scheme,
                'quotas' => $package->quotas->map(function ($quota) {
                    return [
                        'id' => $quota->id,
                        'name' => $quota->name,
                        'description' => $quota->description,
                        'models' => $this->getModels($quota->models),
                        'limit' => $quota->pivot->limit,
                    ];
                }),
            ];
        });
        $dynamicData = [
            'PAGE_BUILDER_BASE_URL' => url('/'),
            'PAGE_BUILDER_PAGE_URL' => url('/'),
            'SAAS_LOGO' => $fullLogoUrl,
            'PACKAGES' => $packages,
        ];


        $htmlContent = $this->replaceVariables($indexContent, $dynamicData);
        $htmlWithCss = "<style>{$cssContent}</style>" . $htmlContent;
        return view('saas::landing-page')->with([
            'renderedHtml' => $htmlWithCss
        ])->render();
    }


    private function replaceVariables($html, $data)
    {
        return Blade::render($html, $data);
    }

    public function getModels($models)
    {
        $models = [];
        foreach ($models as $model) {
            $modelArr = explode('\\', $model);
            $models[] = $modelArr[count($modelArr) - 1];
        }

        return $models;
    }


    private function getUrlContent($url)
    {
        $client = new Client();
        try {
            $response = $client->get($url);
            return (string) $response->getBody();
        } catch (\Exception $e) {
            return "Error fetching content from URL: " . $e->getMessage();
        }
    }


    public static function loadSettings()
    {
        if (self::$settings === null) {
            $driver = config('settings.driver', 'json');
            if ($driver === 'database') {
                self::$settings = Setting::where('tenant_id', app('tenant')->id ?? null)->pluck('value', 'key')->toArray();
            } else {
                $path = storage_path('settings.json');
                self::$settings = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
            }
        }
        return self::$settings;
    }
}
