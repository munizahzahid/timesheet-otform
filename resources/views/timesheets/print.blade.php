<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Timesheet — {{ $timesheet->user->name ?? 'Staff' }} — {{ DateTime::createFromFormat('!m', $timesheet->month)->format('F') }} {{ $timesheet->year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            line-height: 1.2;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .print-container {
            width: 277mm;
            max-height: 190mm;
            overflow: hidden;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 3mm;
            border-bottom: 1px solid #000;
            padding-bottom: 2mm;
        }
        .header-left { font-size: 9pt; font-weight: bold; }
        .header-right { text-align: right; font-size: 7pt; }
        .header-right div { margin-bottom: 1px; }

        /* Main table */
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }

        th, td {
            border: 0.5px solid #333;
            text-align: center;
            vertical-align: middle;
            padding: 0.5mm 0.3mm;
            font-size: 6pt;
            overflow: hidden;
            white-space: nowrap;
        }

        /* First column (item label) — wider */
        th:first-child, td:first-child {
            min-width: 32mm;
            max-width: 32mm;
            text-align: left;
            padding-left: 1mm;
            font-size: 5.5pt;
        }

        /* Second column (type) */
        th:nth-child(2), td:nth-child(2) {
            min-width: 10mm;
            max-width: 10mm;
            font-size: 5pt;
        }

        /* Day columns */
        th.day-col, td.day-col {
            min-width: 6.5mm;
            max-width: 6.5mm;
        }

        /* Total column */
        th:last-child, td:last-child {
            min-width: 10mm;
            max-width: 10mm;
            font-weight: bold;
        }

        /* Color coding */
        .bg-yellow { background-color: #fef9c3 !important; }
        .bg-red { background-color: #fee2e2 !important; }
        .bg-orange { background-color: #ffedd5 !important; }
        .bg-admin-total { background-color: #e5e7eb !important; font-weight: bold; }
        .bg-ext-total { background-color: #e0f2fe !important; font-weight: bold; }
        .bg-working-total { background-color: #dcfce7 !important; font-weight: bold; }
        .bg-available { background-color: #f3f4f6 !important; }
        .bg-overtime { background-color: #e0f2fe !important; font-weight: bold; }

        .section-spacer td { height: 1mm; border: none; background: #e5e7eb; }

        /* Header rows */
        thead th { background-color: #f3f4f6; font-weight: bold; font-size: 6pt; }

        /* Signatures */
        .signatures {
            margin-top: 5mm;
            display: flex;
            justify-content: space-between;
            padding: 0 5mm;
        }
        .sig-block {
            text-align: center;
            width: 30%;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 15mm;
            padding-top: 1mm;
            font-size: 7pt;
        }
        .sig-label { font-size: 6pt; color: #666; }

        /* Print button */
        .no-print { text-align: center; margin: 10px 0; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 8px 24px; font-size: 14px; cursor: pointer; background: #4f46e5; color: #fff; border: none; border-radius: 6px;">
            🖨️ Print Timesheet
        </button>
        <a href="{{ route('timesheets.edit', $timesheet) }}" style="margin-left: 10px; font-size: 14px; color: #4f46e5;">← Back to Edit</a>
    </div>

    <div class="print-container">
        {{-- HEADER --}}
        <div class="header">
            <div class="header-left">
                TIMESHEET<br>
                <span style="font-size: 7pt; font-weight: normal;">
                    {{ DateTime::createFromFormat('!m', $timesheet->month)->format('F') }} {{ $timesheet->year }}
                </span>
            </div>
            <div class="header-right">
                <div><strong>Name:</strong> {{ $timesheet->user->name ?? '—' }}</div>
                <div><strong>Dept:</strong> {{ $timesheet->user->department->name ?? '—' }}</div>
                <div><strong>Emp Code:</strong> {{ $timesheet->user->employee_code ?? '—' }}</div>
            </div>
        </div>

        {{-- MAIN TABLE --}}
        <table>
            <thead>
                {{-- Day number row --}}
                <tr>
                    <th>ITEM</th>
                    <th>TYPE</th>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php $day = $days[$d]; @endphp
                        <th class="day-col {{ $day['day_type'] === 'off_day' ? 'bg-yellow' : '' }}{{ $day['day_type'] === 'public_holiday' ? 'bg-red' : '' }}{{ $day['day_type'] === 'mc' ? 'bg-orange' : '' }}">
                            {{ $d }}
                        </th>
                    @endfor
                    <th>TOTAL</th>
                </tr>
                {{-- Day of week row --}}
                <tr>
                    <th></th>
                    <th></th>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php $day = $days[$d]; @endphp
                        <th class="day-col {{ $day['day_type'] === 'off_day' ? 'bg-yellow' : '' }}{{ $day['day_type'] === 'public_holiday' ? 'bg-red' : '' }}{{ $day['day_type'] === 'mc' ? 'bg-orange' : '' }}" style="font-weight: normal; font-size: 5pt;">
                            {{ $day['day_of_week'] }}
                        </th>
                    @endfor
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {{-- ===== UPPER TABLE: Admin Job Rows ===== --}}
                @php $rowNum = 0; @endphp
                @foreach($adminTypes as $type => $label)
                    @php $rowNum++; @endphp
                    <tr>
                        <td>{{ $rowNum }}. {{ $label }}</td>
                        <td style="font-size: 5pt;">hrs</td>
                        @php $rowTotal = 0; @endphp
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $day = $days[$d];
                                $val = $adminData[$type][$d] ?? 0;
                                $rowTotal += $val;
                                $bgClass = '';
                                if ($day['day_type'] === 'off_day') $bgClass = 'bg-yellow';
                                elseif ($day['day_type'] === 'public_holiday') $bgClass = 'bg-red';
                                elseif ($day['day_type'] === 'mc') $bgClass = 'bg-orange';
                            @endphp
                            <td class="day-col {{ $bgClass }}">{{ $val > 0 ? $val : '' }}</td>
                        @endfor
                        <td>{{ $rowTotal > 0 ? round($rowTotal, 1) : '' }}</td>
                    </tr>
                @endforeach

                {{-- TOTAL ADMIN JOB --}}
                <tr class="bg-admin-total">
                    <td style="font-weight: bold;">TOTAL ADMIN JOB</td>
                    <td></td>
                    @php $grandAdmin = 0; @endphp
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $dayTotal = 0;
                            foreach ($adminTypes as $type => $label) {
                                $dayTotal += $adminData[$type][$d] ?? 0;
                            }
                            $grandAdmin += $dayTotal;
                        @endphp
                        <td class="day-col bg-admin-total">{{ round($dayTotal, 1) }}</td>
                    @endfor
                    <td class="bg-admin-total">{{ round($grandAdmin, 1) }}</td>
                </tr>

                {{-- Spacer --}}
                <tr class="section-spacer"><td colspan="{{ $daysInMonth + 3 }}"></td></tr>

                {{-- ===== LOWER TABLE: Project Rows ===== --}}
                @php $subRowLabels = ['NORM/NC', 'NORM/COBQ', 'OT/NC', 'OT/COBQ']; @endphp
                @php $subRowFields = ['normal_nc', 'normal_cobq', 'ot_nc', 'ot_cobq']; @endphp

                @foreach($projectRowsData as $pIdx => $proj)
                    @foreach($subRowLabels as $sIdx => $sLabel)
                        <tr{{ $sIdx === 0 ? ' style="border-top: 1.5px solid #000;"' : '' }}>
                            @if($sIdx === 0)
                                <td rowspan="4" style="vertical-align: top; text-align: left; font-size: 5.5pt;">
                                    <strong>#{{ $pIdx + 1 }}</strong>
                                    @if($proj['project_code'])
                                        {{ $proj['project_code'] }}<br>
                                    @endif
                                    <span style="font-size: 5pt; color: #666;">{{ $proj['project_name'] }}</span>
                                </td>
                            @endif
                            <td style="font-size: 5pt;">{{ $sLabel }}</td>
                            @php $subTotal = 0; @endphp
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php
                                    $day = $days[$d];
                                    $val = $proj['hours'][$d][$subRowFields[$sIdx]] ?? 0;
                                    $subTotal += $val;
                                    $bgClass = '';
                                    if ($day['day_type'] === 'off_day') $bgClass = 'bg-yellow';
                                    elseif ($day['day_type'] === 'public_holiday') $bgClass = 'bg-red';
                                @endphp
                                <td class="day-col {{ $bgClass }}">{{ $val > 0 ? $val : '' }}</td>
                            @endfor
                            <td>{{ $subTotal > 0 ? round($subTotal, 1) : '' }}</td>
                        </tr>
                    @endforeach
                @endforeach

                {{-- Spacer --}}
                <tr class="section-spacer"><td colspan="{{ $daysInMonth + 3 }}"></td></tr>

                {{-- ===== SUMMARY ROWS ===== --}}

                {{-- TOTAL EXTERNAL PROJECT --}}
                <tr class="bg-ext-total">
                    <td style="font-weight: bold;">TOTAL EXTERNAL PROJECT</td>
                    <td></td>
                    @php $grandExt = 0; @endphp
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $extTotal = 0;
                            foreach ($projectRowsData as $proj) {
                                $h = $proj['hours'][$d] ?? [];
                                $extTotal += ($h['normal_nc'] ?? 0) + ($h['normal_cobq'] ?? 0) + ($h['ot_nc'] ?? 0) + ($h['ot_cobq'] ?? 0);
                            }
                            $grandExt += $extTotal;
                        @endphp
                        <td class="day-col bg-ext-total">{{ $extTotal > 0 ? round($extTotal, 1) : '' }}</td>
                    @endfor
                    <td class="bg-ext-total">{{ $grandExt > 0 ? round($grandExt, 1) : '' }}</td>
                </tr>

                {{-- TOTAL WORKING HOURS --}}
                <tr class="bg-working-total">
                    <td style="font-weight: bold;">TOTAL WORKING HOURS</td>
                    <td></td>
                    @php $grandWorking = 0; @endphp
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $adminDayTotal = 0;
                            foreach ($adminTypes as $type => $label) {
                                $adminDayTotal += $adminData[$type][$d] ?? 0;
                            }
                            $extDayTotal = 0;
                            foreach ($projectRowsData as $proj) {
                                $h = $proj['hours'][$d] ?? [];
                                $extDayTotal += ($h['normal_nc'] ?? 0) + ($h['normal_cobq'] ?? 0) + ($h['ot_nc'] ?? 0) + ($h['ot_cobq'] ?? 0);
                            }
                            $workingTotal = $adminDayTotal + $extDayTotal;
                            $grandWorking += $workingTotal;
                        @endphp
                        <td class="day-col bg-working-total">{{ $workingTotal > 0 ? round($workingTotal, 1) : '' }}</td>
                    @endfor
                    <td class="bg-working-total">{{ $grandWorking > 0 ? round($grandWorking, 1) : '' }}</td>
                </tr>

                {{-- HOURS AVAILABLE --}}
                <tr class="bg-available">
                    <td style="font-weight: bold;">HOURS AVAILABLE</td>
                    <td></td>
                    @php $totalAvail = 0; @endphp
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $avail = $days[$d]['available_hours'];
                            $totalAvail += $avail;
                        @endphp
                        <td class="day-col bg-available">{{ $avail > 0 ? $avail : '' }}</td>
                    @endfor
                    <td class="bg-available">{{ $totalAvail }}</td>
                </tr>

                {{-- OVERTIME --}}
                <tr class="bg-overtime">
                    <td style="font-weight: bold;">OVERTIME</td>
                    <td></td>
                    @php $grandOt = 0; @endphp
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $adminDayTotal = 0;
                            foreach ($adminTypes as $type => $label) {
                                $adminDayTotal += $adminData[$type][$d] ?? 0;
                            }
                            $extDayTotal = 0;
                            foreach ($projectRowsData as $proj) {
                                $h = $proj['hours'][$d] ?? [];
                                $extDayTotal += ($h['normal_nc'] ?? 0) + ($h['normal_cobq'] ?? 0) + ($h['ot_nc'] ?? 0) + ($h['ot_cobq'] ?? 0);
                            }
                            $workingTotal = $adminDayTotal + $extDayTotal;
                            $avail = $days[$d]['available_hours'];
                            $ot = $workingTotal - $avail;
                            if ($ot < 0) $ot = 0;
                            $grandOt += $ot;
                        @endphp
                        <td class="day-col bg-overtime">{{ $ot > 0 ? round($ot, 1) : '' }}</td>
                    @endfor
                    <td class="bg-overtime">{{ $grandOt > 0 ? round($grandOt, 1) : '' }}</td>
                </tr>
            </tbody>
        </table>

        {{-- SIGNATURES --}}
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-line">PREPARED BY (Staff)</div>
                <div class="sig-label">{{ $timesheet->user->name ?? '' }}</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">CHECKED BY (Asst. Manager)</div>
                <div class="sig-label">&nbsp;</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">APPROVED BY (Manager / HOD)</div>
                <div class="sig-label">&nbsp;</div>
            </div>
        </div>
    </div>
</body>
</html>
