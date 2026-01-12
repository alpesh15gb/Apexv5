<?php

use App\Models\Employee;
use App\Models\PunchLog;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "--- Debugging Missing Punches ---\n";

// 1. Search for specific employees from the screenshot
$names = ['Sakendra Chaudhary', 'Ram Bahadur', 'Vinay Kumar'];
$employees = Employee::where(function ($q) use ($names) {
    foreach ($names as $name) {
        $q->orWhere('name', 'like', "%$name%");
    }
})->get();

echo "\nFound " . $employees->count() . " Employees:\n";
foreach ($employees as $emp) {
    echo "ID: {$emp->id} | Name: {$emp->name} | Code: {$emp->device_emp_code}\n";

    // Check raw punch logs for this employee
    $punches = PunchLog::where('employee_id', $emp->id)
        ->orderBy('punch_time', 'desc')
        ->limit(5)
        ->get();

    echo "  -> Recent Linked Punches:\n";
    if ($punches->isEmpty()) {
        echo "     None found.\n";
    } else {
        foreach ($punches as $p) {
            echo "     - {$p->punch_time} ({$p->type})\n";
        }
    }
}

// 2. Check for Unlinked Punches (Employee ID is null)
$unlinked = PunchLog::whereNull('employee_id')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

echo "\n--- Recent Unlinked Punches (Potential Mapping Issues) ---\n";
if ($unlinked->isEmpty()) {
    echo "No unlinked punches found.\n";
} else {
    foreach ($unlinked as $p) {
        echo "Time: {$p->punch_time} | DeviceEmpCode: {$p->device_emp_code} | Name in Payload: " . ($p->raw_data['name'] ?? 'N/A') . "\n";
    }
}

// 3. Count total punches for today
$today = Carbon::today()->toDateString();
$count = PunchLog::whereDate('punch_time', $today)->count();
echo "\nTotal punches for today ($today): $count\n";
