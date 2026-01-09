@extends('layouts.app')

@section('content')
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Employees</h2>
        <a href="{{ route('employees.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add
            Employee</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <form action="{{ route('employees.bulkAssignShift') }}" method="POST">
        @csrf
        <div class="flex items-center mb-4 space-x-4 bg-gray-50 p-4 rounded-lg border">
            <label class="font-bold text-gray-700">Bulk Actions:</label>
            <select name="shift_id" class="border rounded px-3 py-2 text-gray-700 focus:outline-none focus:shadow-outline"
                required>
                <option value="">-- Select Shift --</option>
                @foreach($shifts as $shift)
                    <option value="{{ $shift->id }}">{{ $shift->name }}
                        ({{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }} -
                        {{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }})</option>
                @endforeach
            </select>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                onclick="return confirm('Assign selected shift to checked employees?')">Assign Shift</button>
        </div>

        <div class="bg-white shadow-md rounded my-6">
            <table class="text-left w-full border-collapse">
                <thead>
                    <tr>
                        <th
                            class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                            <input type="checkbox" onclick="toggleAll(this)">
                        </th>
                        <th
                            class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                            Name</th>
                        <th
                            class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                            Department</th>
                        <th
                            class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                            Shift</th>
                        <th
                            class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                            Email</th>
                        <th
                            class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                            Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $employee)
                        <tr class="hover:bg-grey-lighter">
                            <td class="py-4 px-6 border-b border-grey-light">
                                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="emp-checkbox">
                            </td>
                            <td class="py-4 px-6 border-b border-grey-light">{{ $employee->name }}</td>
                            <td class="py-4 px-6 border-b border-grey-light">{{ $employee->department->name ?? 'N/A' }}</td>
                            <td class="py-4 px-6 border-b border-grey-light">{{ $employee->shift->name ?? 'N/A' }}</td>
                            <td class="py-4 px-6 border-b border-grey-light">{{ $employee->email }}</td>
                            <td class="py-4 px-6 border-b border-grey-light">
                                <a href="{{ route('employees.edit', $employee->id) }}"
                                    class="text-blue-600 font-bold py-1 px-3 rounded text-xs bg-blue hover:bg-blue-dark">Edit</a>
                                <!-- Delete button needs to be outside this form or handled carefully. Using a simple link for edit is fine. Delete form nesting is tricky. -->
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </form>

    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByClassName('emp-checkbox');
            for (var i = 0, n = checkboxes.length; i < n; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
@endsection