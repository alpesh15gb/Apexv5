@extends('layouts.app')

@section('content')
    <div x-data="weeklyReport()" x-init="fetchData()" class="flex flex-col">
        <div class="flex flex-col md:flex-row justify-between mb-8 items-start md:items-center">
            <div>
                <h3 class="text-gray-700 text-3xl font-medium">Detailed Attendance</h3>
                <p class="text-slate-500 mt-1">Logs from <span x-text="startDate"></span> to <span x-text="endDate"></span>
                </p>
            </div>
            <div
                class="mt-4 md:mt-0 flex flex-wrap gap-2 items-center bg-white p-2 rounded-md shadow-sm border border-gray-200">
                <!-- Company Filter -->
                <select x-model="filters.company_id" @change="fetchData()"
                    class="border-gray-200 focus:ring-0 text-gray-600 text-sm">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
                <div class="h-4 w-px bg-gray-300 mx-1"></div>

                <!-- Location Filter -->
                <select x-model="filters.location_id" @change="fetchData()"
                    class="border-gray-200 focus:ring-0 text-gray-600 text-sm">
                    <option value="">All Locations</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
                <div class="h-4 w-px bg-gray-300 mx-1"></div>

                <input type="date" x-model="startDate" class="border-none focus:ring-0 text-sm">
                <span class="text-gray-400">to</span>
                <input type="date" x-model="endDate" class="border-none focus:ring-0 text-sm">

                <button @click="fetchData()" class="bg-gray-100 hover:bg-gray-200 text-gray-600 p-2 rounded-md mx-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </button>

                <div class="h-4 w-px bg-gray-300 mx-1"></div>

                <button @click="exportData()"
                    class="bg-apex-600 text-white px-4 py-1.5 rounded-md hover:bg-apex-700 focus:outline-none flex items-center text-sm">
                    Export
                </button>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-slate-100">
            <!-- Table content -->
            <template x-if="loading">
                <div class="p-8 text-center text-slate-500">Loading data...</div>
            </template>

            <div x-show="!loading" class="divide-y divide-slate-100">
                <template x-for="(records, empId) in groupedData" :key="empId">
                    <div class="p-4">
                        <!-- Employee Header -->
                        <div class="flex items-center justify-between mb-3 bg-slate-50 p-2 rounded">
                            <div class="flex items-center gap-3">
                                <div class="bg-white border rounded px-2 py-1 text-xs font-mono text-slate-500"
                                    x-text="records[0].employee?.device_emp_code || 'N/A'"></div>
                                <h4 class="font-bold text-slate-700"
                                    x-text="records[0].employee?.name || 'Unknown Employee'"></h4>
                                <span class="text-xs text-slate-400"
                                    x-text="records[0].employee?.department?.name || ''"></span>
                            </div>
                        </div>

                        <!-- Employee Logs Table -->
                        <table class="w-full text-sm">
                            <thead class="text-xs text-slate-400 uppercase bg-white border-b">
                                <tr>
                                    <th class="px-2 py-2 text-left">Date</th>
                                    <th class="px-2 py-2 text-left">Day</th>
                                    <th class="px-2 py-2 text-left">Shift</th>
                                    <th class="px-2 py-2 text-left">In</th>
                                    <th class="px-2 py-2 text-left">Out</th>
                                    <th class="px-2 py-2 text-left">Late</th>
                                    <th class="px-2 py-2 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="record in records" :key="record.id">
                                    <tr class="border-b border-dashed border-slate-100 hover:bg-slate-50/50">
                                        <td class="px-2 py-2 font-mono text-slate-600" x-text="record.date.split('T')[0]">
                                        </td>
                                        <td class="px-2 py-2 text-slate-500 text-xs" x-text="getDayName(record.date)"></td>
                                        <td class="px-2 py-2 text-slate-500 text-xs" x-text="record.shift?.name || '-'">
                                        </td>
                                        <td class="px-2 py-2 font-mono" x-text="formatTime(record.in_time)"></td>
                                        <td class="px-2 py-2 font-mono" x-text="formatTime(record.out_time)"></td>
                                        <td class="px-2 py-2">
                                            <span x-show="record.late_minutes > 0" class="text-red-500 text-xs font-bold"
                                                x-text="record.late_minutes + ' m'"></span>
                                        </td>
                                        <td class="px-2 py-2">
                                            <span class="px-2 inline-flex text-[10px] leading-4 font-semibold rounded-full"
                                                :class="{
                                                        'bg-green-100 text-green-800': record.status === 'Present',
                                                        'bg-red-100 text-red-800': record.status === 'Absent',
                                                        'bg-yellow-100 text-yellow-800': record.status === 'Half Day' || record.status === 'Late',
                                                        'bg-blue-100 text-blue-800': record.status === 'Holiday' || record.status === 'Leave'
                                                    }" x-text="record.status">
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <div x-show="Object.keys(groupedData).length === 0" class="p-8 text-center text-slate-400">
                    No data found for the selected range.
                </div>
            </div>
        </div>
    </div>

    <script>
        function weeklyReport() {
            return {
                startDate: '{{ $startDate }}',
                endDate: '{{ $endDate }}',
                filters: {
                    company_id: '',
                    location_id: ''
                },
                loading: false,
                groupedData: {},
                fetchData() {
                    this.loading = true;
                    let url = `/reports/detailed?start_date=${this.startDate}&end_date=${this.endDate}`;
                    if (this.filters.company_id) url += `&company_id=${this.filters.company_id}`;
                    if (this.filters.location_id) url += `&location_id=${this.filters.location_id}`;

                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            this.groupedData = data.data;
                        })
                        .catch(err => console.error(err))
                        .finally(() => this.loading = false);
                },
                exportData() {
                    let url = `/reports/export/weekly?start_date=${this.startDate}&end_date=${this.endDate}`;
                    if (this.filters.company_id) url += `&company_id=${this.filters.company_id}`;
                    if (this.filters.location_id) url += `&location_id=${this.filters.location_id}`;
                    window.location.href = url;
                },
                formatTime(datetime) {
                    if (!datetime) return '-';
                    // Fix: Strip 'Z' to treat as local time
                    if (datetime.endsWith('Z')) datetime = datetime.slice(0, -1);
                    return new Date(datetime).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
                },
                getDayName(dateStr) {
                    return new Date(dateStr).toLocaleDateString('en-US', { weekday: 'short' });
                }
            }
        }
    </script>
@endsection