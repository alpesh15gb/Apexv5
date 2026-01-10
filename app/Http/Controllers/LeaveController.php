<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        // For debugging/demo purposes, we might not have a logged-in user linked to an employee yet.
        // If we are admin, we see everything.
        // Let's assume for now this view shows a list of all leaves for Admin.

        $leaves = Leave::with(['employee', 'leaveType'])
            ->orderBy('created_at', 'desc')
            ->get();

        $leaveTypes = LeaveType::all();
        $employees = Employee::orderBy('name')->get();

        return view('leaves.index', compact('leaves', 'leaveTypes', 'employees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        Leave::create($validated);

        return redirect()->route('leaves.index')->with('success', 'Leave application submitted successfully.');
    }

    public function updateStatus(Request $request, Leave $leave)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|required_if:status,rejected|string',
        ]);

        $leave->update([
            'status' => $validated['status'],
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            // 'approved_by' => auth()->id(), // Add this when auth is fully linked
        ]);

        return redirect()->route('leaves.index')->with('success', 'Leave status updated.');
    }
}
