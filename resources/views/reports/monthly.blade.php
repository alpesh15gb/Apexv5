@extends('layouts.app')

@section('content')
    <div x-data="monthlyRegister()" x-init="fetchData()" class="flex flex-col">
        <div class="flex flex-col md:flex-row justify-between mb-8 items-start md:items-center">
            <div>
                <h3 class="text-gray-700 text-3xl font-medium">Monthly Register</h3>
                <p class="text-slate-500 mt-1">Attendance records for <span x-text="monthName"></span></p>
            </div>
            <div class="mt-4 md:mt-0 flex gap-2">
                <input type="month" x-model="selectedMonth" @change="fetchData()"
                    class="border-gray-300 focus:border-apex-500 focus:ring-apex-500 rounded-md shadow-sm">
                <button
                    class="bg-apex-600 text-white px-4 py-2 rounded-md hover:bg-apex-700 focus:outline-none flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export
                </button>
            </div>
        </div>

        <div class="overflow-x-auto bg-white rounded-lg shadow-sm border border-slate-100">
            <template x-if="loading">
                <div class="p-8 text-center text-slate-500">Loading data...</div>
            </template>

            <table x-show="!loading" class="w-full whitespace-no-wrap table-auto text-xs">
                <thead>
                    <tr class="text-left font-bold bg-slate-50 border-b border-slate-200 text-slate-600">
                        <th class="px-3 py-3 w-10 sticky left-0 bg-slate-50 z-20">#</th>
                        <th class="px-3 py-3 w-32 sticky left-10 bg-slate-50 z-20">Name</th>
                        <template x-for="day in daysInMonth" :key="day">
                            <th class="px-2 py-3 text-center border-l w-8" x-text="day"></th>
                        </template>
                        <th class="px-2 py-3 text-center border-l bg-green-50 text-green-700">P</th>
                        <th class="px-2 py-3 text-center bg-red-50 text-red-700">A</th>
                        <th class="px-2 py-3 text-center bg-yellow-50 text-yellow-700">L</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-100">
                    <template x-for="(emp, index) in reportData" :key="index">
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 sticky left-0 bg-white z-10 font-bold text-slate-500 border-r"
                                x-text="index + 1"></td>
                            <td class="px-3 py-2 sticky left-10 bg-white z-10 border-r">
                                <p class="font-semibold text-slate-700" x-text="emp.employee_name"></p>
                                <p class="text-[10px] text-slate-400" x-text="emp.department"></p>
                            </td>

                            <template x-for="day in daysInMonth" :key="day">
                                <td class="px-1 py-2 text-center border-l border-slate-100">
                                    <!-- Timings Cell -->
                                    <div class="flex items-center justify-center w-full h-full min-h-[30px] p-1 rounded" 
                                        :class="{
                                            'bg-green-100 text-green-700': emp.days[day] && emp.days[day].status === 'P',
                                            'bg-red-100 text-red-700': emp.days[day] && emp.days[day].status === 'A',
                                            'bg-yellow-100 text-yellow-700': emp.days[day] && (emp.days[day].status === 'L' || emp.days[day].status === 'HD'),
                                            'bg-blue-50 text-blue-600': emp.days[day] && emp.days[day].status === 'H',
                                            'bg-slate-50 text-slate-300': !emp.days[day]
                                        }">
                                        <span class="text-[9px] font-bold whitespace-nowrap" 
                                              x-text="emp.days[day] ? emp.days[day].label : '-'">
                                        </span>
                                    </div>
                                </td>
                            </template>

                            <td class="px-2 py-2 text-center font-bold text-green-600 border-l bg-green-50/30"
                                x-text="emp.summary.P"></td>
                            <td class="px-2 py-2 text-center font-bold text-red-600 bg-red-50/30" x-text="emp.summary.A">
                            </td>
                            <td class="px-2 py-2 text-center font-bold text-yellow-600 bg-yellow-50/30"
                                x-text="emp.summary.Late"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function monthlyRegister() {
            return {
                selectedMonth: '{{ $serverDate }}', // Initialize with server date
                loading: false,
                reportData: [],
                daysInMonth: [],
                get monthName() {
                    const date = new Date(this.selectedMonth + '-01');
                    return date.toLocaleString('default', { month: 'long', year: 'numeric' });
                },
                fetchData() {
                    this.loading = true;
                    const [year, month] = this.selectedMonth.split('-');

                    // Populate days array
                    const days = new Date(year, month, 0).getDate();
                    this.daysInMonth = Array.from({ length: days }, (_, i) => i + 1);

                    fetch(`/api/reports/monthly?month=${month}&year=${year}`)
                        .then(res => res.json())
                        .then(data => {
                            this.reportData = Object.values(data.data); // Ensure array
                        })
                        .catch(err => console.error(err))
                        .finally(() => {
                            this.loading = false;
                        });
                }
            }
        }
    </script>
@endsection