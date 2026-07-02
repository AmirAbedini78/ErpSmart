<?php

use App\Http\Controllers\Builder\BuilderDefinitionController;
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

    Route::post('definitions/{builderDefinition}/archive', [BuilderDefinitionController::class, 'archive'])
        ->name('builder.definitions.archive');

    Route::post('definitions/{builderDefinition}/restore', [BuilderDefinitionController::class, 'restore'])
        ->name('builder.definitions.restore');
});
