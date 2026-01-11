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

        $cardNo = $rawPunch->card_no ?? $rawPunch->CardNo ?? null;
        $empCode = $rawPunch->emp_code ?? $rawPunch->Badgenumber ?? null;

        $employee = null;

        // 1. Find employee by Card Number (Highest Priority)
        if ($cardNo) {
            $employee = Employee::where('card_number', $cardNo)->first();
        }

        // 2. Find by Employee Code (Badgenumber from UserInfo)
        if (!$employee && $empCode) {
            $employee = Employee::where('device_emp_code', $empCode)->first();
            // Handle HO/ prefix scenarios if needed
            if (!$employee) {
                $employee = Employee::where('device_emp_code', 'HO/' . $empCode)->first();
            }
        }

        // 3. Fallback to Legacy ID Matching
        if (!$employee) {
            $employee = Employee::where('device_emp_code', $deviceLogId)->first();

            // Strict Mode: Removed fuzzy matching (stripping zeros, intval, etc.)
            // Only allow exact match on Legacy ID or standard HO/ prefix if explicitly needed.
            if (!$employee && stripos($deviceLogId, 'HO') === 0 && strpos($deviceLogId, '/') === false) {
                // Allow HO012 -> HO/012 conversion as it is a standard formatting issue
                $number = preg_replace('/[^0-9]/', '', $deviceLogId);
                $employee = Employee::where('device_emp_code', 'HO/' . $number)->first();
            }
        }

        // 4. Auto-populate Card Number if found (Self-Healing)
        if ($employee && $cardNo && $employee->card_number !== $cardNo) {
            $employee->update(['card_number' => $cardNo]);
            Log::info("Updated Card Number for Employee {$employee->id} ({$employee->name}): $cardNo");
        }

        // 2. Prevent Duplicates
        // 2. Prevent Duplicates OR Fix Mismatches
        $existingLog = PunchLog::where('device_emp_code', $deviceLogId)
            ->where('punch_time', $punchTime)
            ->first();

        if ($existingLog) {
            // Retroactive Fix: If employee mapping has changed (e.g. strict vs fuzzy), update it.
            $newEmpId = $employee ? $employee->id : null;
            if ($existingLog->employee_id !== $newEmpId) {
                $existingLog->update(['employee_id' => $newEmpId]);
                Log::info("Corrected mapping for punch {$existingLog->id}: Old Emp {$existingLog->employee_id} -> New Emp {$newEmpId}");
            }
        } else {
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
