<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Carbon\Carbon;

class HolidayController extends Controller
{
    public function index()
    {
        $currentYear = Carbon::now()->year;
        $holidays = Holiday::where('year', $currentYear)
            ->orderBy('date', 'asc')
            ->get();

        return view('holidays.index', compact('holidays', 'currentYear'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date|unique:holidays,date',
        ]);

        Holiday::create([
            'name' => $validated['name'],
            'date' => $validated['date'],
            'year' => Carbon::parse($validated['date'])->year,
            'description' => $request->description
        ]);

        return redirect()->route('holidays.index')->with('success', 'Holiday added successfully.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return redirect()->route('holidays.index')->with('success', 'Holiday deleted successfully.');
    }
}
