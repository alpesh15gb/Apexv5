@extends('layouts.app')

@section('content')
    <!-- Welcome Banner with Glassmorphism -->
    <div
        class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-500 to-purple-600 dark:from-indigo-600 dark:to-purple-800 p-8 mb-10 shadow-xl shadow-indigo-200 dark:shadow-none">
        <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-white opacity-10 blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -ml-16 -mb-16 w-48 h-48 rounded-full bg-white opacity-10 blur-2xl"></div>

        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-6">
            <div>
                <h2 class="text-3xl font-bold text-white tracking-tight">
                    Good Morning, Admin! 👋
                </h2>
                <p class="text-indigo-100 mt-2 text-lg">
                    Here's what's happening with your workforce today.
                </p>
                <div class="mt-6 flex gap-3">
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white/20 text-white backdrop-blur-sm border border-white/10">
                        {{ \Carbon\Carbon::now()->format('l, d M Y') }}
                    </span>
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-100 backdrop-blur-sm border border-emerald-500/20">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                clip-rule="evenodd"></path>
                        </svg>
                        System Active
                    </span>
                </div>
            </div>
            <div class="hidden md:block">
                <img src="https://cdni.iconscout.com/illustration/premium/thumb/business-team-looking-for-new-people-illustration-download-in-svg-png-gif-file-formats--hiring-employee-recruitment-job-search-human-resources-pack-illustrations-3774618.png"
                    class="h-40 w-auto opacity-90 mix-blend-overlay filter drop-shadow-xl" alt="Dashboard Illustration">
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <!-- Present -->
        <a href="{{ route('reports.daily', ['status' => 'Present']) }}"
            class="group relative bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700/60 hover:shadow-md hover:-translate-y-1 transition-all duration-300">
            <div class="absolute top-4 right-4 p-2 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl">
                <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Present Today</p>
            <h4 class="text-3xl font-bold text-slate-800 dark:text-white mt-2" id="stat-present">-</h4>
            <div class="mt-4 flex items-center text-xs font-medium text-emerald-500">
                <span class="flex items-center bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded-md">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    On Time
                </span>
            </div>
        </a>

        <!-- Absent -->
        <a href="{{ route('reports.daily', ['status' => 'Absent']) }}"
            class="group relative bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700/60 hover:shadow-md hover:-translate-y-1 transition-all duration-300">
            <div class="absolute top-4 right-4 p-2 bg-rose-50 dark:bg-rose-500/10 rounded-xl">
                <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Absent</p>
            <h4 class="text-3xl font-bold text-slate-800 dark:text-white mt-2" id="stat-absent">-</h4>
            <div class="mt-4 flex items-center text-xs font-medium text-rose-500">
                <span class="flex items-center bg-rose-50 dark:bg-rose-500/10 px-2 py-0.5 rounded-md">
                    Action Required
                </span>
            </div>
        </a>

        <!-- Late -->
        <a href="{{ route('reports.daily', ['status' => 'Late']) }}"
            class="group relative bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700/60 hover:shadow-md hover:-translate-y-1 transition-all duration-300">
            <div class="absolute top-4 right-4 p-2 bg-amber-50 dark:bg-amber-500/10 rounded-xl">
                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Late Arrivals</p>
            <h4 class="text-3xl font-bold text-slate-800 dark:text-white mt-2" id="stat-late">-</h4>
            <div class="mt-4 flex items-center text-xs font-medium text-amber-500">
                <span class="flex items-center bg-amber-50 dark:bg-amber-500/10 px-2 py-0.5 rounded-md">
                    Review
                </span>
            </div>
        </a>

        <!-- Total Staff -->
        <div
            class="group relative bg-indigo-900 rounded-2xl p-6 shadow-lg shadow-indigo-200 dark:shadow-none hover:-translate-y-1 transition-all duration-300 overflow-hidden">
            <div class="absolute -right-6 -top-6 w-32 h-32 rounded-full bg-indigo-800 opacity-50"></div>
            <p class="text-indigo-200 text-sm font-medium relative z-10">Total Workforce</p>
            <h4 class="text-3xl font-bold text-white mt-2 relative z-10" id="stat-total">-</h4>
            <div class="mt-4 flex items-center text-xs font-medium text-indigo-200 relative z-10">
                <span class="flex items-center bg-indigo-800 px-2 py-0.5 rounded-md">
                    Active Employees
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Charts -->
        <div class="lg:col-span-2 space-y-8">

            <!-- Weekly Chart -->
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/60 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="text-lg font-bold text-slate-800 dark:text-white">Weekly Trends</h4>
                    <select
                        class="text-xs bg-slate-50 dark:bg-slate-700 border-none rounded-lg text-slate-500 dark:text-slate-300 focus:ring-0 cursor-pointer">
                        <option>This Week</option>
                        <option>Last Week</option>
                    </select>
                </div>
                <div id="weeklyChart" class="w-full h-80"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Today's Pie Chart -->
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/60 p-6">
                    <h4 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Today's Composition</h4>
                    <div id="attendanceChart" class="w-full flex items-center justify-center min-h-[250px]"></div>
                </div>

                <!-- Department Stats -->
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/60 p-6">
                    <h4 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Top Departments</h4>
                    <div id="deptChart" class="w-full h-64"></div>
                </div>
            </div>

            <!-- Location Stats Grid -->
            <div id="location-stats-container" style="display: none;">
                <h4 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Location Overview</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="location-cards">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- Right Column: Recent Activity Timeline -->
        <div class="lg:col-span-1">
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/60 p-6 h-fit sticky top-24">
                <div class="flex items-center justify-between mb-8">
                    <h4 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        Live Feed
                        <span class="relative flex h-2 w-2">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                    </h4>
                    <button class="text-xs text-indigo-500 hover:text-indigo-600 font-medium">View All</button>
                </div>

                <div class="space-y-0 relative border-l-2 border-slate-100 dark:border-slate-700 ml-3"
                    id="recent-activity-list">
                    <!-- Javascript will populate this -->
                    <div class="pl-6 pb-8 relative">
                        <div
                            class="absolute -left-[9px] top-0 h-4 w-4 rounded-full bg-slate-200 dark:bg-slate-700 border-2 border-white dark:border-slate-800">
                        </div>
                        <div class="animate-pulse space-y-3">
                            <div class="h-2 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
                            <div class="h-2 bg-slate-200 dark:bg-slate-700 rounded w-2/3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Initial Fetch
            fetchStats();
            // Refresh every 30 seconds
            setInterval(fetchStats, 30000);

            // Listen for Dark Mode Toggle for Chart Updates
            window.addEventListener('storage', (e) => {
                if (e.key === 'darkMode') {
                    const isDark = e.newValue === 'true';
                    updateChartsTheme(isDark);
                }
            });
            // Also watch for class changes on html element if using the toggle in same window
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === "class") {
                        const isDark = document.documentElement.classList.contains('dark');
                        updateChartsTheme(isDark);
                    }
                });
            });
            observer.observe(document.documentElement, { attributes: true });
        });

        // Determine initial theme
        const getMode = () => document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        const getChartColors = (isDark) => ({
            text: isDark ? '#94a3b8' : '#64748b',
            grid: isDark ? '#334155' : '#f1f5f9'
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
                    renderLocationCards(data.location_stats);
                })
                .catch(error => console.error('Error fetching stats:', error));
        }

        let chartInstance = null;
        let weeklyChartInstance = null;
        let deptChartInstance = null;

        function updateChartsTheme(isDark) {
            const theme = isDark ? 'dark' : 'light';
            const colors = getChartColors(isDark);

            if (chartInstance) chartInstance.updateOptions({ theme: { mode: theme } });
            if (weeklyChartInstance) weeklyChartInstance.updateOptions({
                theme: { mode: theme },
                xaxis: { labels: { style: { colors: colors.text } } },
                yaxis: { labels: { style: { colors: colors.text } } },
                grid: { borderColor: colors.grid }
            });
            if (deptChartInstance) deptChartInstance.updateOptions({
                theme: { mode: theme },
                xaxis: { labels: { style: { colors: colors.text } } },
                yaxis: { labels: { style: { colors: colors.text } } }
            });
        }

        function renderDonutChart(data) {
            const isDark = getMode() === 'dark';
            const options = {
                series: [data.present || 0, data.absent || 0, data.late || 0],
                chart: {
                    type: 'donut',
                    height: 280,
                    fontFamily: 'Inter, sans-serif',
                    animations: { enabled: true },
                    background: 'transparent'
                },
                theme: { mode: isDark ? 'dark' : 'light' },
                labels: ['Present', 'Absent', 'Late'],
                colors: ['#10b981', '#f43f5e', '#f59e0b'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '75%',
                            labels: {
                                show: true,
                                name: { show: true, color: isDark ? '#94a3b8' : '#64748b' },
                                value: { show: true, fontSize: '24px', fontWeight: 700, color: isDark ? '#f1f5f9' : '#1e293b' },
                                total: {
                                    show: true,
                                    label: 'Checked In',
                                    color: isDark ? '#94a3b8' : '#64748b',
                                    formatter: function (w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { position: 'bottom', fontFamily: 'Inter, sans-serif', labels: { colors: isDark ? '#cbd5e1' : '#475569' } },
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

            const isDark = getMode() === 'dark';
            const colors = getChartColors(isDark);

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
                    fontFamily: 'Inter, sans-serif',
                    background: 'transparent'
                },
                theme: { mode: isDark ? 'dark' : 'light' },
                colors: ['#10b981', '#f43f5e', '#f59e0b'],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        borderRadius: 4,
                        columnWidth: '30%',
                        borderRadiusApplication: 'end'
                    },
                },
                xaxis: {
                    categories: categories,
                    labels: { style: { colors: colors.text, fontSize: '12px' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: { style: { colors: colors.text } },
                },
                grid: {
                    borderColor: colors.grid,
                    strokeDashArray: 4,
                },
                legend: { position: 'top', labels: { colors: isDark ? '#cbd5e1' : '#475569' } },
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

            const isDark = getMode() === 'dark';
            const colors = getChartColors(isDark);

            const categories = stats.map(s => s.name);
            const percentages = stats.map(s => s.percentage);

            const options = {
                series: [{ name: 'Presence %', data: percentages }],
                chart: {
                    type: 'bar',
                    height: 250,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                    background: 'transparent'
                },
                theme: { mode: isDark ? 'dark' : 'light' },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        horizontal: true,
                        barHeight: '40%',
                        borderRadiusApplication: 'end'
                    }
                },
                colors: ['#6366f1'],
                dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, formatter: function (val, opt) { return val + "%" } },
                xaxis: {
                    categories: categories,
                    max: 100,
                    labels: { style: { colors: colors.text } },
                    axisBorder: { show: false }
                },
                yaxis: {
                    labels: { style: { colors: colors.text, fontSize: '13px', fontWeight: 500 } }
                },
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
                container.innerHTML = '<div class="pl-6"><p class="text-slate-400 text-sm italic">No recent activity</p></div>';
                return;
            }

            container.innerHTML = punches.map((punch, index) => `
                    <div class="relative pl-6 pb-8 group last:pb-0">
                        <!-- Timeline Line -->
                        <div class="absolute -left-[1px] top-0 bottom-0 w-0.5 bg-slate-100 dark:bg-slate-700 group-last:bg-transparent"></div>

                        <!-- Timeline Dot -->
                         <div class="absolute -left-[9px] top-0 h-5 w-5 rounded-full border-4 border-white dark:border-slate-800 ${punch.direction === 'IN' ? 'bg-emerald-500' : (punch.direction === 'OUT' ? 'bg-rose-500' : 'bg-slate-400')} shadow-sm"></div>

                        <div class="flex items-start gap-4">
                            <div class="relative flex-shrink-0 mt-1">
                                 ${punch.image
                    ? `<img src="${punch.image}" class="w-10 h-10 rounded-full object-cover ring-2 ring-white dark:ring-slate-700 shadow-sm cursor-pointer hover:scale-110 transition-transform" onclick="window.open('${punch.image}', '_blank')">`
                    : `<div class="bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-600 rounded-full w-10 h-10 flex items-center justify-center ring-2 ring-white dark:ring-slate-700 shadow-sm">
                                        <span class="text-slate-500 dark:text-slate-300 font-bold text-xs">${punch.emp_name.charAt(0)}</span>
                                       </div>`
                }
                            </div>
                            <div class="flex-1 min-w-0 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                <div class="flex justify-between items-start mb-1">
                                    <p class="text-sm font-bold text-slate-800 dark:text-slate-200 truncate">${punch.emp_name}</p>
                                    <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full ${punch.direction === 'IN' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : (punch.direction === 'OUT' ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400')}">
                                        ${punch.direction}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate font-mono">${punch.emp_code}</p>
                                    <p class="text-xs font-bold text-slate-600 dark:text-slate-300 font-mono">${punch.time}</p>
                                </div>
                                 ${punch.is_mobile
                    ? `<div class="mt-2 flex items-center text-[10px] text-blue-500 font-medium">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        Mobile Punch
                                       </div>`
                    : ''
                }
                            </div>
                        </div>
                    </div>
                `).join('');
        }

        function renderLocationCards(stats) {
            const container = document.getElementById('location-cards');
            const wrapper = document.getElementById('location-stats-container');

            if (!stats || stats.length === 0) {
                wrapper.style.display = 'none';
                return;
            }

            wrapper.style.display = 'block';
            container.innerHTML = stats.map(loc => `
                        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-100 dark:border-slate-700/60 shadow-sm hover:shadow-md transition">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h4 class="text-lg font-bold text-slate-800 dark:text-white">${loc.name}</h4>
                                    <p class="text-slate-500 dark:text-slate-400 text-xs">Total Staff: <span class="font-bold text-slate-700 dark:text-slate-300">${loc.total}</span></p>
                                </div>
                                <div class="bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 px-2 py-1 rounded text-xs font-bold">
                                    ${loc.percentage}% Present
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-4">
                                 <div class="text-center p-3 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl">
                                    <p class="text-emerald-600 dark:text-emerald-400 text-[10px] font-bold uppercase tracking-wider">Present</p>
                                    <h5 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mt-1">${loc.present}</h5>
                                </div>
                                <div class="text-center p-3 bg-rose-50 dark:bg-rose-500/10 rounded-xl">
                                    <p class="text-rose-600 dark:text-rose-400 text-[10px] font-bold uppercase tracking-wider">Absent</p>
                                    <h5 class="text-xl font-bold text-rose-700 dark:text-rose-400 mt-1">${loc.absent}</h5>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-50 dark:border-slate-700/50">
                                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-indigo-500 h-1.5 rounded-full" style="width: ${loc.percentage}%"></div>
                                </div>
                            </div>
                        </div>
                    `).join('');
        }
    </script>
@endsection