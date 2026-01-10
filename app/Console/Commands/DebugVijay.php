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

        // Dynamically generate codes to check based on his DB code
        // e.g. HO/012 -> 12 -> HO012, HO12, HO/12, HO/012
        $number = preg_replace('/[^0-9]/', '', $emp->device_emp_code);
        $padded = str_pad($number, 3, '0', STR_PAD_LEFT);

        $expectedCodes = array_unique([
            $emp->device_emp_code,       // HO/012
            'HO' . $padded,              // HO012
            'HO' . $number,              // HO12
            'HO/' . $padded,             // HO/012
            'HO/' . $number,             // HO/12
            $number,                     // 12
            intval($number)              // 12 (int)
        ]);

        $this->info("\n--- Checking Punches for derived codes: " . implode(', ', $expectedCodes) . " ---");

        foreach ($expectedCodes as $code) {
            $count = PunchLog::where('device_emp_code', $code)->count();
            $unlinked = PunchLog::where('device_emp_code', $code)->whereNull('employee_id')->count();
            $linked = PunchLog::where('device_emp_code', $code)->whereNotNull('employee_id')->count();

            $this->info("Code '{$code}': Found {$count} punches ({$linked} linked, {$unlinked} unlinked)");

            if ($linked > 0) {
                // Check WHO it is linked to
                $examples = PunchLog::where('device_emp_code', $code)->whereNotNull('employee_id')->take(3)->get();
                foreach ($examples as $example) {
                    $linkedEmp = Employee::find($example->employee_id);
                    $linkedName = $linkedEmp ? $linkedEmp->name . " (ID: {$linkedEmp->id})" : "UNKNOWN_ID: {$example->employee_id}";
                    $matchStatus = ($linkedEmp && $linkedEmp->id === $emp->id) ? "[CORRECT]" : "[WRONG LINK]";
                    $this->info("    -> Linked to: " . $linkedName . " " . $matchStatus);
                }
            }
        }

        return 0;
    }
}
