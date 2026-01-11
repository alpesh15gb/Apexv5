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
    Route::get('/reports/matrix-view', [WebController::class, 'matrixReportView'])->name('reports.matrix');
    Route::get('/reports/matrix-print', [WebController::class, 'matrixPrintView'])->name('reports.matrix.print');

    // Data/Export Routes
    Route::get('/reports/detailed', [\App\Http\Controllers\ReportController::class, 'detailedReport']); // For JSON data
    Route::get('/reports/matrix-data', [\App\Http\Controllers\ReportController::class, 'matrixReport']); // For JSON data
    Route::get('/reports/export/monthly', [\App\Http\Controllers\ReportController::class, 'monthlyExport'])->name('reports.monthly.export');
    Route::get('/reports/export/matrix', [\App\Http\Controllers\ReportController::class, 'matrixExport'])->name('reports.matrix.export');
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
    Route::post('employees/bulk-assign-department', [\App\Http\Controllers\EmployeeController::class, 'bulkAssignDepartment'])->name('employees.bulkAssignDepartment');

    Route::resource('leaves', \App\Http\Controllers\LeaveController::class);
    Route::patch('leaves/{leave}/status', [\App\Http\Controllers\LeaveController::class, 'updateStatus'])->name('leaves.updateStatus');

    Route::resource('holidays', \App\Http\Controllers\HolidayController::class)->only(['index', 'store', 'destroy']);

}); // End Auth Middleware

// --- Mobile App Routes (ESS) ---
Route::get('mobile/login', [\App\Http\Controllers\MobilePunchController::class, 'showLogin'])->name('mobile.login');
Route::post('mobile/login', [\App\Http\Controllers\MobilePunchController::class, 'login'])->name('mobile.login.post');
Route::get('mobile/punch', [\App\Http\Controllers\MobilePunchController::class, 'showPunch'])->name('mobile.punch');
Route::post('mobile/punch', [\App\Http\Controllers\MobilePunchController::class, 'storePunch'])->name('mobile.punch.store');

Route::get('/cleanup-mobile', function () {
    $count = \App\Models\DailyAttendance::where('date', \Carbon\Carbon::now()->format('Y-m-d'))
        ->whereNotNull('in_image')
        ->delete();
    return "Deleted $count mobile punches for today.";
});
Route::get('mobile/logout', [\App\Http\Controllers\MobilePunchController::class, 'logout'])->name('mobile.logout');

Route::get('/debug-punches', function () {
    $mobile = \App\Models\DailyAttendance::whereNotNull('in_image')->latest('id')->first();
    $biometric = \App\Models\DailyAttendance::whereNull('in_image')->whereNotNull('in_time')->latest('id')->first();

    return response()->json([
        'server_now_ist' => now()->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
        'server_now_utc' => now()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        'mobile_punch' => $mobile ? [
            'raw' => $mobile->getAttributes(),
            'cast_in_time' => $mobile->in_time ? $mobile->in_time->format('Y-m-d H:i:s') : null,
        ] : null,
        'biometric_punch' => $biometric ? [
            'raw' => $biometric->getAttributes(),
            'cast_in_time' => $biometric->in_time ? $biometric->in_time->format('Y-m-d H:i:s') : null
        ] : null
    ]);
});

Route::get('/debug/recalculate', function () {
    $service = app(\App\Services\AttendanceService::class);
    $start = now()->subDays(30);
    $end = now();

    for ($date = $start; $date <= $end; $date->addDay()) {
        try {
            $service->calculateDailyAttendance($date->toDateString());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Recalc failed for {$date->toDateString()}: " . $e->getMessage());
        }
    }

    return "Recalculation triggered for last 30 days.";
});

Route::get('/debug/status', function () {
    $employees = \App\Models\Employee::count();
    $employees_no_shift = \App\Models\Employee::whereNull('shift_id')->count();
    $punches = \App\Models\PunchLog::where('punch_time', '>=', now()->subDays(30))->count();
    $punches_null_emp = \App\Models\PunchLog::where('punch_time', '>=', now()->subDays(30))
        ->whereNull('employee_id')->count();
    $attendance = \App\Models\DailyAttendance::where('date', '>=', now()->subDays(30))->count();

    $attendance_by_date = \App\Models\DailyAttendance::selectRaw('date, count(*) as count')
        ->where('date', '>=', now()->subDays(5))
        ->groupBy('date')
        ->orderBy('date', 'desc')
        ->pluck('count', 'date');

    // Inspect unlinked punches
    $unlinked_samples = \App\Models\PunchLog::whereNull('employee_id')
        ->where('punch_time', '>=', now()->subDays(2))
        ->take(5)
        ->get(['device_emp_code', 'punch_time']);

    // Inspect some random employees to see their code format
    $employee_samples = \App\Models\Employee::inRandomOrder()->take(5)->get(['id', 'name', 'device_emp_code', 'card_number']);

    return [
        'employees_total' => $employees,
        'employees_without_shift' => $employees_no_shift,
        'recent_punches_total' => $punches,
        'punches_unlinked_to_employee' => $punches_null_emp,
        'unlinked_samples' => $unlinked_samples,
        'employee_samples' => $employee_samples,
        'attendance_records' => $attendance,
        'attendance_by_date_last_5_days' => $attendance_by_date,
        'server_time' => now()->toDateTimeString(),
        'server_timezone' => config('app.timezone'),
    ];
});

