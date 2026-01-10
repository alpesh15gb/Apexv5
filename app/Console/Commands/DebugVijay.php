<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\PunchLog;

class DebugVijay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:debug-vijay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debugs punch linking for Vijay Bolagani';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("--- Vijay Bolagani Diagnostic ---");

        $emp = Employee::where('name', 'LIKE', '%Vijay Bolagani%')->first();

        if (!$emp) {
            $this->error("Employee 'Vijay Bolagani' NOT FOUND in database.");
            return 1;
        }

        $this->info("Found Employee:");
        $this->info("  Name: " . $emp->name);
        $this->info("  ID: " . $emp->id);
        $this->info("  Device Emp Code: '" . $emp->device_emp_code . "'");

        $expectedCodes = [
            'HO015',
            'HO/015',
            'HO/15'
        ];

        $this->info("\n--- Checking Punches ---");

        foreach ($expectedCodes as $code) {
            $count = PunchLog::where('device_emp_code', $code)->count();
            $unlinked = PunchLog::where('device_emp_code', $code)->whereNull('employee_id')->count();
            $linked = PunchLog::where('device_emp_code', $code)->whereNotNull('employee_id')->count();

            $this->info("Code '{$code}': Found {$count} punches ({$linked} linked, {$unlinked} unlinked)");

            if ($linked > 0) {
                $example = PunchLog::where('device_emp_code', $code)->whereNotNull('employee_id')->first();
                $linkedEmp = Employee::find($example->employee_id);
                $linkedName = $linkedEmp ? $linkedEmp->name . " (ID: {$linkedEmp->id})" : "UNKNOWN_ID: {$example->employee_id}";
                $this->info("    -> Linked to: " . $linkedName);
            }
        }

        return 0;
    }
}
