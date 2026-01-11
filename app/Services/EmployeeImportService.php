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

        foreach ($employees as $data) {
            try {
                // Convert to object
                $empData = (object) $data;

                $deviceEmpCode = $empData->device_emp_code ?? null;
                $name = $empData->name ?? null;
                $cardNo = $empData->card_no ?? null;
                $deptName = $empData->department ?? 'Imported';

                if (!$deviceEmpCode || !$name) {
                    Log::warning("Skipping invalid employee record: Missing Code or Name", (array) $data);
                    $failed++;
                    continue;
                }

                // 1. Resolve Department
                $department = Department::firstOrCreate(
                    ['name' => trim($deptName)],
                    ['description' => 'Auto-imported via Direct Sync']
                );

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
                Log::error("Failed to import employee: " . $e->getMessage());
                $failed++;
            }
        }

        return ['imported' => $imported, 'updated' => $updated, 'failed' => $failed];
    }
}
