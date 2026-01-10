<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

if (app()->environment('production') || true) { // Force for now
    \Illuminate\Support\Facades\URL::forceScheme('https');
}

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

// Temporary Setup Route
// Temporary Setup Route
Route::get('/setup-admin', function () {
    $user = \App\Models\User::updateOrCreate(
        ['email' => 'admin@apextime.in'],
        [
            'name' => 'Admin User',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]
    );
    \Illuminate\Support\Facades\Auth::login($user);
    return redirect('/dashboard');
});

// Auth Routes
Route::get('/login', [\App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect('/dashboard');
    });

    Route::get('/dashboard', [WebController::class, 'dashboard'])->name('dashboard');
    Route::get('/reports/monthly-view', [WebController::class, 'monthlyReportView'])->name('reports.monthly');

    Route::get('/reports/daily-view', [WebController::class, 'dailyReportView'])->name('reports.daily');
    Route::get('/reports/weekly-view', [WebController::class, 'weeklyReportView'])->name('reports.weekly');

    // Data/Export Routes
    Route::get('/reports/detailed', [\App\Http\Controllers\ReportController::class, 'detailedReport']); // For JSON data
    Route::get('/reports/export/monthly', [\App\Http\Controllers\ReportController::class, 'monthlyExport'])->name('reports.monthly.export');
    Route::get('/reports/export/daily', [\App\Http\Controllers\ReportController::class, 'dailyExport'])->name('reports.daily.export');
    Route::get('/reports/export/weekly', [\App\Http\Controllers\ReportController::class, 'weeklyExport'])->name('reports.weekly.export');

    // Administration Routes
    Route::resource('companies', \App\Http\Controllers\CompanyController::class);
    Route::resource('branches', \App\Http\Controllers\BranchController::class);
    Route::resource('locations', \App\Http\Controllers\LocationController::class);
    Route::resource('departments', \App\Http\Controllers\DepartmentController::class);
    Route::resource('shifts', \App\Http\Controllers\ShiftController::class);
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class);
    Route::post('employees/bulk-assign-shift', [\App\Http\Controllers\EmployeeController::class, 'bulkAssignShift'])->name('employees.bulkAssignShift');

}); // End Auth Middleware
