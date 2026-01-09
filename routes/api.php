<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/reports/daily', [ReportController::class, 'dailyReport']);
Route::get('/reports/monthly', [ReportController::class, 'monthlyRegister']);
Route::get('/stats', [ReportController::class, 'dashboardStats']);

use App\Http\Controllers\SyncController;
Route::post('/punches/sync', [SyncController::class, 'store']);
Route::post('/punches/import', [SyncController::class, 'store']);
