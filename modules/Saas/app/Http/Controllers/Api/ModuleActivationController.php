<?php

namespace Modules\Saas\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Core\Facades\Module;
use Modules\Saas\Http\Requests\ModuleActivationRequest;
use Modules\Saas\Services\ModuleInitializationService;

class ModuleActivationController extends Controller
{
    public function __construct(
        protected ModuleInitializationService $service
    ){}
    /**
     * Get the deals initial board data.
     */
    public function __invoke(ModuleActivationRequest $request)
    {
        $moduleName = 'saas';
        $module = Module::findOrFail($moduleName);

        $res = $this->service->handle($moduleName, $request->input('saas_activation_code'));

        return response()->json([
            'message' => $res['message'],
        ], $res['status_code']);
    }

}
