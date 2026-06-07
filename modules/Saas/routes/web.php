<?php

use Illuminate\Support\Facades\Route;
use Modules\Saas\Http\Controllers\LandingPageController;
use Modules\Saas\Http\Controllers\TenantRegisterController;

Route::prefix('saas')->group(function () {
});
Route::get('landing', action: [LandingPageController::class, 'show'])->name('landing-page');

Route::middleware(['tenant.registration'])->group(function () {
    Route::get('/tenant/register', [TenantRegisterController::class, 'showForm'])->name('tenant.register');
    Route::post('/tenant/register', [TenantRegisterController::class, 'register']);
});

Route::get('/pages/{id}/preview', [\Modules\Saas\Http\Controllers\PageController::class, 'preview'])->name('pages.preview')
    ->middleware('auth');
