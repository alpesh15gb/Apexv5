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
}
