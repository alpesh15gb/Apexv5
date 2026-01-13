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
            try {
                // Convert array to object
                $punchObj = (object) $punch;

                $this->processPunch($punchObj);

                // Track affected dates
                $punchTime = $punchObj->punch_time ?? $punchObj->LogDate ?? null;
                if ($punchTime) {
                    $date = Carbon::parse($punchTime)->toDateString();
                    $affectedDates[$date] = true;
                }

                $count++;
            } catch (\Exception $e) {
                // Log error but continue processing rest of batch
                Log::error("Failed to process punch in batch: " . $e->getMessage());
            }
        }

        // Recalculate attendance for affected dates
        foreach (array_keys($affectedDates) as $date) {
            try {
                $this->attendanceService->calculateDailyAttendance($date);
            } catch (\Exception $e) {
                Log::error("Failed to recalculate attendance for $date: " . $e->getMessage());
            }
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
            if (!$employee && stripos($deviceLogId, 'HO') === 0 && strpos($deviceLogId, '/') === false) {
                // Allow HO012 -> HO/012 conversion as it is a standard formatting issue
                $number = preg_replace('/[^0-9]/', '', $deviceLogId);
                $employee = Employee::where('device_emp_code', 'HO/' . $number)->first();
            }

            // Fuzzy Failover: Try matching by integer value (handles 001 vs 1)
            if (!$employee && is_numeric($deviceLogId)) {
                $intVal = (int) $deviceLogId;
                // 1. Try exact integer string (e.g. '5')
                $employee = Employee::where('device_emp_code', (string) $intVal)->first();

                // 2. Try 4-digit zero padded (e.g. '0005')
                if (!$employee) {
                    $padded = str_pad($intVal, 4, '0', STR_PAD_LEFT);
                    $employee = Employee::where('device_emp_code', $padded)->first();
                }

                // 3. Try HO/ + 3-digit padded (e.g. 'HO/005') - Common pattern
                if (!$employee) {
                    $hoPadded = 'HO/' . str_pad($intVal, 3, '0', STR_PAD_LEFT);
                    $employee = Employee::where('device_emp_code', $hoPadded)->first();
                }
            }
        }

        // 4. AUTO-CREATE EMPLOYEE (If Name is Provided)
        if (!$employee && !empty($rawPunch->name)) {
            // Fix: Handle null, empty string, or whitespace only
            $deptName = trim($rawPunch->department ?? '');
            if (empty($deptName)) {
                $deptName = 'Imported';
            }

            // Find or Create Department
            $department = \App\Models\Department::firstOrCreate(
                ['name' => $deptName],
                ['description' => 'Auto-imported from Biometric Sync']
            );

            // Create Employee
            try {
                $employee = Employee::create([
                    'name' => $rawPunch->name,
                    'device_emp_code' => $empCode ?? $deviceLogId, // Use Badge if avail, else LogId
                    'card_number' => $cardNo, // Can be null
                    'department_id' => $department->id,
                    'is_active' => true,
                    'joining_date' => now(), // Default to today
                ]);
                Log::info("Auto-Created Employee: {$employee->name} ({$employee->device_emp_code})");
            } catch (\Exception $e) {
                Log::error("Failed to auto-create employee {$rawPunch->name}: " . $e->getMessage());
            }
        }

        // 4. Auto-populate Card Number if found (Self-Healing)
        if ($employee && $cardNo && $employee->card_number !== $cardNo) {
            $employee->update(['card_number' => $cardNo]);
            Log::info("Updated Card Number for Employee {$employee->id} ({$employee->name}): $cardNo");
        }

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
    /**
     * Relink unmapped punches
     */
    public function relinkUnmappedLogs()
    {
        $punches = PunchLog::whereNull('employee_id')->orderBy('id', 'desc')->take(2000)->get();
        $count = 0;

        foreach ($punches as $punch) {
            // Re-run process logic to find employee
            // We construct a mock raw object
            $raw = (object) [
                'device_emp_code' => $punch->device_emp_code,
                'punch_time' => $punch->punch_time,
                'device_id' => $punch->device_id,
                'type' => $punch->type,
            ];

            Log::info("Attempting to relink log #" . $punch->id . " (Code: " . $punch->device_emp_code . ")");
            $this->processPunch($raw);

            // Check if linked
            if ($punch->refresh()->employee_id) {
                $count++;
            }
        }

        return $count;
    }
}
