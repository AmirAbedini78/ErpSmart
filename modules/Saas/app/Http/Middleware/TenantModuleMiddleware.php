<?php

namespace Modules\Saas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Core\Application;
use Modules\Core\Facades\Innoclapps;
use Modules\Core\Facades\Menu;
use Modules\Core\Facades\Module;
use Modules\Core\Facades\SettingsMenu;
use Modules\Saas\Services\ModuleInit;
use Modules\Saas\Services\ModuleRouteService;
use Symfony\Component\HttpFoundation\Response;

class TenantModuleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $moduleInit = new ModuleInit();
        $moduleInit->handle('saas');

        if (app()->has('tenant')) {

            $isGetModules = $request->isMethod(Request::METHOD_GET) && $request->is('api/modules');

            $isModuleRoute = $request->is('api/modules*') && !$isGetModules && !$request->is('api/modules*/activation') ;
            if ($isModuleRoute) {
                abort(403, "Action unauthorized.");
            }

            $tenant = app('tenant');
            $tenant->loadMissing('modules');

            $allMenu = Menu::get();
            $allSettingMenu = collect(SettingsMenu::all());

            $resources = Application::registeredResources();

            $moduleRouteService = app(ModuleRouteService::class);
            $modules = $tenant->modules->pluck('is_enabled', 'name')->toArray();

            $currentRoute = $request->route();

            foreach ($modules as $module => $status) {
                if ($status) {
                    continue;
                }

                $module = Module::findOrFail($module);
                $routes = $moduleRouteService->getModuleRoutes($module->getName());

                if ($currentRoute && $routes->contains('uri', $currentRoute->uri())) {
                    abort(404);
                }

                $resources->filter(function ($resource) use ($module) {
                    return Str::startsWith($resource::$model, 'Modules\\'.$module->getName());
                })->each(function ($resource) use ($allSettingMenu, $currentRoute, $allMenu) {

                    if (Str::contains($currentRoute->uri(), '{resource}') && $currentRoute->parameters()['resource'] == $resource->associateableName()) {
                        abort(404);
                    }

                    foreach ($resource->menu() as $menuItem) {
                        $hasMenuItem = $allMenu->filter(function ($item) use ($menuItem) {
                            return $item->id === $menuItem->id;
                        });

                        if ($hasMenuItem->count() > 0) {
                            foreach ($hasMenuItem as $index => $item) {
                                $allMenu->forget($index);
                            }
                        }
                    }

                    foreach ($resource->settingsMenu() as $menuItem) {
                        $allSettingMenu->each(function ($item) use ($menuItem) {
                            if ($item->getId() === $menuItem->getId()) {
                                SettingsMenu::forget($item->getId());
                            }
                        });
                    }
                });
            }

            Menu::clear();
            Menu::register(function () use ($allMenu) {
                return $allMenu;
            });
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (settings()->get('run_optimize_command') === true)
        {
            Innoclapps::optimize();
            settings()->set(['run_optimize_command' => false])->save();

        }
    }
}
