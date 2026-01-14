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
    public function importPunches($forceStartTime = null)
    {
        Log::info('Starting punch import from MSSQL...');

        // Get last imported punch time
        $lastPunch = PunchLog::orderBy('punch_time', 'desc')->first();
        if ($forceStartTime) {
            $startTime = Carbon::parse($forceStartTime);
            Log::info("Force import started from: " . $startTime->toDateTimeString());
        } else {
            $startTime = $lastPunch ? $lastPunch->punch_time : Carbon::now()->subDays(30);
        }

        $affectedDates = [];

        try {
            // SOURCE 1: Etimetracklite1 (Dynamic Tables)
            $currentMonthTable = 'DeviceLogs_' . Carbon::now()->month . '_' . Carbon::now()->year;
            $prevMonthTable = 'DeviceLogs_' . Carbon::now()->subMonth()->month . '_' . Carbon::now()->subMonth()->year;

            $tables = [$currentMonthTable, $prevMonthTable];

            foreach ($tables as $tableName) {
                // check availability via raw query to avoid errors if table missing
                $tableExists = DB::connection('sqlsrv')->select("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?", [$tableName]);

                if (!empty($tableExists)) {
                    Log::info("Fetching from Etime table: $tableName");
                    DB::connection('sqlsrv')->table($tableName)
                        ->where('LogDate', '>', $startTime)
                        ->chunkById(500, function ($punches) use (&$affectedDates) {
                            foreach ($punches as $punch) {
                                $this->processPunch($punch);
                                $date = Carbon::parse($punch->LogDate)->toDateString();
                                $affectedDates[$date] = true;
                            }
                        }, 'DeviceLogId', 'DeviceLogId');
                }
            }

            // SOURCE 2: HikCentral (HikvisionLogs) - Keep as secondary
            // The user said Gurgaon is in Etime, but HO might be here.
            try {
                DB::connection('sqlsrv')
                    ->table(DB::raw('hikcentral.dbo.HikvisionLogs'))
                    ->where('access_datetime', '>', $startTime)
                    ->orderBy('access_datetime', 'asc')
                    ->chunk(500, function ($punches) use (&$affectedDates) {
                        foreach ($punches as $punch) {
                            $this->processPunch($punch);
                            $date = Carbon::parse($punch->access_datetime)->toDateString();
                            $affectedDates[$date] = true;
                        }
                    });
            } catch (\Exception $e) {
                Log::warning("HikvisionLogs fetch failed: " . $e->getMessage());
            }

            // Recalculate attendance
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
        // Handle both API (snake_case) and DB Raw (PascalCase) and Hikvision formats
        // Hikvision: person_id, access_datetime, direction
        // Etimetrack: UserId, LogDate, Direction

        $deviceLogId = $rawPunch->device_emp_code ?? $rawPunch->person_id ?? $rawPunch->UserId;
        $punchTime = $rawPunch->punch_time ?? $rawPunch->access_datetime ?? $rawPunch->LogDate;
        $deviceId = $rawPunch->device_id ?? $rawPunch->DeviceId ?? $rawPunch->device_name ?? 'Unknown';
        $direction = $rawPunch->type ?? $rawPunch->direction ?? 'NA';

        $cardNo = $rawPunch->card_no ?? $rawPunch->CardNo ?? null;
        $empCode = $rawPunch->emp_code ?? $rawPunch->Badgenumber ?? null;

        // If person_id is present (HikCentral), use it as empCode too if not explicitly provided
        if (!$empCode && isset($rawPunch->person_id)) {
            $empCode = $rawPunch->person_id;
        }

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

                // 4. Try GG/ + 3-digit padded (Gurgaon) or Just GG/ + Int
                if (!$employee) {
                    // Try GG/101
                    $employee = Employee::where('device_emp_code', 'GG/' . $intVal)->first();
                }
                if (!$employee) {
                    // Try GG/005
                    $ggPadded = 'GG/' . str_pad($intVal, 3, '0', STR_PAD_LEFT);
                    $employee = Employee::where('device_emp_code', $ggPadded)->first();
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
            // Use generic fields mapped in processPunch
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
