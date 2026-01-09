@extends('layouts.app')

@section('content')
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Shifts</h2>
        <a href="{{ route('shifts.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add
            Shift</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-white shadow-md rounded my-6">
        <table class="text-left w-full border-collapse">
            <thead>
                <tr>
                    <th
                        class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                        Name</th>
                    <th
                        class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                        Start Time</th>
                    <th
                        class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                        End Time</th>
                    <th
                        class="py-4 px-6 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                        Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($shifts as $shift)
                    <tr class="hover:bg-grey-lighter">
                        <td class="py-4 px-6 border-b border-grey-light">{{ $shift->name }}</td>
                        <td class="py-4 px-6 border-b border-grey-light">{{ $shift->start_time }}</td>
                        <td class="py-4 px-6 border-b border-grey-light">{{ $shift->end_time }}</td>
                        <td class="py-4 px-6 border-b border-grey-light">
                            <a href="{{ route('shifts.edit', $shift->id) }}"
                                class="text-blue-600 font-bold py-1 px-3 rounded text-xs bg-blue hover:bg-blue-dark">Edit</a>
                            <form action="{{ route('shifts.destroy', $shift->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="text-red-600 font-bold py-1 px-3 rounded text-xs bg-red hover:bg-red-dark"
                                    onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection