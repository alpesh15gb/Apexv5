@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h3 class="text-gray-700 text-3xl font-medium">Dashboard</h3>
        <p class="text-slate-500 mt-1">Overview for {{ \Carbon\Carbon::now()->format('d M Y') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-4">
        <!-- Card 1: Present -->
        <div class="w-full px-6 py-5 bg-white rounded-lg shadow-sm border border-slate-100 flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Present</p>
                <p class="text-2xl font-bold text-slate-700" id="stat-present">-</p>
            </div>
        </div>

        <!-- Card 2: Absent -->
        <div class="w-full px-6 py-5 bg-white rounded-lg shadow-sm border border-slate-100 flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Absent</p>
                <p class="text-2xl font-bold text-slate-700" id="stat-absent">-</p>
            </div>
        </div>

        <!-- Card 3: Late -->
        <div class="w-full px-6 py-5 bg-white rounded-lg shadow-sm border border-slate-100 flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Late Arrivals</p>
                <p class="text-2xl font-bold text-slate-700" id="stat-late">-</p>
            </div>
        </div>

        <!-- Card 4: Total -->
        <div class="w-full px-6 py-5 bg-white rounded-lg shadow-sm border border-slate-100 flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Total Staff</p>
                <p class="text-2xl font-bold text-slate-700">-</p>
            </div>
        </div>
    </div>

    <!-- Chart Placeholder -->
    <div class="bg-white rounded-lg shadow-sm border border-slate-100 p-6">
        <h4 class="text-lg font-semibold text-slate-700 mb-4">Attendance Trend</h4>
        <div class="h-64 flex items-center justify-center bg-slate-50 rounded text-slate-400">
            Chart Integration Pending (Requires Chart.js or ApexCharts)
        </div>
    </div>

    <script>
        // Fetch stats (Mock for now, will connect to API later)
        document.addEventListener('DOMContentLoaded', () => {
            // fetch('/api/stats') ...
            document.getElementById('stat-present').innerText = '12';
            document.getElementById('stat-absent').innerText = '3';
            document.getElementById('stat-late').innerText = '2';
        });
    </script>
@endsection