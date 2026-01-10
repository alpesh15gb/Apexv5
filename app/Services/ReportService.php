<?php

namespace App\Services;

use App\Models\DailyAttendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get Daily Attendance Report
     */
    public function getDailyReport($date, $filters = [])
    {
        $date = Carbon::parse($date)->toDateString();

        $query = DailyAttendance::with(['employee.department', 'employee.shift', 'shift'])
            ->where('date', $date);

        if (!empty($filters['department_id'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * Get Monthly Attendance Register
     */
    public function getMonthlyRegister($month, $year, $filters = [])
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get all employees (active or active during that month)
        $employees = Employee::with(['department'])
            ->where('is_active', true) // Simplification for now
            ->when(!empty($filters['department_id']), function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            })
            ->get();

        // Get attendance records for the range
        $attendances = DailyAttendance::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // Transform into a matrix structure: Employee -> [Day 1 => Status, Day 2 => Status, ...]
        $report = $employees->map(function ($employee) use ($attendances, $startDate, $endDate) {
            $empAttendance = $attendances->get($employee->id, collect());

            $days = [];
            $presentCount = 0;
            $absentCount = 0;
            $lateCount = 0;

            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                // Fix: Compare formatted date strings explicitly. 
                // $empAttendance items have 'date' as Carbon object due to casting.
                $record = $empAttendance->first(function ($item) use ($date) {
                    return $item->date->format('Y-m-d') === $date->format('Y-m-d');
                });

                $status = $record ? $record->status : 'Absent'; // Default to Absent if no record

                // Abbreviations for the register view
                $shortStatus = match ($status) {
                    'Present' => 'P',
                    'Absent' => 'A',
                    'Half Day' => 'HD',
                    'Leave' => 'L',
                    'Holiday' => 'H',
                    default => 'A'
                };

                $label = $shortStatus;
                if ($status === 'Present' || $status === 'Half Day') {
                    if ($record && $record->in_time) {
                        $in = $record->in_time->format('H:i');
                        $out = $record->out_time ? $record->out_time->format('H:i') : 'Missing';
                        $label = "$in - $out";
                    }
                }

                $days[$date->day] = [
                    'status' => $shortStatus,
                    'label' => $label,
                    'is_late' => ($record && $record->late_minutes > 0)
                ];

                if ($status === 'Present')
                    $presentCount++;
                if ($status === 'Absent')
                    $absentCount++;
                if ($record && $record->late_minutes > 0)
                    $lateCount++;
            }

            return [
                'employee_name' => $employee->name,
                'employee_code' => $employee->device_emp_code,
                'department' => $employee->department->name ?? 'N/A',
                'days' => $days,
                'summary' => [
                    'P' => $presentCount,
                    'A' => $absentCount,
                    'Late' => $lateCount
                ]
            ];
        });

        return $report;
    }

    /**
     * Get Summary Stats for Dashboard
     */
    public function getDashboardStats($date)
    {
        $date = Carbon::parse($date)->toDateString();

        $stats = DailyAttendance::where('date', $date)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Fill missing keys
        return array_merge([
            'Present' => 0,
            'Absent' => 0,
            'Late' => 0 // Note: 'Late' might need separate query if it's a flag not just a status
        ], $stats);
    }
    /**
     * Export Monthly Register to CSV
     */
    public function exportMonthlyRegister($month, $year)
    {
        // Reuse getMonthlyRegister to get the data
        $data = $this->getMonthlyRegister($month, $year);
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        $callback = function () use ($data, $daysInMonth) {
            $file = fopen('php://output', 'w');

            // Header Row
            $header = ['S.No', 'Employee Name', 'Department', 'Employee Code'];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $header[] = $d;
            }
            $header = array_merge($header, ['Total P', 'Total A', 'Total Late']);
            fputcsv($file, $header);

            // Data Rows
            foreach ($data as $index => $row) {
                $csvRow = [
                    $index + 1,
                    $row['employee_name'],
                    $row['department'],
                    $row['employee_code']
                ];

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayData = $row['days'][$d] ?? null;

                    // Handle array structure from timing update
                    if (is_array($dayData)) {
                        $cellContent = $dayData['label'] ?? '-';
                    } else {
                        $cellContent = $dayData ?? '-';
                    }
                    $csvRow[] = $cellContent;
                }

                $csvRow[] = $row['summary']['P'];
                $csvRow[] = $row['summary']['A'];
                $csvRow[] = $row['summary']['Late'];

                fputcsv($file, $csvRow);
            }

            fclose($file);
        };

        return $callback;
    }
}
