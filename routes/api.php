<?php

use App\Http\Controllers\Builder\BuilderDefinitionController;
use App\Http\Controllers\Builder\BuilderPublishApprovalRequestController;
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
