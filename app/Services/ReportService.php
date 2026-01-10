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
     * Get Detailed Attendance Report (Weekly/Custom Range)
     */
    public function getDetailedReport($startDate, $endDate, $filters = [])
    {
        $startDate = Carbon::parse($startDate)->toDateString();
        $endDate = Carbon::parse($endDate)->toDateString();

        $query = DailyAttendance::with(['employee.department', 'employee.shift', 'shift'])
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($filters['department_id'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        return $query->orderBy('date', 'asc')->get()->groupBy('employee_id');
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
     * Get Monthly Matrix Report
     */
    public function getMatrixReport($month, $year, $filters = [])
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Fetch employees
        $employees = Employee::with(['department.location.branch.company', 'shift']) // eager load for performance
            ->where('is_active', true)
            ->when(!empty($filters['department_id']), fn($q) => $q->where('department_id', $filters['department_id']))
            ->when(!empty($filters['location_id']), function ($q) use ($filters) {
                $q->whereHas('department', fn($q) => $q->where('location_id', $filters['location_id']));
            })
            ->when(!empty($filters['company_id']), function ($q) use ($filters) {
                $q->whereHas('department.location.branch', fn($q) => $q->where('company_id', $filters['company_id']));
            })
            ->get();

        // Get attendance
        $attendances = DailyAttendance::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // Build Matrix
        return $employees->map(function ($employee) use ($attendances, $startDate, $endDate) {
            $empAttendance = $attendances->get($employee->id, collect());
            $days = [];

            // Summary Counters
            $totalDuration = 0; // minutes
            $totalOT = 0; // minutes
            $presentCount = 0;
            $absentCount = 0;
            $leavesCount = 0;
            $lateCount = 0;
            $earlyCount = 0; // based on early_leaving_minutes > 0

            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                $record = $empAttendance->first(function ($item) use ($date) {
                    return $item->date->format('Y-m-d') === $date->format('Y-m-d');
                });

                $metrics = [
                    'in_time' => '-',
                    'out_time' => '-',
                    'duration' => '-',
                    'late_by' => '-',
                    'early_by' => '-',
                    'ot' => '-',
                    'status' => 'A'
                ];

                if ($record) {
                    $metrics['status'] = match ($record->status) {
                        'Present' => 'P',
                        'Absent' => 'A',
                        'Half Day' => 'HD',
                        'Leave' => 'L',
                        'Holiday' => 'H',
                        default => substr($record->status, 0, 2)
                    };

                    if ($record->in_time)
                        $metrics['in_time'] = $record->in_time->format('H:i');
                    if ($record->out_time)
                        $metrics['out_time'] = $record->out_time->format('H:i');

                    // Duration (Total Hours is likely in hours or minutes? Default 0. Migration says decimal 8,2. Usually hours.)
                    // Let's assume calculated total_hours is hours. Let's convert to H:i
                    if ($record->total_hours > 0) {
                        $hours = floor($record->total_hours);
                        $mins = round(($record->total_hours - $hours) * 60);
                        $metrics['duration'] = sprintf('%02d:%02d', $hours, $mins);
                        $totalDuration += ($record->total_hours * 60);
                    }

                    if ($record->late_minutes > 0) {
                        $metrics['late_by'] = $record->late_minutes;
                        $lateCount++;
                    }

                    if ($record->early_leaving_minutes > 0) {
                        $metrics['early_by'] = $record->early_leaving_minutes;
                        $earlyCount++;
                    }

                    if ($record->overtime_minutes > 0) {
                        $metrics['ot'] = $record->overtime_minutes; // minutes
                        $totalOT += $record->overtime_minutes;
                    }

                    if ($record->status === 'Present' || $record->status === 'Half Day')
                        $presentCount++;
                    if ($record->status === 'Absent')
                        $absentCount++;
                    if ($record->status === 'Leave')
                        $leavesCount++;
                } else {
                    $absentCount++; // Default absent if no record
                }

                $days[$date->day] = $metrics;
            }

            // Convert summary minutes to HH:mm
            return [
                'employee' => $employee,
                'days' => $days,
                'summary' => [
                    'total_duration' => sprintf('%02d:%02d', floor($totalDuration / 60), $totalDuration % 60),
                    'total_ot' => sprintf('%02d:%02d', floor($totalOT / 60), $totalOT % 60),
                    'present' => $presentCount,
                    'absent' => $absentCount,
                    'leaves' => $leavesCount,
                    'late' => $lateCount,
                    'early' => $earlyCount,
                    'shift_count' => $startDate->diffInDays($endDate) + 1 // Simply days in month for now
                ]
            ];
        });
    }

    /**
     * Export Matrix Report
     */
    public function exportMatrixReport($month, $year, $filters = [])
    {
        $data = $this->getMatrixReport($month, $year, $filters);
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        $headers = [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=monthly-matrix-report.csv',
            'Expires' => '0',
            'Pragma' => 'public'
        ];

        return response()->stream(function () use ($data, $daysInMonth) {
            $file = fopen('php://output', 'w');

            // Header Row
            $headerRow = ['Emp Code', 'Emp Name', 'Dept', 'Metric'];
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $headerRow[] = $i;
            }
            fputcsv($file, $headerRow);

            foreach ($data as $row) {
                // We define which metrics to export as rows
                $metrics = [
                    'status' => 'Status',
                    'in_time' => 'In Time',
                    'out_time' => 'Out Time',
                    'duration' => 'Duration',
                    'late_by' => 'Late By (Min)',
                    'early_by' => 'Early By (Min)',
                    'ot' => 'OT (Min)'
                ];

                foreach ($metrics as $key => $label) {
                    $csvRow = [
                        $row['employee']->device_emp_code,
                        $row['employee']->name,
                        $row['employee']->department->name ?? '-',
                        $label
                    ];

                    for ($i = 1; $i <= $daysInMonth; $i++) {
                        $val = $row['days'][$i][$key] ?? '-';
                        $csvRow[] = $val;
                    }
                    fputcsv($file, $csvRow);
                }
                // Separator row for readability? Or just keep it tight.
            }
            fclose($file);
        }, 200, $headers);
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
     * Export Detailed Report
     */
    public function exportDetailedReport($startDate, $endDate, $filters = [])
    {
        $data = $this->getDetailedReport($startDate, $endDate, $filters);

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Employee Code', 'Name', 'Department', 'Date', 'Day', 'Shift', 'In Time', 'Out Time', 'Late (Mins)', 'Status']);

            foreach ($data as $empId => $records) {
                foreach ($records as $record) {
                    fputcsv($file, [
                        $record->employee->device_emp_code ?? '-',
                        $record->employee->name ?? '-',
                        $record->employee->department->name ?? '-',
                        $record->date->format('Y-m-d'),
                        $record->date->format('l'),
                        $record->shift->name ?? '-',
                        $record->in_time ? $record->in_time->format('H:i') : '-',
                        $record->out_time ? $record->out_time->format('H:i') : '-',
                        $record->late_minutes,
                        $record->status
                    ]);
                }
            }
            fclose($file);
        };
        return $callback;
    }

    /**
     * Export Daily Report
     */
    public function exportDailyReport($date, $filters = [])
    {
        $data = $this->getDailyReport($date, $filters);

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee Code', 'Name', 'Department', 'Shift', 'In Time', 'Out Time', 'Late (Mins)', 'Status']);

            foreach ($data as $record) {
                fputcsv($file, [
                    $record->employee->device_emp_code ?? '-',
                    $record->employee->name ?? '-',
                    $record->employee->department->name ?? '-',
                    $record->shift->name ?? '-',
                    $record->in_time ? $record->in_time->format('H:i') : '-',
                    $record->out_time ? $record->out_time->format('H:i') : '-',
                    $record->late_minutes,
                    $record->status
                ]);
            }
            fclose($file);
        };
        return $callback;
    }

    /**
     * Export Monthly Register to CSV
     */
    public function exportMonthlyRegister($month, $year, $filters = [])
    {
        // Reuse getMonthlyRegister to get the data
        $data = $this->getMonthlyRegister($month, $year, $filters);
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
