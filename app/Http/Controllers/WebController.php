<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReportService;

class WebController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function dashboard()
    {
        return view('dashboard');
    }

    public function monthlyReportView()
    {
        return view('reports.monthly', [
            'serverDate' => \Carbon\Carbon::today()->format('Y-m')
        ]);
    }

    public function dailyReportView()
    {
        return view('reports.daily', [
            'serverDate' => \Carbon\Carbon::today()->format('Y-m-d')
        ]);
    }

    public function weeklyReportView()
    {
        return view('reports.weekly', [
            'startDate' => \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d'),
            'endDate' => \Carbon\Carbon::now()->endOfWeek()->format('Y-m-d')
        ]);
    }

    public function matrixReportView()
    {
        return view('reports.matrix', [
            'serverDate' => \Carbon\Carbon::today()->format('Y-m')
        ]);
    }

    public function matrixPrintView(Request $request)
    {
        $month = $request->input('month', \Carbon\Carbon::now()->month);
        $year = $request->input('year', \Carbon\Carbon::now()->year);
        $filters = $request->only(['department_id']);

        $data = $this->reportService->getMatrixReport($month, $year, $filters);
        $serverDate = \Carbon\Carbon::createFromDate($year, $month, 1);

        return view('reports.matrix_print', [
            'data' => $data,
            'month' => $month,
            'year' => $year,
            'monthName' => $serverDate->format('F Y')
        ]);
    }
}
