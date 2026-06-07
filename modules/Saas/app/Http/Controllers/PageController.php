<?php

namespace Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Installer\DatabaseTest;
use Modules\Installer\PrivilegesChecker;
use Modules\Saas\Enums\PageStatus;
use Modules\Saas\Models\Package;
use Modules\Saas\Models\Page;
use Modules\Saas\Models\Template;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{

    public function index(Request $request)
    {
        $page = Page::where('status', PageStatus::PUBLISHED->value)->get();
        return response()->json($page, Response::HTTP_OK);
    }


    public function show($id)
    {
        $page = Page::findOrFail($id);
        $cssUrls = null;
        $jsUrls = null;
        if($page->template_id){
            $template = Template::where('id', $page->template_id)->firstOrFail();
            $indexContent = Storage::get("public/{$template->path}/{$template->index_file}");
            $cssFiles = Storage::files("public/{$template->path}/assets/css");
            $cssUrls = array_map(fn($file) => asset(Storage::url(str_replace('public/', '', $file))), $cssFiles);
            $cssUrls = array_filter($cssUrls, fn($file) => Str::contains($file, '.css'));
            $jsFiles = Storage::files("public/{$template->path}/assets/js");
            $jsUrls = array_map(fn($file) => asset(Storage::url(str_replace('public/', '', $file))), $jsFiles);
            $jsUrls = array_filter($jsUrls, fn($file) => Str::contains($file, '.js'));
        }

        return response()->json([
            'id' => $page->id,
            'name' => $page->name,
            'status' => $page->status,
            'html' => $page->html,
            'template_id' => $page->template_id,
            'inline_css' => $page->css,
            'external_css' => $cssUrls ? array_values($cssUrls) : '',
            'external_scripts' => $jsUrls ? array_values($jsUrls) : '',
        ]);
    }


    private function extractCSS($markdown)
    {
        preg_match('/<style>(.*?)<\/style>/s', $markdown, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }
    /**
     * Update the page details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'name' => 'nullable|string',
            'status' => 'nullable|string',
            'html' => 'nullable|string',
            'css' => 'nullable|string'
        ]);

        $page = Page::findOrFail($id);
        $page->update([
            'name' => $page->name,
            'html' => $request->input('html'),
            'css' => $request->input('css'),
            'status' => $request->input('status'),
        ]);

        return response()->json($page);
    }

    public function preview($id)
    {
        $settings = settings();

        $page = Page::findOrFail($id);
        $logoPath = $settings->get('logo_dark');
        $fullLogoUrl = asset($logoPath);

        $indexContent = $page->html;
        $cssContent = $page->css;
        $packages = Package::with('quotas')->get()->map(function ($package) {
            return [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'base_price' => $package->base_price,
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

}
