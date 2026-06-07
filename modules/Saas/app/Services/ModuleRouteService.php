<?php

namespace Modules\Saas\Services;

use Illuminate\Routing\Router;

class ModuleRouteService
{
    public function __construct(private readonly Router $router) {}

    public function getModuleRoutes(string $module)
    {
        $routes = collect($this->router->getRoutes())
            ->filter(function ($route) use ($module) {
                $action = $route->getAction();

                return isset($action['controller']) &&
                    str_contains($action['controller'], 'Modules\\'.$module);
            });

        return $routes->map(function ($route) {
            return [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
            ];
        });
    }
}
