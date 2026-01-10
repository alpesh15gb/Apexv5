@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h3 class="text-slate-800 text-3xl font-bold tracking-tight">Dashboard</h3>
        <p class="text-slate-500 mt-2 text-sm">Overview for {{ \Carbon\Carbon::now()->format('l, d M Y') }}</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Present -->
        <a href="{{ route('reports.daily', ['status' => 'Present']) }}" class="block group">
            <div
                class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 text-white shadow-lg shadow-green-200 transform transition group-hover:-translate-y-1 duration-300">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-green-100 font-medium text-sm">Present</p>
                        <h4 class="text-4xl font-bold mt-2" id="stat-present">-</h4>
                    </div>
                    <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Absent -->
        <a href="{{ route('reports.daily', ['status' => 'Absent']) }}" class="block group">
            <div
                class="bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl p-6 text-white shadow-lg shadow-red-200 transform transition group-hover:-translate-y-1 duration-300">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-red-100 font-medium text-sm">Absent</p>
                        <h4 class="text-4xl font-bold mt-2" id="stat-absent">-</h4>
                    </div>
                    <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Late -->
        <a href="{{ route('reports.daily', ['status' => 'Late']) }}" class="block group">
            <div
                class="bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl p-6 text-white shadow-lg shadow-orange-200 transform transition group-hover:-translate-y-1 duration-300">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-amber-100 font-medium text-sm">Late Arrivals</p>
                        <h4 class="text-4xl font-bold mt-2" id="stat-late">-</h4>
                    </div>
                    <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Total Staff -->
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col justify-center items-center">
            <p class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Staff</p>
            <h4 class="text-3xl font-bold text-slate-700 mt-2" id="stat-total">-</h4>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Charts -->
        <div class="lg:col-span-2 space-y-8">

            <!-- Weekly Trend -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h4 class="text-lg font-bold text-slate-800 mb-6">Weekly Attendance Trend</h4>
                <div id="weeklyChart" class="w-full h-80"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Today's Chart -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-6">Today's Status</h4>
                    <div id="attendanceChart" class="w-full flex items-center justify-center min-h-[250px]"></div>
                </div>

                <!-- Department Stats -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-6">Top Departments (Present %)</h4>
                    <div id="deptChart" class="w-full h-64"></div>
                </div>
            </div>
        </div>

        <!-- Right Column: Recent Activity -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 h-fit sticky top-6">
            <h4 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-sky-500"></span>
                </span>
                Live Feed
            </h4>

            <div class="space-y-6 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar" id="recent-activity-list">
                <!-- Javascript will populate this -->
                <div class="animate-pulse flex space-x-4">
                    <div class="rounded-full bg-slate-200 h-10 w-10"></div>
                    <div class="flex-1 space-y-2 py-1">
                        <div class="h-2 bg-slate-200 rounded"></div>
                        <div class="space-y-3">
                            <div class="grid grid-cols-3 gap-4">
                                <div class="h-2 bg-slate-200 rounded col-span-2"></div>
                                <div class="h-2 bg-slate-200 rounded col-span-1"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetchStats();
            // Refresh every 30 seconds
            setInterval(fetchStats, 30000);
        });

        function fetchStats() {
            fetch('/api/stats')
                .then(response => response.json())
                .then(data => {
                    // Update Stats Cards
                    document.getElementById('stat-present').innerText = data.present || 0;
                    document.getElementById('stat-absent').innerText = data.absent || 0;
                    document.getElementById('stat-late').innerText = data.late || 0;
                    document.getElementById('stat-total').innerText = data.total_staff || 0;

                    // Update Recent Activity
                    updateRecentActivity(data.recent_punches);

                    // Render Charts
                    renderDonutChart(data);
                    renderWeeklyChart(data.weekly_stats);
                    renderDeptChart(data.department_stats);
                })
                .catch(error => console.error('Error fetching stats:', error));
        }

        let chartInstance = null;
        let weeklyChartInstance = null;
        let deptChartInstance = null;

        function renderDonutChart(data) {
            const options = {
                series: [data.present || 0, data.absent || 0, data.late || 0],
                chart: {
                    type: 'donut',
                    height: 280,
                    fontFamily: 'Inter, sans-serif',
                    animations: { enabled: true }
                },
                labels: ['Present', 'Absent', 'Late'],
                colors: ['#10b981', '#f43f5e', '#f59e0b'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                name: { show: true },
                                value: { show: true, fontSize: '24px', fontWeight: 700 },
                                total: {
                                    show: true,
                                    label: 'Checked In',
                                    formatter: function (w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { position: 'bottom', fontFamily: 'Inter, sans-serif' },
                stroke: { show: false }
            };

            if (chartInstance) {
                chartInstance.updateSeries([data.present || 0, data.absent || 0, data.late || 0]);
            } else {
                chartInstance = new ApexCharts(document.querySelector("#attendanceChart"), options);
                chartInstance.render();
            }
        }

        function renderWeeklyChart(stats) {
            if (!stats) return;

            const categories = stats.map(s => s.date);
            const presentData = stats.map(s => s.present);
            const absentData = stats.map(s => s.absent);
            const lateData = stats.map(s => s.late);

            const options = {
                series: [
                    { name: 'Present', data: presentData },
                    { name: 'Absent', data: absentData },
                    { name: 'Late', data: lateData }
                ],
                chart: {
                    type: 'bar',
                    height: 320,
                    stacked: true,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif'
                },
                colors: ['#10b981', '#f43f5e', '#f59e0b'],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        borderRadius: 4,
                        columnWidth: '40%'
                    },
                },
                xaxis: { categories: categories },
                legend: { position: 'top' },
                fill: { opacity: 1 }
            };

            if (weeklyChartInstance) {
                weeklyChartInstance.updateSeries([
                    { name: 'Present', data: presentData },
                    { name: 'Absent', data: absentData },
                    { name: 'Late', data: lateData }
                ]);
            } else {
                weeklyChartInstance = new ApexCharts(document.querySelector("#weeklyChart"), options);
                weeklyChartInstance.render();
            }
        }

        function renderDeptChart(stats) {
            if (!stats) return;

            const categories = stats.map(s => s.name);
            const percentages = stats.map(s => s.percentage);

            const options = {
                series: [{ name: 'Presence %', data: percentages }],
                chart: {
                    type: 'bar',
                    height: 250,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif'
                },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        horizontal: true,
                        barHeight: '50%'
                    }
                },
                colors: ['#3b82f6'],
                dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, formatter: function (val, opt) { return val + "%" } },
                xaxis: { categories: categories, max: 100 },
                grid: { show: false }
            };

            if (deptChartInstance) {
                deptChartInstance.updateSeries([{ name: 'Presence %', data: percentages }]);
            } else {
                deptChartInstance = new ApexCharts(document.querySelector("#deptChart"), options);
                deptChartInstance.render();
            }
        }

        function updateRecentActivity(punches) {
            const container = document.getElementById('recent-activity-list');
            if (!punches || punches.length === 0) {
                container.innerHTML = '<p class="text-slate-400 text-sm italic">No recent activity</p>';
                return;
            }

            container.innerHTML = punches.map(punch => `
                    <div class="flex items-start gap-4 p-3 hover:bg-slate-50 rounded-xl transition-colors">
                        <div class="relative flex-shrink-0">
                            ${punch.image
                    ? `<img src="${punch.image}" class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm cursor-pointer hover:scale-110 transition-transform" onclick="window.open('${punch.image}', '_blank')">`
                    : `<div class="bg-slate-100 rounded-full w-12 h-12 flex items-center justify-center border-2 border-white shadow-sm">
                                    <span class="text-slate-500 font-bold text-sm">${punch.emp_name.charAt(0)}</span>
                                   </div>`
                }
                            ${punch.is_mobile
                    ? `<div class="absolute -bottom-1 -right-1 bg-blue-500 text-white p-1 rounded-full border-2 border-white" title="Mobile Punch">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                   </div>`
                    : ''
                }
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-bold text-slate-800 truncate">${punch.emp_name}</p>
                                    <p class="text-xs text-slate-500 truncate font-mono">${punch.emp_code}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-slate-700 font-mono">${punch.time}</p>
                                     <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full ${punch.direction === 'IN' ? 'bg-green-100 text-green-700' : (punch.direction === 'OUT' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600')}">
                                        ${punch.direction}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
        }
    </script>
@endsection