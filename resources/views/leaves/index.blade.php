@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Leave Management</h2>

            <!-- Apply Leave Modal Trigger (using simple toggle for now or we can implement a separate create page if preferred, but modal is nicer. Let's stick to inline form or separate section below for simplicity as per plan) -->
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Apply for Leave Section -->
            <div class="md:col-span-1 bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4 h-fit">
                <h3 class="text-xl font-bold mb-4">Apply for Leave</h3>
                <form action="{{ route('leaves.store') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Employee</label>
                        <select name="employee_id"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                            required>
                            <option value="">Select Employee</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Leave Type</label>
                        <select name="leave_type_id"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                            required>
                            <option value="">Select Type</option>
                            @foreach($leaveTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">From</label>
                            <input type="date" name="start_date"
                                class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                                required>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">To</label>
                            <input type="date" name="end_date"
                                class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                                required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Reason</label>
                        <textarea name="reason"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                            rows="3"></textarea>
                    </div>

                    <div class="flex items-center justify-between">
                        <button
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full"
                            type="submit">
                            Submit Application
                        </button>
                    </div>
                </form>
            </div>

            <!-- Leave History / Approval List -->
            <div class="md:col-span-2 bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <h3 class="text-xl font-bold mb-4">Leave Requests</h3>
                <div class="overflow-x-auto">
                    <table class="text-left w-full border-collapse">
                        <thead>
                            <tr>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Employee</th>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Type</th>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Dates</th>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Status</th>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leaves as $leave)
                                <tr class="hover:bg-grey-lighter">
                                    <td class="py-2 px-4 border-b border-grey-light">{{ $leave->employee->name }}</td>
                                    <td class="py-2 px-4 border-b border-grey-light">
                                        <span
                                            class="bg-gray-200 text-gray-700 py-1 px-2 rounded text-xs">{{ $leave->leaveType->code }}</span>
                                    </td>
                                    <td class="py-2 px-4 border-b border-grey-light text-sm">
                                        {{ $leave->start_date->format('d M') }} - {{ $leave->end_date->format('d M') }}<br>
                                        <span
                                            class="text-xs text-gray-500">{{ $leave->start_date->diffInDays($leave->end_date) + 1 }}
                                            days</span>
                                    </td>
                                    <td class="py-2 px-4 border-b border-grey-light">
                                        @if($leave->status == 'approved')
                                            <span class="text-green-600 font-bold text-xs uppercase">Approved</span>
                                        @elseif($leave->status == 'rejected')
                                            <span class="text-red-600 font-bold text-xs uppercase">Rejected</span>
                                        @else
                                            <span class="text-yellow-600 font-bold text-xs uppercase">Pending</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-4 border-b border-grey-light">
                                        @if($leave->status == 'pending')
                                            <form action="{{ route('leaves.updateStatus', $leave->id) }}" method="POST"
                                                class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit"
                                                    class="text-green-600 hover:text-green-800 text-xs font-bold mr-2">Approve</button>
                                            </form>
                                            <form action="{{ route('leaves.updateStatus', $leave->id) }}" method="POST"
                                                class="inline-block" onsubmit="return promptRejection(this)">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="rejected">
                                                <input type="hidden" name="rejection_reason" class="rejection-reason-input">
                                                <button type="submit"
                                                    class="text-red-600 hover:text-red-800 text-xs font-bold">Reject</button>
                                            </form>
                                        @else
                                            <span class="text-gray-400 text-xs">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function promptRejection(form) {
            const reason = prompt("Please enter a reason for rejection:");
            if (reason === null) return false; // Cancelled
            form.querySelector('.rejection-reason-input').value = reason;
            return true;
        }
    </script>
@endsection