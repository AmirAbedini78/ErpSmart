<?php

namespace App\Http\Controllers\Builder;

use App\Models\BuilderDefinition;
use App\Services\Builder\BuilderPublishExecutionPreparationService;
use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\ApiController;

class BuilderPublishExecutionController extends ApiController
{
    public function index(BuilderDefinition $builderDefinition): JsonResponse
    {
        return $this->response(
            $builderDefinition->publishExecutions()
                ->latest()
                ->paginate(request()->integer('per_page', 15))
        );
    }

    public function store(
        BuilderDefinition $builderDefinition,
        BuilderPublishExecutionPreparationService $preparation
    ): JsonResponse {
        return $this->response($preparation->prepare($builderDefinition), JsonResponse::HTTP_CREATED);
    }
}
