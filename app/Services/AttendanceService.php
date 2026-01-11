<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PunchLog;
use App\Models\DailyAttendance;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Calculate attendance for a specific date or range
     */
    public function calculateDailyAttendance($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        Log::info("Starting attendance calculation for: " . $date->toDateString());

        $employees = Employee::where('is_active', true)->get();

        foreach ($employees as $employee) {
            $this->processEmployeeAttendance($employee, $date);
        }
    }

    protected function processEmployeeAttendance(Employee $employee, Carbon $date)
    {
        // 1. Get Shift
        // For now, assume default shift assigned to employee. 
        // Later can implement Shift Roster for rotating shifts.
        $shift = $employee->shift;

        if (!$shift) {
            Log::warning("No shift assigned for employee: {$employee->id}");
            return;
        }

        // 2. Fetch Punches for the day
        // Adjust for night shifts (cross-date) if needed. 
        // Simple logic: 00:00 to 23:59 of the date.
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $punches = PunchLog::where('employee_id', $employee->id)
            ->whereBetween('punch_time', [$startOfDay, $endOfDay])
            ->orderBy('punch_time', 'asc')
            ->get();

        // Filter duplicates (2-minute debounce), but allow if type changes (e.g. In -> Out)
        $filteredPunches = new \Illuminate\Database\Eloquent\Collection();
        $lastPunch = null;

        foreach ($punches as $punch) {
            $isTimeOk = !$lastPunch || $punch->punch_time->diffInSeconds($lastPunch->punch_time) >= 120;
            $isTypeChanged = $lastPunch && $punch->type !== $lastPunch->type;

            if ($isTimeOk || $isTypeChanged) {
                $filteredPunches->push($punch);
                $lastPunch = $punch;
            }
        }

        // 3. Determine In/Out
        $inTime = $filteredPunches->first()?->punch_time;
        $outTime = $filteredPunches->count() > 1 ? $filteredPunches->last()?->punch_time : null;

        // 4. Calculate Hours
        $totalHours = 0;
        $status = 'Absent';
        $lateMinutes = 0;
        $earlyMinutes = 0;

        if ($inTime) {
            // Basic Status
            $status = 'Present';

            // Shift timings (Needs date context)
            $shiftStart = $date->copy()->setTimeFrom(Carbon::parse($shift->start_time));
            $shiftEnd = $date->copy()->setTimeFrom(Carbon::parse($shift->end_time));

            // Late Arrival
            if ($inTime->gt($shiftStart)) {
                $lateMinutes = $inTime->diffInMinutes($shiftStart);
            }

            // Total Hours
            if ($outTime) {
                $totalHours = $outTime->diffInHours($inTime);

                // Early Leaving
                if ($outTime->lt($shiftEnd)) {
                    $earlyMinutes = $shiftEnd->diffInMinutes($outTime);
                }
            }
        }

        // 5. Update or Create Daily Record
        DailyAttendance::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $date->toDateString(),
            ],
            [
                'shift_id' => $shift->id,
                'in_time' => $inTime,
                'out_time' => $outTime,
                'total_hours' => $totalHours,
                'late_minutes' => $lateMinutes,
                'early_leaving_minutes' => $earlyMinutes,
                'status' => $status,
                'is_finalized' => false // Can be finalized by admin or end of month
            ]
        );
    }
}
