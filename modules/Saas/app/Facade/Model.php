<?php

namespace Modules\Saas\Facade;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Modules\Saas\Services\ModelService;

/**
 * @method static Collection getAllModels()
 * @method static Collection getQuotableModels()
 *
 * @see \Modules\Saas\Services\ModelService
 */
class Model extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ModelService::class;
    }
}
