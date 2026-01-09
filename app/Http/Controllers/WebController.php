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
    public function monthlyReportView()
    {
        $serverDate = \Carbon\Carbon::now()->format('Y-m');
        return view('reports.monthly', compact('serverDate'));
    }
    }
}
