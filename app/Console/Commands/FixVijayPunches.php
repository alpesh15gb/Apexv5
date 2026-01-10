<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\PunchLog;

class FixVijayPunches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-vijay-punches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlinks HO012 punches from Pratap and links them to Vijay';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("--- Fixing Vijay Bolagani Punches ---");

        // 1. Identify Actors
        $vijay = Employee::where('name', 'LIKE', '%Vijay Bolagani%')->first();
        if (!$vijay) {
            $this->error("Vijay not found!");
            return 1;
        }

        $pratap = Employee::find(4);
        if ($pratap) {
            $this->info("Pratap (ID: 4) found. Device Code: '{$pratap->device_emp_code}'");
            if ($pratap->device_emp_code === 'HO012' || $pratap->device_emp_code === 'HO/012') {
                $this->error("WARNING: Pratap actually HAS the code HO/012. Aborting to prevent data corruption.");
                return 1;
            }
        }

        // 2. Find Mislinked Punches
        $mislinked = PunchLog::where('device_emp_code', 'HO012')
            ->where('employee_id', 4)
            ->get();

        $count = $mislinked->count();
        $this->info("Found {$count} punches for 'HO012' currently linked to Pratap (ID 4).");

        if ($count === 0) {
            $this->info("No punches to fix.");
            return 0;
        }

        if (!$this->confirm("Do you want to unlink these {$count} punches from Pratap and link them to Vijay (ID {$vijay->id})?", true)) {
            $this->info("Aborted.");
            return 0;
        }

        // 3. Fix
        $updated = PunchLog::where('device_emp_code', 'HO012')
            ->where('employee_id', 4)
            ->update(['employee_id' => $vijay->id]);

        $this->info("Successfully moved {$updated} punches to Vijay Bolagani.");

        // 4. Trigger Recalculation
        $this->info("Triggering recalculation will be done by user separately.");

        return 0;
    }
}
