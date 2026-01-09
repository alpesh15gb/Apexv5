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
    public function importPunches()
    {
        Log::info('Starting punch import from MSSQL...');

        // Get last imported punch time to allow incremental sync
        $lastPunch = PunchLog::orderBy('punch_time', 'desc')->first();
        $startTime = $lastPunch ? $lastPunch->punch_time : Carbon::now()->subDays(30); // Default to last 30 days if empty

        try {
            // Fetch raw logs from MSSQL
            // Assuming table name 'DeviceLogs_Processed' or similar based on Etimetracklite schema
            // We select columns relevant to our schema
            $rawPunches = DB::connection('sqlsrv')
                ->table('DeviceLogs_Processed') // Adjust table name if needed
                ->where('LogDate', '>', $startTime)
                ->orderBy('LogDate', 'asc')
                ->chunk(500, function ($punches) {
                    foreach ($punches as $punch) {
                        $this->processPunch($punch);
                    }
                });

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

        foreach ($punches as $punch) {
            // Convert array to object to match existing processPunch expectation
            // or modify processPunch to accept array. Let's cast to object for minimal change.
            $punchObj = (object) $punch;

            // Map keys if incoming JSON keys differ from MSSQL columns. 
            // Assuming Local Agent sends raw MSSQL columns: LogDate, DeviceId, UserId

            $this->processPunch($punchObj);
            $count++;
        }

        return $count;
    }

    protected function processPunch($rawPunch)
    {
        // 1. Map Device Code to Employee
        // DeviceUserId is usually the ID on the device
        $deviceUserId = $rawPunch->UserId;

        // Find employee by device_emp_code
        $employee = Employee::where('device_emp_code', $deviceUserId)->first();

        // 2. Prevent Duplicates
        // Use composite key check or existence check
        $exists = PunchLog::where('device_emp_code', $deviceUserId)
            ->where('punch_time', $rawPunch->LogDate)
            ->exists();

        if (!$exists) {
            PunchLog::create([
                'punch_time' => $rawPunch->LogDate,
                'device_id' => $rawPunch->DeviceId,
                'device_emp_code' => $deviceUserId,
                'employee_id' => $employee ? $employee->id : null,
                'is_processed' => false,
            ]);
        }
    }
}
