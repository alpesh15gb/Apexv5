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
        $dateStr = Carbon::parse($date)->toDateString();
        $dateObj = Carbon::parse($date);

        // 1. Get Employees matching filters
        $employeesQuery = Employee::with([
            'department',
            'shift',
            'leaves' => function ($q) use ($dateStr) {
                // Eager load only relevant leaves to optimize
                $q->where('status', 'approved')
                    ->whereDate('start_date', '<=', $dateStr)
                    ->whereDate('end_date', '>=', $dateStr);
            }
        ])
            ->where('is_active', true);

        if (!empty($filters['department_id'])) {
            $employeesQuery->where('department_id', $filters['department_id']);
        }
        if (!empty($filters['location_id'])) {
            $employeesQuery->whereHas('department', fn($q) => $q->where('location_id', $filters['location_id']));
        }
        if (!empty($filters['company_id'])) {
            $employeesQuery->whereHas('department.location.branch', fn($q) => $q->where('company_id', $filters['company_id']));
        }
        if (!empty($filters['search'])) {
            $employeesQuery->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('device_emp_code', 'like', '%' . $filters['search'] . '%');
            });
        }
        $employees = $employeesQuery->get();

        // 2. Get Attendance for this date for these employees
        $attendances = DailyAttendance::with('shift') // Eager load Shift for existing records
            ->where('date', $dateStr)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        // 3. Check for Holiday
        $isHoliday = \App\Models\Holiday::where('date', $dateStr)->exists();

        // 4. Merge and Map
        $reportData = $employees->map(function ($employee) use ($attendances, $isHoliday, $dateStr, $dateObj) {
            $record = $attendances->get($employee->id);

            // If no record exists, create a dummy one
            if (!$record) {
                $record = new DailyAttendance();
                $record->employee_id = $employee->id;
                $record->date = $dateObj; // Use Carbon object as model expects cast
                $record->status = 'Absent';
                $record->late_minutes = 0;
                // Manually set relation to avoid lazy load
                $record->setRelation('shift', $employee->shift);
            } else {
                // Formatting time for JSON output
                if ($record->in_time)
                    $record->in_time = \Carbon\Carbon::parse($record->in_time)->format('Y-m-d H:i:s');
                if ($record->out_time)
                    $record->out_time = \Carbon\Carbon::parse($record->out_time)->format('Y-m-d H:i:s');
            }

            // ALWAYS Attach Employee (Fixes "Unknown" and N+1)
            $record->setRelation('employee', $employee);

            // Status Overrides (Holiday / Leave) if Absent
            if ($record->status === 'Absent' || $record->status === 'Half Day') { // Check leaves even for Half Day? Usually absent logic.
                if ($record->status === 'Absent' && $isHoliday) {
                    $record->status = 'Holiday';
                } else {
                    // Check Eager Loaded Leaves
                    $leave = $employee->leaves->first(); // We already filtered in eager load
                    if ($leave) {
                        $record->status = $leave->leaveType->code ?? 'Leave';
                        // Attach leave type for frontend if needed
                        // $leave->load('leaveType'); // Assume loaded or simple code
                    }
                }
            }

            return $record;
        });

        // 5. Apply Status Filters (Post-processing since we need to check generated status)
        if (!empty($filters['status'])) {
            $statusFilter = $filters['status'];
            $reportData = $reportData->filter(function ($record) use ($statusFilter) {
                if ($statusFilter === 'Late') {
                    return $record->late_minutes > 0;
                }
                return $record->status === $statusFilter;
            });
        }

        return $reportData->values(); // Reset keys
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

        if (!empty($filters['location_id'])) {
            $query->whereHas('employee.department', function ($q) use ($filters) {
                $q->where('location_id', $filters['location_id']);
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('employee.department.location.branch', function ($q) use ($filters) {
                $q->where('company_id', $filters['company_id']);
            });
        }

        if (!empty($filters['search'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('device_emp_code', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['search'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('device_emp_code', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        $data = $query->orderBy('date', 'asc')->get()->map(function ($record) {
            if ($record->in_time) {
                $record->in_time = \Carbon\Carbon::parse($record->in_time)->format('Y-m-d H:i:s');
            }
            if ($record->out_time) {
                $record->out_time = \Carbon\Carbon::parse($record->out_time)->format('Y-m-d H:i:s');
            }
            return $record;
        });

        return $data->groupBy('employee_id');
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
            ->when(!empty($filters['location_id']), function ($q) use ($filters) {
                $q->whereHas('department', fn($q) => $q->where('location_id', $filters['location_id']));
            })
            ->when(!empty($filters['company_id']), function ($q) use ($filters) {
                $q->whereHas('department.location.branch', fn($q) => $q->where('company_id', $filters['company_id']));
            })
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('device_emp_code', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->get();

        // Get attendance records for the range
        $attendances = DailyAttendance::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // Fetch Holidays for the month
        $holidays = \App\Models\Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();

        // Fetch Approved Leaves for the month
        $leaves = \App\Models\Leave::with('leaveType')
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('start_date', '<', $startDate)
                            ->where('end_date', '>', $endDate);
                    });
            })
            ->get()
            ->groupBy('employee_id');

        // Transform into a matrix structure: Employee -> [Day 1 => Status, Day 2 => Status, ...]
        $report = $employees->map(function ($employee) use ($attendances, $startDate, $endDate, $holidays, $leaves) {
            $empAttendance = $attendances->get($employee->id, collect());
            $empLeaves = $leaves->get($employee->id, collect());

            $days = [];
            $presentCount = 0;
            $absentCount = 0;
            $lateCount = 0;

            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $record = $empAttendance->first(function ($item) use ($dateStr) {
                    return $item->date->format('Y-m-d') === $dateStr;
                });

                $status = $record ? $record->status : 'Absent'; // Default to Absent if no record

                // Override Absent with Holiday or Leave
                if ($status === 'Absent') {
                    if (in_array($dateStr, $holidays)) {
                        $status = 'Holiday';
                    } else {
                        // Check for Leave
                        $leave = $empLeaves->first(function ($l) use ($date) {
                            return $date->between($l->start_date, $l->end_date);
                        });
                        if ($leave) {
                            $status = $leave->leaveType->code ?? 'Leave';
                        }
                    }
                }

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
                        $in = \Carbon\Carbon::parse($record->in_time)->format('H:i');
                        $out = $record->out_time ? \Carbon\Carbon::parse($record->out_time)->format('H:i') : 'Missing';
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
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('device_emp_code', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->get();

        // Get attendance
        $attendances = DailyAttendance::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // Fetch Holidays for the month
        $holidays = \App\Models\Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();

        // Fetch Approved Leaves
        $leaves = \App\Models\Leave::with('leaveType')
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('start_date', '<', $startDate)
                            ->where('end_date', '>', $endDate);
                    });
            })
            ->get()
            ->groupBy('employee_id');

        // Build Matrix
        return $employees->map(function ($employee) use ($attendances, $startDate, $endDate, $holidays, $leaves) {
            $empAttendance = $attendances->get($employee->id, collect());
            $empLeaves = $leaves->get($employee->id, collect());
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
                $dateStr = $date->format('Y-m-d');
                $record = $empAttendance->first(function ($item) use ($dateStr) {
                    return $item->date->format('Y-m-d') === $dateStr;
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

                    // Override Absent if Holiday or Leave
                    if ($record->status === 'Absent') {
                        if (in_array($dateStr, $holidays)) {
                            $metrics['status'] = 'H';
                        } else {
                            $leave = $empLeaves->first(function ($l) use ($date) {
                                return $date->between($l->start_date, $l->end_date);
                            });
                            if ($leave) {
                                $metrics['status'] = 'L'; // Or use code? Matrix usually uses single char 'L'
                            }
                        }
                    }

                    if ($record->in_time)
                        $metrics['in_time'] = \Carbon\Carbon::parse($record->in_time)->format('H:i');
                    if ($record->out_time)
                        $metrics['out_time'] = \Carbon\Carbon::parse($record->out_time)->format('H:i');

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

                    // Check Holiday/Leave for missing records too
                    if (in_array($dateStr, $holidays)) {
                        $metrics['status'] = 'H';
                        $absentCount--; // Revert counter
                    } else {
                        $leave = $empLeaves->first(function ($l) use ($date) {
                            return $date->between($l->start_date, $l->end_date);
                        });
                        if ($leave) {
                            $metrics['status'] = 'L';
                            $absentCount--; // Revert counter
                            $leavesCount++;
                        }
                    }
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
                        $record->in_time ? \Carbon\Carbon::parse($record->in_time)->format('H:i') : '-',
                        $record->out_time ? \Carbon\Carbon::parse($record->out_time)->format('H:i') : '-',
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
                    $record->in_time ? \Carbon\Carbon::parse($record->in_time)->format('H:i') : '-',
                    $record->out_time ? \Carbon\Carbon::parse($record->out_time)->format('H:i') : '-',
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
    /**
     * Get Weekly Attendance Trend (Last 7 Days)
     */
    public function getWeeklyStats()
    {
        $dates = collect(\Carbon\CarbonPeriod::create(Carbon::today()->subDays(6), Carbon::today()));

        $stats = DailyAttendance::whereDate('date', '>=', Carbon::today()->subDays(6))
            ->select('date', 'status')
            ->get()
            ->groupBy(fn($item) => $item->date->format('Y-m-d'));

        return $dates->map(function ($date) use ($stats) {
            $dayStats = $stats->get($date->format('Y-m-d'), collect());
            return [
                'date' => $date->format('D, d M'), // Mon, 12 Jan
                'present' => $dayStats->whereIn('status', ['Present', 'Half Day'])->count(),
                'absent' => $dayStats->where('status', 'Absent')->count(),
                'late' => $dayStats->where('late_minutes', '>', 0)->count()
            ];
        })->values();
    }
    /**
     * Get Department-wise Presence Stats
     */
    public function getDepartmentStats()
    {
        $today = \Carbon\Carbon::today();

        // accurate total count per department
        $departments = \App\Models\Department::withCount([
            'employees' => function ($q) {
                $q->where('is_active', true);
            }
        ])->having('employees_count', '>', 0)->get();

        $attendance = DailyAttendance::whereDate('date', $today)
            ->whereIn('status', ['Present', 'Half Day'])
            ->with('employee')
            ->get()
            ->groupBy('employee.department_id');

        return $departments->map(function ($dept) use ($attendance) {
            $present = $attendance->get($dept->id, collect())->count();
            $percentage = $dept->employees_count > 0 ? round(($present / $dept->employees_count) * 100) : 0;

            return [
                'name' => $dept->name,
                'total' => $dept->employees_count,
                'present' => $present,
                'percentage' => $percentage
            ];
        })->sortByDesc('percentage')->take(5)->values();
    }

    /**
     * Get Location Stats
     */
    public function getLocationStats()
    {
        $today = Carbon::today()->toDateString();

        // Get all locations with employee count
        $locations = \App\Models\Location::withCount([
            'departments as employees_count' => function ($q) {
                $q->join('employees', 'departments.id', '=', 'employees.department_id')
                    ->where('employees.is_active', true);
            }
        ])->get();

        // Get today's attendance grouped by location
        // We need to join tables to filter by location
        $attendance = DailyAttendance::whereDate('date', $today)
            ->whereIn('status', ['Present', 'Half Day'])
            ->whereHas('employee.department.location') // optimize
            ->with('employee.department.location')
            ->get()
            ->groupBy(function ($item) {
                return $item->employee->department->location_id;
            });

        return $locations->map(function ($loc) use ($attendance) {
            $present = $attendance->get($loc->id, collect())->count();

            // Calculate Absent and Late
            // Note: This is an approximation based on 'Present' count vs Total. 
            // Real 'Absent' might need query, but for cards usually Present/Total is key.
            // Let's get strict if needed. For now, Present % is main metric.

            $percentage = $loc->employees_count > 0 ? round(($present / $loc->employees_count) * 100) : 0;

            return [
                'name' => $loc->name,
                'total' => $loc->employees_count,
                'present' => $present,
                'absent' => $loc->employees_count - $present, // Simplification
                'percentage' => $percentage
            ];
        })->values();
    }

    /**
     * Get Recent Punches (Live Feed)
     */
    public function getRecentPunches()
    {
        return DailyAttendance::whereDate('date', \Carbon\Carbon::today())
            ->where(function ($q) {
                $q->whereNotNull('in_time')->orWhereNotNull('out_time');
            })
            ->with('employee')
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($record) {
                // Determine if this was an IN or OUT punch based on which time is closer to now
                $time = $record->updated_at->setTimezone('Asia/Kolkata')->format('H:i');
                $direction = 'LOG';
                $image = null;

                // Simple logic: if out_time is set and close to updated_at, it's OUT, else IN
                // Safe parsing for calculations
                $outTime = $record->out_time ? \Carbon\Carbon::parse($record->out_time) : null;

                if ($outTime && $record->updated_at->diffInMinutes($outTime) < 5) {
                    $direction = 'OUT';
                    $image = $record->out_image;
                } elseif ($record->in_time) {
                    $direction = 'IN';
                    $image = $record->in_image;
                }

                return [
                    'emp_name' => $record->employee->name ?? 'Unknown',
                    'emp_code' => $record->employee->device_emp_code ?? '-',
                    'time' => $time,
                    'direction' => $direction,
                    'image' => $image ? asset($image) : null,
                    'is_mobile' => !empty($image)
                ];
            });
    }
}
