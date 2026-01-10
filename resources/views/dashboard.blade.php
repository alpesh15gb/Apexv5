@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h3 class="text-slate-800 text-3xl font-bold tracking-tight">Dashboard</h3>
        <p class="text-slate-500 mt-2 text-sm">Overview for {{ \Carbon\Carbon::now()->format('l, d M Y') }}</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Present -->
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 text-white shadow-lg shadow-green-200 transform transition hover:-translate-y-1 duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-green-100 font-medium text-sm">Present</p>
                    <h4 class="text-4xl font-bold mt-2" id="stat-present">-</h4>
                </div>
                <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
            </div>
        </div>

        <!-- Absent -->
        <div class="bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl p-6 text-white shadow-lg shadow-red-200 transform transition hover:-translate-y-1 duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-red-100 font-medium text-sm">Absent</p>
                    <h4 class="text-4xl font-bold mt-2" id="stat-absent">-</h4>
                </div>
                <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </div>
            </div>
        </div>

        <!-- Late -->
        <div class="bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl p-6 text-white shadow-lg shadow-orange-200 transform transition hover:-translate-y-1 duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-amber-100 font-medium text-sm">Late Arrivals</p>
                    <h4 class="text-4xl font-bold mt-2" id="stat-late">-</h4>
                </div>
                <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
        </div>

        <!-- Total Staff -->
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col justify-center items-center">
            <p class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Staff</p>
            <h4 class="text-3xl font-bold text-slate-700 mt-2" id="stat-total">-</h4>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Chart -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <h4 class="text-lg font-bold text-slate-800 mb-6">Today's Attendance</h4>
            <div id="attendanceChart" class="w-full flex items-center justify-center min-h-[300px]"></div>
        </div>

        <!-- Right Column: Recent Activity -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <h4 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-sky-500"></span>
                </span>
                Live Feed
            </h4>
            
            <div class="space-y-6" id="recent-activity-list">
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

                    // Render Chart
                    renderChart(data);
                })
                .catch(error => console.error('Error fetching stats:', error));
        }

        let chartInstance = null;

        function renderChart(data) {
            const options = {
                series: [data.present || 0, data.absent || 0, data.late || 0],
                chart: {
                    type: 'donut',
                    height: 350,
                    fontFamily: 'Inter, sans-serif',
                    animations: { enabled: true }
                },
                labels: ['Present', 'Absent', 'Late'],
                colors: ['#10b981', '#f43f5e', '#f59e0b'], 
                plotOptions: {
                    pie: {
                        donut: {
                            size: '75%',
                            labels: {
                                show: true,
                                name: { show: true, fontSize: '14px', fontFamily: 'Inter, sans-serif', color: '#64748b' },
                                value: { show: true, fontSize: '30px', fontFamily: 'Inter, sans-serif', fontWeight: 700, color: '#334155' },
                                total: {
                                    show: true,
                                    label: 'Checked In',
                                    color: '#64748b',
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

            if(chartInstance) {
                chartInstance.updateSeries([data.present || 0, data.absent || 0, data.late || 0]);
            } else {
                chartInstance = new ApexCharts(document.querySelector("#attendanceChart"), options);
                chartInstance.render();
            }
        }

        function updateRecentActivity(punches) {
            const container = document.getElementById('recent-activity-list');
            if(!punches || punches.length === 0) {
                container.innerHTML = '<p class="text-slate-400 text-sm italic">No recent activity</p>';
                return;
            }

            container.innerHTML = punches.map(punch => `
                <div class="flex items-start gap-3">
                    <div class="bg-slate-100 rounded-full p-2 flex-shrink-0">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-700 truncate">${punch.emp_name}</p>
                        <p class="text-xs text-slate-500 truncate">ID: ${punch.emp_code}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-mono font-bold text-slate-700">${punch.time}</p>
                        <span class="text-[10px] uppercase font-bold tracking-wider ${punch.direction === 'IN' ? 'text-green-500' : 'text-slate-400'}">
                            ${punch.direction || 'LOG'}
                        </span>
                    </div>
                </div>
            `).join('');
        }
    </script>
@endsection