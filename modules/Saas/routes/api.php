<?php

use Illuminate\Support\Facades\Route;
use Modules\Saas\Http\Controllers\DatabaseConnectionController;
use Modules\Saas\Http\Controllers\ImageController;
use Modules\Saas\Http\Controllers\PageController;
use Modules\Saas\Http\Controllers\TemplateController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('saas/test-database-connection', action: DatabaseConnectionController::class);
    Route::get('', DatabaseConnectionController::class);
    Route::get('saas/pages/{id}', [PageController::class, 'show']);
    Route::put('saas/pages/{id}', [PageController::class, 'update']);
    Route::get('saas/pages/', [PageController::class, 'index']);
    Route::get('saas/tenants/{id}', \Modules\Saas\Http\Controllers\Api\TenantController::class);
    Route::get('saas/packages/{id}', \Modules\Saas\Http\Controllers\Api\PackageController::class);
    Route::post('saas/upload-image',  [ImageController::class, 'upload'])->withoutMiddleware([Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::post('saas/templates/upload/{id}', [TemplateController::class, 'upload']);
    Route::get('saas/templates', [TemplateController::class, 'list']);
    Route::get('saas/templates/{uuid}', [TemplateController::class, 'getTemplate']);
    Route::get('saas/download', [TemplateController::class, 'download']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('generate-translations', [\Modules\Saas\Http\Controllers\Api\GenerateTranslationController::class, 'generateTranslations'])->name('api.generate-translations');
    Route::post('modules/saas/activation', \Modules\Saas\Http\Controllers\Api\ModuleActivationController::class)->name('api.saas.activate-module');
});
Route::post('/tenant/register', [\Modules\Saas\Http\Controllers\TenantRegisterController::class, 'register']);
