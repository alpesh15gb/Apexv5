@extends('layouts.app')

@section('content')
    <div x-data="dailyReport()" x-init="fetchData()" class="flex flex-col">
        <div class="flex flex-col md:flex-row justify-between mb-8 items-start md:items-center">
            <div>
                <h3 class="text-gray-700 text-3xl font-medium">Daily Attendance</h3>
                <p class="text-slate-500 mt-1">Status for <span x-text="dateLabel"></span></p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                <!-- Company Filter -->
                <select x-model="filters.company_id" @change="fetchData()"
                    class="border-gray-300 focus:border-apex-500 focus:ring-apex-500 rounded-md shadow-sm text-sm">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>

                <!-- Location Filter -->
                <select x-model="filters.location_id" @change="fetchData()"
                    class="border-gray-300 focus:border-apex-500 focus:ring-apex-500 rounded-md shadow-sm text-sm">
                    <option value="">All Locations</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>

                <!-- Search Input -->
                <div class="relative">
                    <input type="text" x-model.debounce.500ms="filters.search" @input="fetchData()"
                        placeholder="Search Name/Code..."
                        class="border-gray-300 focus:border-apex-500 focus:ring-apex-500 rounded-md shadow-sm text-sm pl-8 p-2">
                    <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>

                <input type="date" x-model="selectedDate" @change="fetchData()"
                    class="border-gray-300 focus:border-apex-500 focus:ring-apex-500 rounded-md shadow-sm">

                <button @click="exportData()"
                    class="bg-apex-600 text-white px-4 py-2 rounded-md hover:bg-apex-700 focus:outline-none flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export
                </button>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-100 overflow-hidden">
            <!-- Table wrapper -->
            <template x-if="loading">
                <div class="p-8 text-center text-slate-500">Loading data...</div>
            </template>

            <div class="overflow-x-auto">
                <table x-show="!loading" class="w-full whitespace-no-wrap table-auto">
                    <!-- existing table content -->
                    <thead>
                        <tr
                            class="text-left font-bold bg-slate-50 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider">
                            <th class="px-6 py-4">Employee</th>
                            <th class="px-6 py-4">Department</th>
                            <th class="px-6 py-4">Shift</th>
                            <th class="px-6 py-4">In Time</th>
                            <th class="px-6 py-4">Out Time</th>
                            <th class="px-6 py-4">Late</th>
                            <th class="px-6 py-4">Proof</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-100 text-sm">
                        <template x-for="(record, index) in reportData" :key="index">
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="font-medium text-slate-900"
                                                x-text="record.employee?.name || 'Unknown'"></div>
                                            <div class="text-slate-500 text-xs"
                                                x-text="record.employee?.device_emp_code || '-'"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500" x-text="record.employee?.department?.name || '-'"></td>
                                <td class="px-6 py-4 text-slate-500" x-text="record.shift?.name || '-'"></td>
                                <td class="px-6 py-4 font-mono text-slate-600" x-text="formatTime(record.in_time) || '-'">
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-600" x-text="formatTime(record.out_time) || '-'">
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <span x-show="record.late_minutes > 0" class="text-red-500 font-bold"
                                        x-text="record.late_minutes + ' m'"></span>
                                    <span x-show="!record.late_minutes">-</span>
                                </td>
                                <td class="px-6 py-4 flex gap-2">
                                    <template x-if="record.in_image">
                                        <button @click="$dispatch('open-photo', { url: record.in_image })"
                                            class="text-blue-500 hover:text-blue-700" title="View Photo">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z">
                                                </path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                        </button>
                                    </template>
                                    <template x-if="record.in_lat">
                                        <a :href="`https://www.google.com/maps?q=${record.in_lat},${record.in_long}`"
                                            target="_blank" class="text-green-500 hover:text-green-700"
                                            title="View Location">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                </path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                        </a>
                                    </template>
                                    <span x-show="!record.in_image && !record.in_lat" class="text-slate-300">-</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="{
                                                                            'bg-green-100 text-green-800': record.status === 'Present',
                                                                            'bg-red-100 text-red-800': record.status === 'Absent',
                                                                            'bg-yellow-100 text-yellow-800': record.status === 'Half Day' || record.status === 'Late',
                                                                            'bg-blue-100 text-blue-800': record.status === 'Holiday' || record.status === 'Leave'
                                                                        }" x-text="record.status">
                                    </span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="reportData.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                No records found for this date.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    </div>

    <!-- Photo Modal -->
    <div x-data="{ open: false, url: '' }" @open-photo.window="open = true; url = $event.detail.url" x-show="open"
        class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/80" style="display: none;"
        x-transition.opacity>

        <div @click.away="open = false" class="bg-white p-2 rounded-lg max-w-lg w-full relative">
            <button @click="open = false" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <img :src="`/${url}`" alt="Proof" class="w-full h-auto rounded">
        </div>
    </div>

    <script>
        function dailyReport() {
            return {
                selectedDate: '{{ $serverDate }}',
                filters: {
                    company_id: '',
                    location_id: '',
                    status: '{{ request('status') }}',
                    search: ''
                },
                loading: false,
                reportData: [],
                get dateLabel() {
                    return new Date(this.selectedDate).toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                    });
                },
                fetchData() {
                    this.loading = true;
                    let url = `/api/reports/daily?date=${this.selectedDate}`;
                    if (this.filters.company_id) url += `&company_id=${this.filters.company_id}`;
                    if (this.filters.location_id) url += `&location_id=${this.filters.location_id}`;
                    if (this.filters.status) url += `&status=${this.filters.status}`;
                    if (this.filters.search) url += `&search=${this.filters.search}`;

                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            this.reportData = data.data;
                        })
                        .catch(err => console.error(err))
                        .finally(() => this.loading = false);
                },
                exportData() {
                    let url = `/reports/export/daily?date=${this.selectedDate}`;
                    if (this.filters.company_id) url += `&company_id=${this.filters.company_id}`;
                    if (this.filters.location_id) url += `&location_id=${this.filters.location_id}`;
                    if (this.filters.search) url += `&search=${this.filters.search}`;
                    window.location.href = url;
                },
                formatTime(datetime) {
                    if (!datetime) return null;
                    // Fix: Strip 'Z' to treat as local time (Server sends UTC formatted string for IST stored time)
                    if (datetime.endsWith('Z')) datetime = datetime.slice(0, -1);
                    return new Date(datetime).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
                }
            }
        }
    </script>
@endsection