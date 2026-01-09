<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', [WebController::class, 'dashboard'])->name('dashboard');
Route::get('/reports/monthly-view', [WebController::class, 'monthlyReportView'])->name('reports.monthly');

// Administration Routes
Route::resource('companies', \App\Http\Controllers\CompanyController::class);
Route::resource('branches', \App\Http\Controllers\BranchController::class);
Route::resource('locations', \App\Http\Controllers\LocationController::class);
Route::resource('departments', \App\Http\Controllers\DepartmentController::class);
Route::resource('shifts', \App\Http\Controllers\ShiftController::class);
Route::resource('employees', \App\Http\Controllers\EmployeeController::class);
Route::post('employees/bulk-assign-shift', [\App\Http\Controllers\EmployeeController::class, 'bulkAssignShift'])->name('employees.bulkAssignShift');
