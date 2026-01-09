<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\PunchLog;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PunchImportService
{
    /**
     * Import new punches from MSSQL source
     * 
     * @return int Number of records imported
     */
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Import new punches from MSSQL source
     * 
     * @return int Number of records imported
     */
    public function importPunches()
    {
        Log::info('Starting punch import from MSSQL...');

        // Get last imported punch time to allow incremental sync
        $lastPunch = PunchLog::orderBy('punch_time', 'desc')->first();
        $startTime = $lastPunch ? $lastPunch->punch_time : Carbon::now()->subDays(30); // Default to last 30 days if empty

        $affectedDates = [];

        try {
            // Fetch raw logs from MSSQL
            // Assuming table name 'DeviceLogs_Processed' or similar based on Etimetracklite schema
            // We select columns relevant to our schema
            $rawPunches = DB::connection('sqlsrv')
                ->table('DeviceLogs_Processed') // Adjust table name if needed
                ->where('LogDate', '>', $startTime)
                ->orderBy('LogDate', 'asc')
                ->chunk(500, function ($punches) use (&$affectedDates) {
                    foreach ($punches as $punch) {
                        $this->processPunch($punch);
                        $date = Carbon::parse($punch->LogDate)->toDateString();
                        $affectedDates[$date] = true;
                    }
                });

            // Recalculate attendance for affected dates
            foreach (array_keys($affectedDates) as $date) {
                $this->attendanceService->calculateDailyAttendance($date);
            }

            Log::info('Punch import completed successfully.');

        } catch (\Exception $e) {
            Log::error('Punch import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a batch of raw punches from external API
     */
    public function processBatch(array $punches)
    {
        Log::info('Processing batch of ' . count($punches) . ' punches from API.');
        $count = 0;
        $affectedDates = [];

        foreach ($punches as $punch) {
            // Convert array to object to match existing processPunch expectation
            // or modify processPunch to accept array. Let's cast to object for minimal change.
            $punchObj = (object) $punch;

            // Map keys if incoming JSON keys differ from MSSQL columns. 
            // Assuming Local Agent sends raw MSSQL columns: LogDate, DeviceId, UserId

            $this->processPunch($punchObj);

            // Track affected dates
            $punchTime = $punchObj->punch_time ?? $punchObj->LogDate ?? null;
            if ($punchTime) {
                $date = Carbon::parse($punchTime)->toDateString();
                $affectedDates[$date] = true;
            }

            $count++;
        }

        // Recalculate attendance for affected dates
        foreach (array_keys($affectedDates) as $date) {
            $this->attendanceService->calculateDailyAttendance($date);
        }

        return $count;
    }

    protected function processPunch($rawPunch)
    {
        // Handle both API (snake_case) and DB Raw (PascalCase) formats
        $deviceLogId = $rawPunch->device_emp_code ?? $rawPunch->UserId;
        $punchTime = $rawPunch->punch_time ?? $rawPunch->LogDate;
        $deviceId = $rawPunch->device_id ?? $rawPunch->DeviceId;
        $direction = $rawPunch->type ?? 'NA'; // Default if missing

        // 1. Find employee
        // Try strict match first
        $employee = Employee::where('device_emp_code', $deviceLogId)->first();

        // If not found, try stripping leading zeros or "HO/" prefix if applicable
        if (!$employee) {
            $normalizedId = ltrim($deviceLogId, '0');
            $employee = Employee::where('device_emp_code', $normalizedId)
                ->orWhere('device_emp_code', 'HO/' . str_pad($normalizedId, 3, '0', STR_PAD_LEFT))
                ->orWhere('device_emp_code', intval($deviceLogId))
                ->first();
        }

        // 2. Prevent Duplicates
        $exists = PunchLog::where('device_emp_code', $deviceLogId)
            ->where('punch_time', $punchTime)
            ->exists();

        if (!$exists) {
            PunchLog::create([
                'punch_time' => $punchTime,
                'device_id' => $deviceId,
                'device_emp_code' => $deviceLogId,
                'employee_id' => $employee ? $employee->id : null,
                'type' => $direction,
                'is_processed' => false,
            ]);
        }
    }
}
