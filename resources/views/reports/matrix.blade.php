@extends('layouts.app')

@section('content')
    <div x-data="matrixReport()" x-init="fetchData()" class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between mb-4 items-start md:items-center">
            <div>
                <h3 class="text-gray-700 text-2xl font-medium">Monthly Assessment Matrix</h3>
                <p class="text-slate-500 text-sm mt-1">Detailed Work Duration & Overtime</p>
            </div>
            <div class="mt-4 md:mt-0 flex gap-2 items-center bg-white p-2 rounded-md shadow-sm border border-gray-200">
                <select x-model="month" @change="fetchData()" class="border-none text-sm focus:ring-0">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
                            {{ date('F', mktime(0, 0, 0, $m, 10)) }}
                        </option>
                    @endforeach
                </select>
                <select x-model="year" @change="fetchData()" class="border-none text-sm focus:ring-0">
                    @foreach(range(date('Y') - 1, date('Y') + 1) as $y)
                        <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
                <button @click="fetchData()" class="bg-gray-100 hover:bg-gray-200 text-gray-600 p-2 rounded-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                </button>
                <button @click="exportData()"
                    class="bg-apex-600 hover:bg-apex-700 text-white px-3 py-2 rounded-md text-sm font-medium flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export
                </button>
            </div>
        </div>

        <!-- Report Area -->
        <div class="bg-white shadow-sm border border-slate-200 overflow-hidden flex-1 relative">
            <div x-show="loading" class="absolute inset-0 bg-white/80 z-10 flex items-center justify-center">
                <div class="text-slate-500 font-medium">Generating Matrix...</div>
            </div>

            <div class="overflow-x-auto h-full">
                <template x-for="item in reportData" :key="item.employee.id">
                    <div class="mb-8 border-b-4 border-slate-100 pb-4">
                        <!-- Employee Header -->
                        <div
                            class="px-4 py-3 bg-slate-50 border-y border-slate-200 flex justify-between items-center sticky left-0">
                            <div class="flex items-center gap-4">
                                <div class="bg-white border text-center px-2 py-1 rounded">
                                    <div class="text-[10px] uppercase text-slate-400 font-bold">Code</div>
                                    <div class="font-mono font-bold text-slate-700" x-text="item.employee.device_emp_code">
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-800 text-lg" x-text="item.employee.name"></h4>
                                    <div class="text-xs text-slate-500 flex gap-4">
                                        <span>Dept: <span x-text="item.employee.department?.name || '-'"></span></span>
                                        <span>Shift: <span x-text="item.employee.shift?.name || '-'"></span></span>
                                    </div>
                                </div>
                            </div>
                            <!-- Summary Stats -->
                            <div class="flex gap-4 text-xs">
                                <div class="bg-blue-50 px-2 py-1 rounded border border-blue-100">
                                    <span class="text-blue-500 block">Total Duration</span>
                                    <span class="font-mono font-bold text-blue-700"
                                        x-text="item.summary.total_duration"></span>
                                </div>
                                <div class="bg-orange-50 px-2 py-1 rounded border border-orange-100">
                                    <span class="text-orange-500 block">Total OT</span>
                                    <span class="font-mono font-bold text-orange-700" x-text="item.summary.total_ot"></span>
                                </div>
                                <div class="bg-green-50 px-2 py-1 rounded border border-green-100">
                                    <span class="text-green-500 block">Present</span>
                                    <span class="font-mono font-bold text-green-700"
                                        x-text="item.summary.present + '/' + item.summary.shift_count"></span>
                                </div>
                                <div class="bg-red-50 px-2 py-1 rounded border border-red-100">
                                    <span class="text-red-500 block">Late / Early</span>
                                    <span class="font-mono font-bold text-red-700"
                                        x-text="item.summary.late + ' / ' + item.summary.early"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Matrix Grid -->
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100 text-slate-500 border-b border-slate-200">
                                    <th
                                        class="p-1 border-r border-slate-200 w-24 text-left pl-2 sticky left-0 bg-slate-100 z-10">
                                        Metric</th>
                                    <template x-for="day in daysInMonth" :key="day">
                                        <th class="p-1 border-r border-slate-200 min-w-[40px] text-center font-normal">
                                            <div x-text="day"></div>
                                            <div class="text-[9px]" x-text="getDayInitial(day)"></div>
                                        </th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <!-- Helper to render rows -->
                                <template x-for="metric in metricsList" :key="metric.key">
                                    <tr class="hover:bg-yellow-50/50">
                                        <td class="p-1 border-r border-slate-200 font-medium text-slate-600 pl-2 sticky left-0 bg-white z-10"
                                            x-text="metric.label"></td>
                                        <template x-for="day in daysInMonth" :key="day">
                                            <td class="p-1 border-r border-slate-200 text-center font-mono whitespace-nowrap px-0.5"
                                                :class="getCellClass(metric.key, item.days[day])">
                                                <span x-text="item.days[day] ? item.days[day][metric.key] : '-'"></span>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <div x-show="!loading && reportData.length === 0" class="p-12 text-center text-slate-400">
                    No data available for this month.
                </div>
            </div>
        </div>
    </div>

    <script>
        function matrixReport() {
            return {
                month: {{ date('n') }},
                year: {{ date('Y') }},
                loading: false,
                reportData: [],
                metricsList: [
                    { key: 'status', label: 'Status' },
                    { key: 'in_time', label: 'In Time' },
                    { key: 'out_time', label: 'Out Time' },
                    { key: 'duration', label: 'Duration' },
                    { key: 'late_by', label: 'Late By' },
                    { key: 'early_by', label: 'Early By' },
                    { key: 'ot', label: 'OT' },
                ],
                get daysInMonth() {
                    // Return array 1..daysInMonth
                    return Array.from({ length: new Date(this.year, this.month, 0).getDate() }, (_, i) => i + 1);
                },
                fetchData() {
                    this.loading = true;
                    fetch(`/reports/matrix-data?month=${this.month}&year=${this.year}`)
                        .then(res => res.json())
                        .then(data => {
                            this.reportData = data.data;
                        })
                        .catch(err => console.error(err))
                        .finally(() => this.loading = false);
                },
                exportData() {
                    window.location.href = `/reports/export/matrix?month=${this.month}&year=${this.year}`;
                },
                getDayInitial(day) {
                    const date = new Date(this.year, this.month - 1, day);
                    return date.toLocaleDateString('en-US', { weekday: 'narrow' });
                },
                getCellClass(metric, dayData) {
                    if (!dayData) return 'bg-slate-50';

                    if (metric === 'status') {
                        if (dayData.status === 'P') return 'bg-green-100 text-green-700 font-bold';
                        if (dayData.status === 'A') return 'bg-red-50 text-red-400';
                        if (dayData.status === 'H') return 'bg-blue-100 text-blue-700';
                        if (dayData.status === 'L') return 'bg-yellow-100 text-yellow-700';
                    }

                    if (metric === 'late_by' && dayData.late_by > 0) return 'text-red-600 font-bold';
                    if (metric === 'early_by' && dayData.early_by > 0) return 'text-orange-600 font-bold';

                    return 'text-slate-600';
                }
            }
        }
    </script>
@endsection