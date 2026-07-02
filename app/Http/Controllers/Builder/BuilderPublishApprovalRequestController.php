<?php

namespace App\Http\Controllers\Builder;

use App\Models\BuilderDefinition;
use App\Models\BuilderPublishApprovalRequest;
use App\Services\Builder\BuilderPublishApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\ApiController;

class BuilderPublishApprovalRequestController extends ApiController
{
    public function index(BuilderDefinition $builderDefinition): JsonResponse
    {
        return $this->response(
            $builderDefinition->publishApprovalRequests()
                ->with('auditLogs')
                ->latest()
                ->get()
        );
    }

    public function store(
        BuilderDefinition $builderDefinition,
        BuilderPublishApprovalService $approval
    ): JsonResponse {
        return $this->response([
            'approval_request' => $approval->requestApproval($builderDefinition),
            'approval_does_not_publish' => true,
            'publish_executed' => false,
            'runtime_writes_performed' => 0,
        ], JsonResponse::HTTP_CREATED);
    }

    public function approve(
        BuilderPublishApprovalRequest $approvalRequest,
        BuilderPublishApprovalService $approval,
        Request $request
    ): JsonResponse {
        return $this->response([
            'approval_request' => $approval->approve($approvalRequest, $request->string('note')->toString() ?: null),
            'approval_does_not_publish' => true,
            'publish_executed' => false,
            'runtime_writes_performed' => 0,
        ]);
    }

    public function reject(
        BuilderPublishApprovalRequest $approvalRequest,
        BuilderPublishApprovalService $approval,
        Request $request
    ): JsonResponse {
        return $this->response([
            'approval_request' => $approval->reject($approvalRequest, $request->string('note')->toString() ?: null),
            'approval_does_not_publish' => true,
            'publish_executed' => false,
            'runtime_writes_performed' => 0,
        ]);
    }

    public function revoke(
        BuilderPublishApprovalRequest $approvalRequest,
        BuilderPublishApprovalService $approval,
        Request $request
    ): JsonResponse {
        return $this->response([
            'approval_request' => $approval->revoke($approvalRequest, $request->string('note')->toString() ?: null),
            'approval_does_not_publish' => true,
            'publish_executed' => false,
            'runtime_writes_performed' => 0,
        ]);
    }
}
