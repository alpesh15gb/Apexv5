<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PunchLog;
use App\Models\Employee;
use App\Services\AttendanceService;
use Carbon\Carbon;

class RelinkPunches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:relink-punches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Relink punch logs with missing employee_ids using smart matching';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceService $attendanceService)
    {
        $unlinked = PunchLog::whereNull('employee_id')->get();

        if ($unlinked->isEmpty()) {
            $this->info('No unlinked punches found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$unlinked->count()} unlinked punches. Attempting to relink...");

        $count = 0;
        $affectedDates = [];

        foreach ($unlinked as $punch) {
            $deviceLogId = $punch->device_emp_code;

            // 1. Try strict match
            $employee = Employee::where('device_emp_code', $deviceLogId)->first();

            // 2. Smart Match (if strict fails)
            if (!$employee) {
                $normalizedId = ltrim($deviceLogId, '0');

                // Special handling for HO codes without slash (e.g. HO012 -> HO/012)
                if (stripos($deviceLogId, 'HO') === 0 && strpos($deviceLogId, '/') === false) {
                    $number = preg_replace('/[^0-9]/', '', $deviceLogId);
                    $simpleNumber = ltrim($number, '0');
                    $employee = Employee::where('device_emp_code', 'HO/' . $number)
                        ->orWhere('device_emp_code', 'HO/' . str_pad($number, 3, '0', STR_PAD_LEFT))
                        ->orWhere('device_emp_code', 'HO/' . $simpleNumber) // Try HO/15 matches HO015
                        ->first();
                }

                if (!$employee) {
                    $employee = Employee::where('device_emp_code', $normalizedId)
                        ->orWhere('device_emp_code', 'HO/' . str_pad($normalizedId, 3, '0', STR_PAD_LEFT))
                        ->orWhere('device_emp_code', 'MIPA' . $normalizedId) // MIPA prefix match
                        ->orWhere('device_emp_code', intval($deviceLogId))
                        ->first();
                }
            }

            if ($employee) {
                $punch->employee_id = $employee->id;
                $punch->save();
                $count++;

                $date = Carbon::parse($punch->punch_time)->toDateString();
                $affectedDates[$date] = true;

                $this->output->write('.');
            }
        }

        $this->newLine();
        $this->info("Successfully relinked {$count} punches.");

        if (!empty($affectedDates)) {
            $this->info('Recalculating attendance for affected dates...');
            foreach (array_keys($affectedDates) as $date) {
                $this->info("  - $date");
                $attendanceService->calculateDailyAttendance($date);
            }
        }

        return Command::SUCCESS;
    }
}
