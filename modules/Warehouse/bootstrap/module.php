<?php

use Modules\Core\Facades\Module;
use Illuminate\Contracts\Foundation\Application;

return Module::configure('warehouse')
    ->onDeleteResetMigrations()
    ->enabled(function (Application $app) {
        //
    })
    ->disabled(function (Application $app) {
        //
    })
    ->deleted(function (Application $app) {
        //
    });
