<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebController extends Controller
{
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
}
