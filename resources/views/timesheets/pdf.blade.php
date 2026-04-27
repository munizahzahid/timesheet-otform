<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Timesheet — {{ $timesheet->user->name ?? 'Staff' }}</title>
    @php
        // ===== ABSOLUTE PT-BASED COLUMN WIDTHS =====
        // A4 landscape = 842pt. Body padding 10mm (28.35pt) each side → usable = 785pt
        $usablePt = 785;
        $noPt     = 4;     // NO column — minimum DomPDF supports
        $typePt   = 10;    // TIME/COST column — wider for separator
        $totalPt  = 10;    // TOTAL column
        $dayPt    = 3.5;   // Day column — minimum DomPDF supports
        $daysTotalPt = $daysInMonth * $dayPt;
        // Label gets ALL remaining space
        $labelPt  = $usablePt - $noPt - $typePt - $daysTotalPt - $totalPt;
        $tablePt  = $usablePt;
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6pt;
            line-height: 1.1;
            color: #000;
            padding: 12mm 10mm 5mm 10mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ===== TOP HEADER ===== */
        .top-header {
            display: table;
            width: 100%;
            border-bottom: 1.5pt solid #000;
            padding-bottom: 1mm;
            margin-bottom: 2mm;
        }
        .top-header-left {
            display: table-cell;
            vertical-align: top;
            width: 55%;
        }
        .top-header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 45%;
        }
        .company-name {
            font-size: 9pt;
            font-weight: bold;
            border: 1.5pt solid #000;
            display: inline-block;
            padding: 0.5mm 2mm;
            letter-spacing: 0.5pt;
        }
        .form-title {
            font-size: 8pt;
            font-weight: bold;
            margin-top: 1mm;
        }

        /* Approval boxes */
        .approval-boxes { display: table; border-collapse: collapse; }
        .approval-boxes td {
            border: 0.5pt solid #000;
            text-align: center;
            padding: 0.5mm 2mm;
            font-size: 5.5pt;
            min-width: 22mm;
            height: 12mm;
            vertical-align: top;
        }

        /* Staff info */
        .info-row { display: table; width: 100%; margin-bottom: 1.5mm; }
        .info-row .cell { display: table-cell; font-size: 7pt; }
        .info-row .cell b { font-weight: bold; }

        /* ===== MAIN TABLE ===== */
        table.main {
            border-collapse: collapse;
            width: {{ $tablePt }}pt;
            table-layout: fixed;
        }

        table.main th, table.main td {
            border: 0.5pt solid #000;
            text-align: center;
            vertical-align: middle;
            padding: 1px 0;
            font-size: 4pt;
            overflow: hidden;
        }

        /* Day cells — tiny, single-line, clipped */
        td.dc, th.dc {
            white-space: nowrap;
            overflow: hidden;
            font-size: 3.2pt;
        }

        /* Label cells — wrap text, uniform row height */
        td.lc, th.lc {
            text-align: left;
            padding-left: 1mm;
            white-space: normal;
            word-wrap: break-word;
            overflow: hidden;
            font-weight: bold;
            font-size: 3.5pt;
            line-height: 1.2;
            min-height: 8pt;
        }

        /* Admin rows — fixed height for uniformity */
        tbody tr {
            height: 10pt;
        }

        /* Header row styling */
        table.main thead th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 4.5pt;
        }

        /* Colors */
        .bg-yellow { background-color: #fff2cc !important; }
        .bg-red { background-color: #e06666 !important; color: #fff; }
        .bg-gray { background-color: #e8e8e8 !important; }
        .bg-admin-total { background-color: #d9d9d9 !important; font-weight: bold; }

        .bold { font-weight: bold; }
        .proj-top td { border-top: 1pt solid #000; }
        .summary-row td { font-weight: bold; }

        /* Footer */
        .footer { margin-top: 1mm; font-size: 5pt; line-height: 1.3; }
        .footer table { border-collapse: collapse; width: 100%; }
        .footer td { padding: 0.2mm 1mm; font-size: 5pt; border: none; vertical-align: top; }

        /* Stamps */
        .stamp-mini { display: inline-block; margin-top: 1mm; }
        .stamp-mini svg { width: 10mm; height: 10mm; }
        .stamp-name { font-size: 4pt; margin-top: 0.3mm; }
    </style>
</head>
<body>
    {{-- ===== TOP HEADER ===== --}}
    <div class="top-header">
        <div class="top-header-left">
            <span class="company-name">INGRESS</span>
            <span style="font-size: 7pt; font-weight: bold;"> TALENT SYNERGY</span><br>
            <span style="font-size: 6pt; font-weight: bold; padding-left: 2mm;">SDN BHD</span>
            <div class="form-title">DAILY TIME SHEET</div>
        </div>
        <div class="top-header-right">
            <table class="approval-boxes" style="margin-left: auto;">
                <tr>
                    <td>
                        <div style="font-weight: bold; font-size: 5pt;">PREPARED</div>
                        @if($approvalStamps[0]['status'] === 'approved')
                            <div class="stamp-mini">
                                <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="16" fill="none" stroke="#c00" stroke-width="1.5"/><circle cx="20" cy="20" r="13" fill="none" stroke="#c00" stroke-width="0.5"/><text x="20" y="17" text-anchor="middle" fill="#c00" font-size="4" font-weight="bold">TSSB</text><text x="20" y="23" text-anchor="middle" fill="#c00" font-size="4">{{ $approvalStamps[0]['date'] }}</text></svg>
                            </div>
                            <div class="stamp-name">{{ $approvalStamps[0]['name'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: bold; font-size: 5pt;">CHECKED</div>
                        @if($approvalStamps[1]['status'] === 'approved')
                            <div class="stamp-mini">
                                <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="16" fill="none" stroke="#c00" stroke-width="1.5"/><circle cx="20" cy="20" r="13" fill="none" stroke="#c00" stroke-width="0.5"/><text x="20" y="17" text-anchor="middle" fill="#c00" font-size="4" font-weight="bold">TSSB</text><text x="20" y="23" text-anchor="middle" fill="#c00" font-size="4">{{ $approvalStamps[1]['date'] }}</text></svg>
                            </div>
                            <div class="stamp-name">{{ $approvalStamps[1]['name'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: bold; font-size: 5pt;">APPROVED</div>
                        @if($approvalStamps[2]['status'] === 'approved')
                            <div class="stamp-mini">
                                <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="16" fill="none" stroke="#c00" stroke-width="1.5"/><circle cx="20" cy="20" r="13" fill="none" stroke="#c00" stroke-width="0.5"/><text x="20" y="17" text-anchor="middle" fill="#c00" font-size="4" font-weight="bold">TSSB</text><text x="20" y="23" text-anchor="middle" fill="#c00" font-size="4">{{ $approvalStamps[2]['date'] }}</text></svg>
                            </div>
                            <div class="stamp-name">{{ $approvalStamps[2]['name'] }}</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="height: auto; padding: 1px; font-size: 4pt;">SYED</td>
                    <td style="height: auto; padding: 1px; font-size: 4pt;">ASST. MNGR</td>
                    <td style="height: auto; padding: 1px; font-size: 4pt;">MNGR/HOD</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ===== STAFF INFO ===== --}}
    <div class="info-row">
        <div class="cell" style="width: 60%;">
            <b>MONTH :</b> {{ strtoupper(DateTime::createFromFormat('!m', $timesheet->month)->format('F')) }} {{ $timesheet->year }}<br>
            <b>NAME :</b> {{ strtoupper($timesheet->user->name ?? '—') }}
        </div>
        <div class="cell" style="width: 40%; text-align: center;">
            <b>EMP NO:</b> {{ $timesheet->user->staff_no ?? '—' }}
        </div>
    </div>

    {{-- ===== MAIN TABLE ===== --}}
    <table class="main">
        <colgroup>
            <col style="width: {{ $noPt }}pt;">
            <col style="width: {{ $labelPt }}pt;">
            <col style="width: {{ $typePt }}pt;">
            @for($d = 1; $d <= $daysInMonth; $d++)
                <col style="width: {{ $dayPt }}pt;">
            @endfor
            <col style="width: {{ $totalPt }}pt;">
        </colgroup>

        <thead>
            {{-- Row 1: defines ALL column widths explicitly (critical for DomPDF) --}}
            <tr>
                <th rowspan="2" style="width: {{ $noPt }}pt;">NO.</th>
                <th rowspan="2" colspan="2" style="width: {{ $labelPt + $typePt }}pt;" class="lc">ADMIN JOB</th>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $day = $days[$d];
                        $bg = '';
                        if ($day['day_type'] === 'off_day') $bg = 'bg-yellow';
                        elseif ($day['day_type'] === 'public_holiday') $bg = 'bg-red';
                    @endphp
                    <th class="dc {{ $bg }}" style="width: {{ $dayPt }}pt;">{{ strtoupper(substr($day['day_of_week'], 0, 3)) }}</th>
                @endfor
                <th rowspan="2" style="width: {{ $totalPt }}pt;">TOTAL</th>
            </tr>
            {{-- Row 2: day numbers --}}
            <tr>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $day = $days[$d];
                        $bg = '';
                        if ($day['day_type'] === 'off_day') $bg = 'bg-yellow';
                        elseif ($day['day_type'] === 'public_holiday') $bg = 'bg-red';
                    @endphp
                    <th class="dc {{ $bg }}" style="width: {{ $dayPt }}pt;">{{ $d }}</th>
                @endfor
            </tr>
        </thead>

        <tbody>
            {{-- ===== ADMIN JOB ROWS ===== --}}
            @php $rowNum = 0; @endphp
            @foreach($adminTypes as $type => $adminLabel)
                @php $rowNum++; @endphp
                <tr>
                    <td style="width: {{ $noPt }}pt;">{{ $rowNum }}</td>
                    <td colspan="2" class="lc" style="width: {{ $labelPt + $typePt }}pt;">{{ $adminLabel }}</td>
                    @php $rowTotal = 0; @endphp
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $day = $days[$d];
                            $val = $adminData[$type][$d] ?? 0;
                            $rowTotal += $val;
                            $bg = '';
                            if ($day['day_type'] === 'off_day') $bg = 'bg-yellow';
                            elseif ($day['day_type'] === 'public_holiday') $bg = 'bg-red';
                        @endphp
                        <td class="dc {{ $bg }}" style="width: {{ $dayPt }}pt;">{{ $val > 0 ? number_format($val, 1) : '' }}</td>
                    @endfor
                    <td class="bold" style="width: {{ $totalPt }}pt;">{{ $rowTotal > 0 ? number_format($rowTotal, 1) : '' }}</td>
                </tr>
            @endforeach

            {{-- TOTAL ADMIN JOB --}}
            <tr class="summary-row">
                <td class="bg-admin-total" style="width: {{ $noPt }}pt;"></td>
                <td colspan="2" class="lc bg-admin-total bold" style="width: {{ $labelPt + $typePt }}pt;">TOTAL ADMIN JOB</td>
                @php $grandAdmin = 0; @endphp
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $dayTotal = 0;
                        foreach ($adminTypes as $type => $adminLabel) {
                            $dayTotal += $adminData[$type][$d] ?? 0;
                        }
                        $grandAdmin += $dayTotal;
                    @endphp
                    <td class="dc bg-admin-total" style="width: {{ $dayPt }}pt;">{{ $dayTotal > 0 ? number_format($dayTotal, 1) : '0' }}</td>
                @endfor
                <td class="bg-admin-total" style="width: {{ $totalPt }}pt;">{{ number_format($grandAdmin, 1) }}</td>
            </tr>

            {{-- ===== PROJECT CODE SECTION ===== --}}
            <tr style="border-top: 1.5pt solid #000;">
                <th style="width: {{ $noPt }}pt;">NO.</th>
                <th class="lc" style="width: {{ $labelPt }}pt;">PROJECT CODE</th>
                <th style="font-size: 3.2pt; width: {{ $typePt }}pt;">TIME/<br>COST</th>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $day = $days[$d];
                        $bg = '';
                        if ($day['day_type'] === 'off_day') $bg = 'bg-yellow';
                        elseif ($day['day_type'] === 'public_holiday') $bg = 'bg-red';
                    @endphp
                    <th class="dc {{ $bg }}" style="width: {{ $dayPt }}pt;"></th>
                @endfor
                <th style="width: {{ $totalPt }}pt;"></th>
            </tr>

            @php $subRowLabels = ['NC', 'COBQ', 'NC', 'COBQ']; @endphp
            @php $subRowTypes = ['NORMAL', '', 'OT', '']; @endphp
            @php $subRowFields = ['normal_nc', 'normal_cobq', 'ot_nc', 'ot_cobq']; @endphp

            @foreach($projectRowsData as $pIdx => $proj)
                @for($sIdx = 0; $sIdx < 4; $sIdx++)
                    <tr class="{{ $sIdx === 0 ? 'proj-top' : '' }}">
                        @if($sIdx === 0)
                            <td rowspan="4" style="vertical-align: top; font-weight: bold; width: {{ $noPt }}pt;">{{ $pIdx + 1 }}</td>
                            <td rowspan="4" class="lc" style="vertical-align: top; width: {{ $labelPt }}pt;">
                                @if($proj['project_code'])
                                    <b>{{ $proj['project_code'] }}</b>
                                @endif
                                @if($proj['project_name'])
                                    <br><span style="font-size: 4pt;">{{ $proj['project_name'] }}</span>
                                @endif
                            </td>
                        @endif
                        <td style="font-size: 3.5pt; width: {{ $typePt }}pt; padding: 0;">
                            <table style="width: 100%; border-collapse: collapse; border: none;">
                                @if($subRowTypes[$sIdx])
                                    <tr>
                                        <td style="border: none; padding: 1px 0;"><b>{{ $subRowTypes[$sIdx] }}</b></td>
                                    </tr>
                                    <tr>
                                        <td style="border-top: 0.5pt solid #000; border-bottom: none; border-left: none; border-right: none; padding: 1px 0;"></td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="border: none; padding: 1px 0;">{{ $subRowLabels[$sIdx] }}</td>
                                </tr>
                            </table>
                        </td>
                        @php $subTotal = 0; @endphp
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $day = $days[$d];
                                $val = $proj['hours'][$d][$subRowFields[$sIdx]] ?? 0;
                                $subTotal += $val;
                                $bg = '';
                                if ($day['day_type'] === 'off_day') $bg = 'bg-yellow';
                                elseif ($day['day_type'] === 'public_holiday') $bg = 'bg-red';
                            @endphp
                            <td class="dc {{ $bg }}" style="width: {{ $dayPt }}pt;">{{ $val > 0 ? number_format($val, 1) : '' }}</td>
                        @endfor
                        <td class="bold" style="width: {{ $totalPt }}pt;">{{ $subTotal > 0 ? number_format($subTotal, 1) : '0.0' }}</td>
                    </tr>
                @endfor
            @endforeach

            {{-- ===== SUMMARY ROWS ===== --}}
            <tr class="summary-row" style="border-top: 1.5pt solid #000;">
                <td class="bg-gray" style="width: {{ $noPt }}pt;"></td>
                <td colspan="2" class="lc bg-gray bold" style="width: {{ $labelPt + $typePt }}pt;">TOTAL EXTERNAL PROJECT</td>
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
                    <td class="dc bg-gray" style="width: {{ $dayPt }}pt;">{{ $extTotal > 0 ? number_format($extTotal, 1) : '0' }}</td>
                @endfor
                <td class="bg-gray" style="width: {{ $totalPt }}pt;">{{ number_format($grandExt, 1) }}</td>
            </tr>

            <tr class="summary-row">
                <td style="width: {{ $noPt }}pt;"></td>
                <td colspan="2" class="lc bold" style="width: {{ $labelPt + $typePt }}pt;">TOTAL WORKING HOURS</td>
                @php $grandWorking = 0; @endphp
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $adminDayTotal = 0;
                        foreach ($adminTypes as $type => $adminLabel) {
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
                    <td class="dc" style="width: {{ $dayPt }}pt;">{{ $workingTotal > 0 ? number_format($workingTotal, 1) : '0' }}</td>
                @endfor
                <td class="bold" style="width: {{ $totalPt }}pt;">{{ number_format($grandWorking, 1) }}</td>
            </tr>

            <tr class="summary-row">
                <td class="bg-gray" style="width: {{ $noPt }}pt;"></td>
                <td colspan="2" class="lc bg-gray bold" style="width: {{ $labelPt + $typePt }}pt;">HOURS AVAILABLE</td>
                @php $totalAvail = 0; @endphp
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php $avail = $days[$d]['available_hours'] ?? 0; $totalAvail += $avail; @endphp
                    <td class="dc bg-gray" style="width: {{ $dayPt }}pt;">{{ $avail > 0 ? number_format($avail, 1) : '' }}</td>
                @endfor
                <td class="bg-gray" style="width: {{ $totalPt }}pt;">{{ number_format($totalAvail, 1) }}</td>
            </tr>

            <tr class="summary-row">
                <td style="width: {{ $noPt }}pt;"></td>
                <td colspan="2" class="lc bold" style="width: {{ $labelPt + $typePt }}pt;">OVERTIME</td>
                @php $grandOt = 0; @endphp
                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $adminDayTotal = 0;
                        foreach ($adminTypes as $type => $adminLabel) {
                            $adminDayTotal += $adminData[$type][$d] ?? 0;
                        }
                        $extDayTotal = 0;
                        foreach ($projectRowsData as $proj) {
                            $h = $proj['hours'][$d] ?? [];
                            $extDayTotal += ($h['normal_nc'] ?? 0) + ($h['normal_cobq'] ?? 0) + ($h['ot_nc'] ?? 0) + ($h['ot_cobq'] ?? 0);
                        }
                        $workingTotal = $adminDayTotal + $extDayTotal;
                        $avail = $days[$d]['available_hours'] ?? 0;
                        $ot = max(0, $workingTotal - $avail);
                        $grandOt += $ot;
                    @endphp
                    <td class="dc" style="width: {{ $dayPt }}pt;">{{ $ot > 0 ? number_format($ot, 1) : '' }}</td>
                @endfor
                <td class="bold" style="width: {{ $totalPt }}pt;">{{ $grandOt > 0 ? number_format($grandOt, 1) : '' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ===== FOOTER / LEGEND ===== --}}
    <div class="footer">
        <table>
            <tr>
                <td style="width: 40%;">
                    <b>NOTE:</b><br>
                    NORMAL DAY (EXCLUDE OT) - 8 HOURS<br>
                    FRIDAY ONLY (EXCLUDE OT) - 7 HOURS
                </td>
                <td style="width: 60%;">
                    <b>LEGEND:</b><br>
                    <b>NC</b> = NORMAL COST &nbsp;&nbsp;
                    <b>COBQ</b> = COST OF BAD QUALITY &nbsp;&nbsp;
                    <b>RFQ</b> = REQUEST FOR QUOTATION<br>
                    <b>MKT</b> = MARKETING &nbsp;&nbsp;
                    <b>PUR</b> = PURCHASING &nbsp;&nbsp;
                    <b>TDR</b> = TENDER &nbsp;&nbsp;
                    <b>A.S.S</b> = AFTER SALE SERVICE
                </td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: right; font-size: 4pt; padding-top: 0.5mm;">
                    <b>REMARKS:</b> SUBMIT TO FINANCE ON 2ND WORKING DAYS END OF EACH MONTH
                </td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center; font-size: 4pt; color: #666;">
                    PLEASE MAKE SURE TOTAL UP ALL HOURS OF THE ITEM
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
