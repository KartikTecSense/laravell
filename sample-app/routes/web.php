<?php

use App\Http\Controllers\DatabaseBackupController;
use App\Http\Middleware\DbBackupBasicAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Pinged on a schedule so the Aiven free-plan MySQL isn't powered off for inactivity.
Route::get('/ping-db', function () {
    try {
        DB::select('select 1');
    } catch (Throwable $e) {
        report($e);

        return response()->json(['db' => 'down'], 503);
    }

    return response()->json(['db' => 'ok']);
})->middleware('throttle:10,1');

Route::get('/db-backup', DatabaseBackupController::class)
    ->middleware(['throttle:10,1', DbBackupBasicAuth::class]);
