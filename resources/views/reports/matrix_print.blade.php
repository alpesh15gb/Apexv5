<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrix Report - {{ $monthName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        apex: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 900: '#0c4a6e' }
                    }
                }
            }
        }
    </script>
    <style>
        @media print {
            @page {
                size: landscape;
                margin: 5mm;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                /* Prevent splitting updated rows */
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }
        }

        .print-tiny {
            font-size: 9px;
            line-height: 1.1;
        }

        .rotated-header {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
        }
    </style>
</head>

<body class="bg-white text-gray-800 p-4" onload="window.print()">

    <!-- Header -->
    <div class="flex justify-between items-end mb-4 border-b pb-2">
        <div class="flex items-center gap-4">
            {{-- <img src="/logo.png" class="h-10" alt="Logo"> --}}
            <div>
                <h1 class="text-xl font-bold uppercase tracking-wide text-gray-900">Apex Human Capital</h1>
                <h2 class="text-sm font-medium text-gray-600">Monthly Assessment Matrix - {{ $monthName }}</h2>
            </div>
        </div>
        <div class="text-right text-xs text-gray-500">
            <p>Generated on: {{ now()->format('Y-m-d H:i') }}</p>
            <p class="no-print text-red-500 font-bold mt-1">Right Click -> Save as PDF</p>
        </div>
    </div>

    @php
        $daysInMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $metrics = [
            'status' => 'Status',
            'in_time' => 'In',
            'out_time' => 'Out',
            'duration' => 'Dur',
            'late_by' => 'Late',
            'early_by' => 'Early',
            'ot' => 'OT'
        ];
    @endphp

    <table class="w-full border-collapse border border-gray-300 print-tiny table-fixed" style="width: 100%">
        <thead>
            <tr class="bg-gray-100/50">
                <th class="border border-gray-300 p-1 w-12 text-left text-[8px]">Code</th>
                <th class="border border-gray-300 p-1 w-24 text-left text-[8px]">Employee Name</th>
                <th class="border border-gray-300 p-1 w-10 text-left text-[8px]">Dept</th>
                <th class="border border-gray-300 p-1 w-8 text-center bg-gray-50 text-[8px]">Metric</th>
                @for($i = 1; $i <= $daysInMonth; $i++)
                    <th class="border border-gray-300 p-0 text-center bg-gray-50">
                        <div class="font-bold border-b border-gray-200 text-[8px]">{{ $i }}</div>
                        <div class="font-normal text-[7px]">
                            {{ \Carbon\Carbon::createFromDate($year, $month, $i)->format('D') }}</div>
                    </th>
                @endfor
                <th class="border border-gray-300 p-1 w-20 bg-gray-50 text-[8px]">Summary</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                @php $rowSpan = count($metrics); @endphp
                <!-- Employee Row Group -->
                @foreach($metrics as $key => $label)
                    <tr class="{{ $key === 'status' ? 'border-t-2 border-gray-400' : '' }}">
                        @if($loop->first)
                            <td rowspan="{{ $rowSpan }}"
                                class="border border-gray-300 p-1 align-top bg-gray-50/30 font-bold font-mono">
                                {{ $row['employee']->device_emp_code }}
                            </td>
                            <td rowspan="{{ $rowSpan }}" class="border border-gray-300 p-1 align-top font-bold">
                                {{ $row['employee']->name }}
                                <div class="font-normal text-[8px] text-gray-500 mt-1">{{ $row['employee']->shift->name ?? '' }}
                                </div>
                            </td>
                            <td rowspan="{{ $rowSpan }}" class="border border-gray-300 p-1 align-top">
                                {{ substr($row['employee']->department->name ?? '', 0, 15) }}
                            </td>
                        @endif

                        <!-- Metric Label -->
                        <td
                            class="border border-gray-300 p-0.5 text-center font-semibold text-gray-500 bg-gray-50/50 text-[8px]">
                            {{ $label }}
                        </td>

                        <!-- Days -->
                        @for($i = 1; $i <= $daysInMonth; $i++)
                            @php 
                                                                        $dayData = $row['days'][$i] ?? null;
                                $val = $dayData[$key] ?? '-';
                                $bgClass = '';
                                $textClass = '';

                                if ($key === 'status') {
                                    if ($val === 'P') {
                                        $bgClass = '!bg-green-100';
                                        $textClass = 'text-green-800 font-bold';
                                    } elseif ($val === 'A') {
                                        $bgClass = '!bg-red-50';
                                        $textClass = 'text-red-600';
                                    } elseif ($val === 'H') {
                                        $bgClass = '!bg-blue-50';
                                        $textClass = 'text-blue-800';
                                    } elseif ($val === 'WO') {
                                        $bgClass = '!bg-gray-100';
                                    }
                                } elseif ($key === 'late_by' && $val !== '-' && $val > 0) {
                                    $textClass = 'text-red-600 font-bold';
                                } elseif ($key === 'early_by' && $val !== '-' && $val > 0) {
                                    $textClass = 'text-orange-600 font-bold';
                                }
                            @endphp

                                 <td class="border border-gray-300 p-0 text-center whitespace-nowrap {{ $bgClass }} {{ $textClass }}">
                                            {{ $val === 0 ? '-' : $val }}
                                        </td>
                        @endfor
                                <!-- Summary Column (Only on first row for clean look, or split?) -->
                                    @if($loop->first)
                                        <td rowspan="{{ $rowSpan }}" class="border border-gray-300 p-1 align-top bg-yellow-50/30 text-[8px]">
                                            <div class="grid grid-cols-2 gap-x-1">
                                                <span>Pres:</span> <b>{{ $row['summary']['present'] }}</b>

                                         <span>Abs:</span> <b>{{ $row['summary']['absent'] }}</b>
                                                <span>Late:</span> <b>{{ $row['summary']['late'] }}</b>
                                                <span>OT:</span> <b>{{ $row['summary']['total_ot'] }}</b>
                                                <span class="col-span-2 border-t mt-1 pt-1">
                                                    Dur: <b>{{ $row['summary']['total_duration'] }}</b>
                                                </span>
                                            </div>
                                        </td>
                                    @endif
                                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
