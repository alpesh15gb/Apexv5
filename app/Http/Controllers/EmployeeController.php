<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['department.location', 'shift']);

        if ($request->has('location_id') && $request->location_id != '') {
            $query->whereHas('department.location', function ($q) use ($request) {
                $q->where('id', $request->location_id);
            });
        }

        $employees = $query->get();
        $shifts = Shift::all();
        $locations = \App\Models\Location::all();
        $departments = Department::with('location')->get();

        return view('employees.index', compact('employees', 'shifts', 'locations', 'departments'));
    }

    public function bulkAssignShift(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
        ]);

        Employee::whereIn('id', $validated['employee_ids'])->update(['shift_id' => $validated['shift_id']]);

        return redirect()->route('employees.index')->with('success', 'Shifts assigned successfully.');
    }

    public function bulkAssignDepartment(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'department_id' => 'required|exists:departments,id',
        ]);

        Employee::whereIn('id', $validated['employee_ids'])->update(['department_id' => $validated['department_id']]);

        return redirect()->route('employees.index')->with('success', 'Departments (and Locations) updated successfully.');
    }

    public function create()
    {
        $departments = Department::with('location')->get();
        $shifts = Shift::all();
        return view('employees.create', compact('departments', 'shifts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'device_emp_code' => 'required|string|max:50|unique:employees',
            'email' => 'nullable|email',
            'department_id' => 'required|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        // Combine first and last name for the single 'name' column in DB
        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];
        unset($validated['first_name'], $validated['last_name']);

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee)
    {
        $departments = Department::with('location')->get();
        $shifts = Shift::all();
        // Split name for display - assuming checking for space
        $parts = explode(' ', $employee->name, 2);
        $employee->first_name = $parts[0] ?? '';
        $employee->last_name = $parts[1] ?? '';

        return view('employees.edit', compact('employee', 'departments', 'shifts'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'device_emp_code' => ['required', 'string', 'max:50', Rule::unique('employees')->ignore($employee->id)],
            'email' => 'nullable|email',
            'department_id' => 'required|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];
        unset($validated['first_name'], $validated['last_name']);

        $employee->update($validated);

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Employee deleted successfully.');
    }
}
