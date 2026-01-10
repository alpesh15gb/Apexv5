@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Holiday Calendar ({{ $currentYear }})</h2>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Add Holiday Form -->
            <div class="md:col-span-1 bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4 h-fit">
                <h3 class="text-xl font-bold mb-4">Add Holiday</h3>
                <form action="{{ route('holidays.store') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Holiday Name</label>
                        <input type="text" name="name"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                            required placeholder="e.g. Independence Day">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Date</label>
                        <input type="date" name="date"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                            required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Description (Optional)</label>
                        <textarea name="description"
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:shadow-outline"
                            rows="2"></textarea>
                    </div>

                    <button
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full"
                        type="submit">
                        Add Holiday
                    </button>
                </form>
            </div>

            <!-- Holiday List -->
            <div class="md:col-span-2 bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <h3 class="text-xl font-bold mb-4">Holidays List</h3>
                <div class="overflow-x-auto">
                    <table class="text-left w-full border-collapse">
                        <thead>
                            <tr>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Date</th>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Name</th>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Day</th>
                                <th
                                    class="py-2 px-4 bg-grey-lightest font-bold uppercase text-sm text-grey-dark border-b border-grey-light">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($holidays as $holiday)
                                <tr class="hover:bg-grey-lighter">
                                    <td class="py-2 px-4 border-b border-grey-light font-bold text-gray-700">
                                        {{ $holiday->date->format('d M Y') }}
                                    </td>
                                    <td class="py-2 px-4 border-b border-grey-light">
                                        {{ $holiday->name }}
                                    </td>
                                    <td class="py-2 px-4 border-b border-grey-light text-sm text-gray-500">
                                        {{ $holiday->date->format('l') }}
                                    </td>
                                    <td class="py-2 px-4 border-b border-grey-light">
                                        <form action="{{ route('holidays.destroy', $holiday->id) }}" method="POST"
                                            onsubmit="return confirm('Are you sure?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="text-red-600 hover:text-red-800 text-xs font-bold">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 px-4 text-center text-gray-500">No holidays added for this year.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection