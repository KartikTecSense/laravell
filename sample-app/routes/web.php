<?php

use App\Http\Controllers\DatabaseBackupController;
use App\Http\Middleware\DbBackupBasicAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/db-backup', DatabaseBackupController::class)
    ->middleware(['throttle:10,1', DbBackupBasicAuth::class]);
