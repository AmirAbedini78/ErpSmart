<?php

use App\Http\Controllers\Builder\BuilderDefinitionController;
use App\Http\Controllers\Builder\BuilderPublishApprovalRequestController;
use App\Http\Controllers\Builder\BuilderPublishExecutionController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::middleware(['auth:sanctum', 'admin'])->prefix('builder')->group(function () {
    Route::apiResource('definitions', BuilderDefinitionController::class)
        ->parameters(['definitions' => 'builderDefinition'])
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::post('definitions/{builderDefinition}/validate', [BuilderDefinitionController::class, 'validateDefinition'])
        ->name('builder.definitions.validate');

    Route::post('definitions/{builderDefinition}/preview', [BuilderDefinitionController::class, 'preview'])
        ->name('builder.definitions.preview');

    Route::post('definitions/{builderDefinition}/publish-readiness', [BuilderDefinitionController::class, 'publishReadiness'])
        ->name('builder.definitions.publish-readiness');

    Route::post('definitions/{builderDefinition}/publish-dry-run', [BuilderDefinitionController::class, 'publishDryRun'])
        ->name('builder.definitions.publish-dry-run');

    Route::post('definitions/{builderDefinition}/publish-candidate-snapshot', [BuilderDefinitionController::class, 'publishCandidateSnapshot'])
        ->name('builder.definitions.publish-candidate-snapshot');

    Route::get('definitions/{builderDefinition}/approved-candidate-preflight', [BuilderDefinitionController::class, 'approvedCandidatePreflight'])
        ->name('builder.definitions.approved-candidate-preflight');

    Route::get('definitions/{builderDefinition}/publish-executions', [BuilderPublishExecutionController::class, 'index'])
        ->name('builder.definitions.publish-executions.index');

    Route::post('definitions/{builderDefinition}/publish-executions', [BuilderPublishExecutionController::class, 'store'])
        ->name('builder.definitions.publish-executions.store');

    Route::post('publish-executions/{execution}/validate-staged-files', [BuilderPublishExecutionController::class, 'validateStagedFiles'])
        ->name('builder.publish-executions.validate-staged-files');

    Route::post('publish-executions/{execution}/runtime-write-plan', [BuilderPublishExecutionController::class, 'runtimeWritePlan'])
        ->name('builder.publish-executions.runtime-write-plan');

    Route::get('definitions/{builderDefinition}/publish-approval-requests', [BuilderPublishApprovalRequestController::class, 'index'])
        ->name('builder.definitions.publish-approval-requests.index');

    Route::post('definitions/{builderDefinition}/publish-approval-requests', [BuilderPublishApprovalRequestController::class, 'store'])
        ->name('builder.definitions.publish-approval-requests.store');

    Route::post('publish-approval-requests/{approvalRequest}/approve', [BuilderPublishApprovalRequestController::class, 'approve'])
        ->name('builder.publish-approval-requests.approve');

    Route::post('publish-approval-requests/{approvalRequest}/reject', [BuilderPublishApprovalRequestController::class, 'reject'])
        ->name('builder.publish-approval-requests.reject');

    Route::post('publish-approval-requests/{approvalRequest}/revoke', [BuilderPublishApprovalRequestController::class, 'revoke'])
        ->name('builder.publish-approval-requests.revoke');

    Route::post('definitions/{builderDefinition}/archive', [BuilderDefinitionController::class, 'archive'])
        ->name('builder.definitions.archive');

    Route::post('definitions/{builderDefinition}/restore', [BuilderDefinitionController::class, 'restore'])
        ->name('builder.definitions.restore');
});
