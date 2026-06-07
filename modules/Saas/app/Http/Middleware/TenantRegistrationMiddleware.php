<?php

namespace Modules\Saas\Http\Middleware; 

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantRegistrationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $settings = settings();

        if (!$settings->get('tenant_registration')) {
            throw new NotFoundHttpException();
        }

        return $next($request);
    }
}