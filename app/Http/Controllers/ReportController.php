<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function dailyReport(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $filters = $request->only(['department_id', 'status']);

        $data = $this->reportService->getDailyReport($date, $filters);

        // If 'export' param is present, we would trigger export logic here
        // For now, return JSON
        return response()->json([
            'date' => $date,
            'data' => $data
        ]);
    }

    public function monthlyRegister(Request $request)
    {
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);
        $filters = $request->only(['department_id']);

        $data = $this->reportService->getMonthlyRegister($month, $year, $filters);

        return response()->json([
            'month' => $month,
            'year' => $year,
            'data' => $data
        ]);
    }

    public function monthlyExport(Request $request)
    {
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        $callback = $this->reportService->exportMonthlyRegister($month, $year);

        $filename = "monthly_register_{$year}_{$month}.csv";

        return response()->stream($callback, 200, [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }

    public function dashboardStats(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());

        $present_count = \App\Models\DailyAttendance::where('date', $date)
            ->where('status', 'Present')
            ->count();

        $absent_count = \App\Models\DailyAttendance::where('date', $date)
            ->where('status', 'Absent')
            ->count();

        $late_count = \App\Models\DailyAttendance::where('date', $date)
            ->where('late_minutes', '>', 0)
            ->count();

        $total_staff = \App\Models\Employee::where('is_active', true)->count();

        // If attendance hasn't been calculated for today, Absent count might be 0.
        // We should really count absents as Total - Present (roughly) if records are missing, 
        // but let's rely on DailyAttendance being generated.

        return response()->json([
            'date' => $date,
            'present' => $present_count,
            'absent' => $absent_count,
            'late' => $late_count,
            'total_staff' => $total_staff
        ]);
    }
}
