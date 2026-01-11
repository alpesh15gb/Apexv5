<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Department;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmployeeImportService
{
    /**
     * Process a batch of employees from external source
     * 
     * @param array $employees
     * @return array ['imported' => int, 'updated' => int, 'failed' => int]
     */
    public function processBatch(array $employees)
    {
        Log::info('Processing batch of ' . count($employees) . ' employees.');

        $imported = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($employees as $data) {
            try {
                // Convert to object
                $empData = (object) $data;

                $deviceEmpCode = $empData->device_emp_code ?? null;
                $name = $empData->name ?? null;
                // Fix: Convert empty string to null to avoid Unique Constraint violation
                $cardNo = !empty($empData->card_no) ? $empData->card_no : null;
                $deptName = $empData->department ?? 'Imported';

                if (!$deviceEmpCode || !$name) {
                    $errors[] = "Missing Code/Name for: " . json_encode($data);
                    $failed++;
                    continue;
                }

                // 1. Resolve Department
                // Ensure a default location exists
                $location = \App\Models\Location::firstOrCreate(
                    ['code' => 'HO'],
                    ['name' => 'Head Office', 'address' => 'Main Address']
                );

                $department = Department::where('name', trim($deptName))->first();

                if (!$department) {
                    $department = Department::create([
                        'name' => trim($deptName),
                        'code' => strtoupper(substr(trim($deptName), 0, 3)) . rand(100, 999), // Generate simple code
                        'location_id' => $location->id,
                        'description' => 'Auto-imported via Direct Sync'
                    ]);
                }

                // 2. Find Existing Employee
                $employee = Employee::where('device_emp_code', $deviceEmpCode)->first();

                // Fallback: Check HO/ Prefix
                if (!$employee && strpos($deviceEmpCode, 'HO/') === false) {
                    $employee = Employee::where('device_emp_code', 'HO/' . $deviceEmpCode)->first();
                }

                if ($employee) {
                    // UPDATE
                    $employee->update([
                        'name' => $name,
                        'card_number' => $cardNo, // Update card number if changed
                        'department_id' => $department->id,
                        'is_active' => true, // Reactivate if was disabled
                    ]);
                    $updated++;
                } else {
                    // CREATE
                    Employee::create([
                        'device_emp_code' => $deviceEmpCode,
                        'name' => $name,
                        'card_number' => $cardNo,
                        'department_id' => $department->id,
                        'joining_date' => now(),
                        'is_active' => true,
                    ]);
                    $imported++;
                    Log::info("Created new employee: $name ($deviceEmpCode)");
                }

            } catch (\Exception $e) {
                $msg = $e->getMessage();
                Log::error("Failed to import employee {$name}: " . $msg);
                $errors[] = "Error for {$name} ({$deviceEmpCode}): $msg";
                $failed++;
            }
        }

        // Return detailed stats including errors
        return ['imported' => $imported, 'updated' => $updated, 'failed' => $failed, 'errors' => $errors];
    }
}
