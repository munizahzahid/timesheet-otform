<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Time Sheet</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 2mm 3mm;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 8.5pt;
            color: #000;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        td, th {
            padding: 2px 3px;
            vertical-align: middle;
            font-weight: normal;
            overflow: hidden;
            word-wrap: break-word;
        }
        .bd td, .bd th { border: 0.5pt solid #000; overflow: hidden; }
        .tc { text-align: center; }
        .tl { text-align: left; }
        .tr { text-align: right; }
        .b  { font-weight: bold; }
        .f5 { font-size: 5pt; }
        .f6 { font-size: 6pt; }
        .f7 { font-size: 7pt; }
        .f8 { font-size: 8pt; }
        .f9 { font-size: 9pt; }
        .f10 { font-size: 10pt; }
        .f12 { font-size: 12pt; }
        .f14 { font-size: 14pt; }
        .blue { color: #00B0F0; font-weight: bold; }
        .red  { color: #FF0000; }
        .dbl { border: 1.8pt double #000 !important; }
        .nb { border: 0 !important; }

        /* Circular signature stamp */
        .stamp {
            width: 48px;
            height: 48px;
            border: 1pt solid #002060;
            border-radius: 50%;
            margin: 1px auto;
            padding: 4px 2px;
            text-align: center;
            color: #002060;
            line-height: 1.2;
        }
        .stamp .nm  { font-size: 5.5pt; font-weight: bold; }
        .stamp .ds  { font-size: 4.5pt; }
        .stamp .dt  { font-size: 4.5pt; margin-top: 1px; }

        /* Background colors */
        .bg-yellow { background-color: #FFFF00; }
        .bg-red { background-color: #FF0000; }
    </style>
</head>
<body>
@php
    $timesheet->load([
        'user.department',
        'dayMetadata',
        'adminHours',
        'projectRows.projectCode',
        'projectRows.hours',
        'approvalLogs.user',
    ]);

    $user = $timesheet->user;
    $monthName = strtoupper(Carbon\Carbon::create($timesheet->year, $timesheet->month)->format('F'));
    $monthYear = $monthName . ' ' . $timesheet->year;
    $daysInMonth = Carbon\Carbon::create($timesheet->year, $timesheet->month)->daysInMonth;

    // Day metadata
    $days = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = Carbon\Carbon::create($timesheet->year, $timesheet->month, $d);
        $days[$d] = [
            'day_of_week' => strtoupper($date->format('D')), // Three-letter: MON, TUE, WED, THU, FRI, SAT, SUN
            'day_type' => 'working',
            'available_hours' => 8,
        ];
    }
    // Merge DB metadata
    foreach ($timesheet->dayMetadata as $meta) {
        $d = (int) $meta->entry_date->day;
        if (isset($days[$d])) {
            $days[$d]['day_type'] = $meta->day_type;
            $days[$d]['available_hours'] = (float) $meta->available_hours;
        }
    }

    // Admin hours lookup
    $adminTypes = \App\Services\TimesheetCalculationService::ADMIN_TYPES;
    $adminData = [];
    foreach ($adminTypes as $type => $label) {
        $adminData[$type] = array_fill(1, $daysInMonth, 0);
    }
    foreach ($timesheet->adminHours as $ah) {
        $day = (int) $ah->entry_date->day;
        if (isset($adminData[$ah->admin_type])) {
            $adminData[$ah->admin_type][$day] = (float) $ah->hours;
        }
    }

    // Project rows data
    $projectRowsData = [];
    foreach ($timesheet->projectRows->sortBy('row_order') as $row) {
        $hoursData = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $hoursData[$d] = [
                'normal_nc' => 0, 'normal_cobq' => 0,
                'ot_nc' => 0, 'ot_cobq' => 0,
            ];
        }
        foreach ($row->hours as $h) {
            $day = (int) $h->entry_date->day;
            $hoursData[$day] = [
                'normal_nc'   => (float) $h->normal_nc_hours,
                'normal_cobq' => (float) $h->normal_cobq_hours,
                'ot_nc'       => (float) $h->ot_nc_hours,
                'ot_cobq'     => (float) $h->ot_cobq_hours,
            ];
        }
        $projectRowsData[] = [
            'project_code' => $row->projectCode ? $row->projectCode->code : '',
            'project_name' => $row->project_name,
            'hours'        => $hoursData,
        ];
    }

    // Pad to minimum 5 project slots
    while (count($projectRowsData) < 5) {
        $emptyHours = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $emptyHours[$d] = ['normal_nc' => 0, 'normal_cobq' => 0, 'ot_nc' => 0, 'ot_cobq' => 0];
        }
        $projectRowsData[] = [
            'project_code' => '',
            'project_name' => '',
            'hours'        => $emptyHours,
        ];
    }

    // Approval stamps
    $approvalLogs = $timesheet->approvalLogs ? $timesheet->approvalLogs->sortBy('id') : collect();
    $staffStamp = ['show' => !in_array($timesheet->status, ['draft']), 'name' => $timesheet->staff_signature ?? ($user->name ?? ''), 'date' => $timesheet->staff_signed_at ? $timesheet->staff_signed_at->format('d/m/Y') : ''];
    $hodLog = $approvalLogs->where('level', 0.5)->where('action', 'approved')->first();
    $hodStamp = ['show' => $hodLog && $hodLog->user, 'name' => $hodLog && $hodLog->user ? $hodLog->user->name : '', 'date' => $timesheet->hod_signed_at ? $timesheet->hod_signed_at->format('d/m/Y') : ''];
    $l1Log = $approvalLogs->where('level', 1)->where('action', 'approved')->first();
    $l1Stamp = ['show' => $l1Log && $l1Log->user, 'name' => $l1Log && $l1Log->user ? $l1Log->user->name : '', 'date' => $timesheet->l1_signed_at ? $timesheet->l1_signed_at->format('d/m/Y') : ''];

    // Short name helper - skips common prefixes
    $shortName = function($name) {
        $prefixes = ['MOHAMAD', 'MUHAMMAD', 'MUHAMAD', 'MUHD', 'MOHD', 'NUR', 'NURUL', 'SITI'];
        $parts = explode(' ', strtoupper($name));
        
        // Skip common prefixes
        foreach ($parts as $part) {
            if (!in_array($part, $prefixes)) {
                return $part;
            }
        }
        
        // If all parts are prefixes, return the last part
        return end($parts);
    };
@endphp

<div style="border: 3pt solid #000; padding: 1.5mm 2mm;">
    {{-- ───── TOP HEADER: LOGO + TITLE + APPROVAL BOXES ───── --}}
    <table style="margin-bottom: 1mm; width: 100%;">
        <tr>
            <td style="padding: 2mm 0; width: 78%;">
                <div style="font-size: 14pt; font-weight: bold;">TALENT SYNERGY SDN BHD</div>
            </td>
            <td style="vertical-align: top; width: 22%; padding: 0;">
                <table class="bd" style="width: 100%; font-size: 3.5pt; border: 0.5pt solid #000;">
                    <tr>
                        <td class="f4 b tc" style="padding: 1px;">Prepared By</td>
                        <td class="f4 b tc" style="padding: 1px;">Checked By</td>
                        <td class="f4 b tc" style="padding: 1px;">Verified By</td>
                    </tr>
                    <tr style="height: 55px;">
                        <td class="tc">
                            @if($staffStamp['show'])
                                <div class="stamp">
                                    <div class="ds">PRPD</div>
                                    <div class="nm">{{ $shortName($staffStamp['name']) }}</div>
                                    <div class="ds">STAFF</div>
                                    <div class="dt">{{ $staffStamp['date'] }}</div>
                                </div>
                            @endif
                        </td>
                        <td class="tc">
                            @if($hodStamp['show'])
                                <div class="stamp">
                                    <div class="ds">CHKD</div>
                                    <div class="nm">{{ $shortName($hodStamp['name']) }}</div>
                                    <div class="ds">HOD/EXEC</div>
                                    <div class="dt">{{ $hodStamp['date'] }}</div>
                                </div>
                            @endif
                        </td>
                        <td class="tc">
                            @if($l1Stamp['show'])
                                <div class="stamp">
                                    <div class="ds">VRFD</div>
                                    <div class="nm">{{ $shortName($l1Stamp['name']) }}</div>
                                    <div class="ds">MNGR</div>
                                    <div class="dt">{{ $l1Stamp['date'] }}</div>
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="f3 b tc">{{ $shortName($staffStamp['name']) }}</td>
                        <td class="f3 b tc">HOD/EXEC</td>
                        <td class="f3 b tc">MNGR</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ───── TITLE + INFO ROW ───── --}}
    <table style="border-top: 1pt solid #000; border-bottom: 1pt solid #000; margin-bottom: 1mm;">
        <tr>
            <td class="f7 b" style="padding: 2px 3px;">DAILY TIME SHEET</td>
            <td class="f7 b tr" style="padding: 2px 3px;">MONTH : {{ $monthYear }}</td>
        </tr>
    </table>
    <table style="margin-bottom: 1mm;">
        <col style="width: 10%;"/>
        <col style="width: 30%;"/>
        <col style="width: 10%;"/>
        <col style="width: 15%;"/>
        <tr>
            <td class="f7 b">NAME :</td>
            <td class="f7">{{ $user->name ?? '-' }}</td>
            <td class="f7 b">STAFF NO :</td>
            <td class="f7">{{ $user->staff_no ?? '-' }}</td>
        </tr>
    </table>

    {{-- ───── SIZING ROW FOR COLUMNS ───── --}}
    <table class="bd" style="width: 100%;">
        <tr style="height: 0; line-height: 0; font-size: 0;">
            <td style="width:2%; padding:0; border:0; height:0;"></td>
            <td style="width:15%; padding:0; border:0; height:0;"></td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td style="width:2.2%; padding:0; border:0; height:0;"></td>
            @endfor
            <td style="width:3%; padding:0; border:0; height:0;"></td>
        </tr>

        {{-- ───── HEADER ROW ───── --}}
        <tr class="f7 b tc">
            <td rowspan="3">NO.</td>
            <td rowspan="3">ADMIN JOB</td>
            <td colspan="{{ $daysInMonth }}">HOURS</td>
            <td rowspan="3">TOTAL</td>
        </tr>
        <tr class="f7 b tc">
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td class="f6">{{ $days[$d]['day_of_week'] }}</td>
            @endfor
        </tr>
        <tr class="f7 b tc">
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td class="f6">{{ $d }}</td>
            @endfor
        </tr>

        {{-- ───── ADMIN SECTION ───── --}}
        @php
            $adminLabels = [
                'mc_leave' => 'MC/LEAVE',
                'late' => 'LATE',
                'morning_assy_admin_job' => 'MORNING ASSY/ADMIN JOB',
                's5' => '5S',
                'ceramah_agama' => 'CERAMAH AGAMA',
                'iso' => 'ISO',
                'training_seminar_visit' => 'TRAINING/SEMINAR/VISIT',
                'rfq_mkt_pur_r_d_a_s_s_tdr' => 'RFQ / MKT / PUR / R & D / A.S.S / TDR',
            ];
            $adminTypeKeys = array_keys($adminTypes);
            $rowNum = 1;
        @endphp
        @foreach($adminTypeKeys as $idx => $typeKey)
            @php
                $label = $adminLabels[$typeKey] ?? $adminTypes[$typeKey];
                $rowTotal = 0;
                $adminRowData = [];
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $val = $adminData[$typeKey][$d] ?? 0;
                    $rowTotal += $val;
                    $dayType = $days[$d]['day_type'] ?? 'working';
                    $bgClass = '';
                    if ($dayType === 'off_day') $bgClass = 'bg-yellow';
                    if ($dayType === 'public_holiday') $bgClass = 'bg-red';
                    $adminRowData[$d] = ['val' => $val, 'bgClass' => $bgClass];
                }
            @endphp
            <tr class="f6 tc">
                <td class="b">{{ $rowNum }}</td>
                <td class="tl">{{ $label }}</td>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    <td class="{{ $adminRowData[$d]['bgClass'] }}">{{ $adminRowData[$d]['val'] > 0 ? $adminRowData[$d]['val'] : '' }}</td>
                @endfor
                <td class="b">{{ $rowTotal > 0 ? $rowTotal : '' }}</td>
            </tr>
            @php $rowNum++; @endphp
        @endforeach

        {{-- TOTAL ADMIN JOB --}}
        @php
            $grandAdminTotal = 0;
            $adminDayTotals = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayTotal = 0;
                foreach ($adminTypeKeys as $typeKey) {
                    $dayTotal += ($adminData[$typeKey][$d] ?? 0);
                }
                $grandAdminTotal += $dayTotal;
                $dayType = $days[$d]['day_type'] ?? 'working';
                $bgClass = '';
                if ($dayType === 'off_day') $bgClass = 'bg-yellow';
                if ($dayType === 'public_holiday') $bgClass = 'bg-red';
                $adminDayTotals[$d] = ['val' => $dayTotal, 'bgClass' => $bgClass];
            }
        @endphp
        <tr class="f7 b tc">
            <td></td>
            <td class="tl">TOTAL ADMIN JOB</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td class="{{ $adminDayTotals[$d]['bgClass'] }}">{{ $adminDayTotals[$d]['val'] > 0 ? $adminDayTotals[$d]['val'] : '' }}</td>
            @endfor
            <td class="b">{{ $grandAdminTotal > 0 ? $grandAdminTotal : '' }}</td>
        </tr>
    </table>

    {{-- ───── PROJECT TABLE ───── --}}
    <table class="bd" style="width: 100%; margin-top: 2mm;">
        <tr style="height: 0; line-height: 0; font-size: 0;">
            <td style="width:2%; padding:0; border:0; height:0;"></td>
            <td style="width:8%; padding:0; border:0; height:0;"></td>
            <td style="width:4%; padding:0; border:0; height:0;"></td>
            <td style="width:3%; padding:0; border:0; height:0;"></td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td style="width:2.2%; padding:0; border:0; height:0;"></td>
            @endfor
            <td style="width:3%; padding:0; border:0; height:0;"></td>
        </tr>
        <tr class="f7 b tc">
            <td>NO</td>
            <td>PROJECT CODE</td>
            <td colspan="2">TIME / COST</td>
            <td colspan="{{ $daysInMonth }}">HOURS</td>
            <td>TOTAL</td>
        </tr>

        {{-- ───── PROJECT ROWS ───── --}}
        @php
            $projNum = 1;
            $grandTotalExternal = 0;
        @endphp
        @foreach($projectRowsData as $proj)
            @php
                $code = $proj['project_code'];
                $name = $proj['project_name'];
                $displayText = $code . ($name ? ' - ' . $name : '');
                
                // Pre-calculate all project row data
                $normalNcData = [];
                $normalCobqData = [];
                $otNcData = [];
                $otCobqData = [];
                
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayType = $days[$d]['day_type'] ?? 'working';
                    $bgClass = '';
                    if ($dayType === 'off_day') $bgClass = 'bg-yellow';
                    if ($dayType === 'public_holiday') $bgClass = 'bg-red';
                    
                    $normalNcData[$d] = ['val' => $proj['hours'][$d]['normal_nc'] ?? 0, 'bgClass' => $bgClass];
                    $normalCobqData[$d] = ['val' => $proj['hours'][$d]['normal_cobq'] ?? 0, 'bgClass' => $bgClass];
                    $otNcData[$d] = ['val' => $proj['hours'][$d]['ot_nc'] ?? 0, 'bgClass' => $bgClass];
                    $otCobqData[$d] = ['val' => $proj['hours'][$d]['ot_cobq'] ?? 0, 'bgClass' => $bgClass];
                }
                
                $normalNcTotal = array_sum(array_column($normalNcData, 'val'));
                $normalCobqTotal = array_sum(array_column($normalCobqData, 'val'));
                $otNcTotal = array_sum(array_column($otNcData, 'val'));
                $otCobqTotal = array_sum(array_column($otCobqData, 'val'));
            @endphp
            {{-- Project header (4 rows merged) --}}
            <tr class="f6 tc">
                <td rowspan="4" class="b">{{ $projNum }}</td>
                <td rowspan="4" class="tl" style="font-size: 6pt;">{{ $displayText }}</td>
                <td rowspan="2" class="b">NORMAL</td>
                <td>NC</td>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    <td class="{{ $normalNcData[$d]['bgClass'] }}">{{ $normalNcData[$d]['val'] > 0 ? $normalNcData[$d]['val'] : '' }}</td>
                @endfor
                <td class="b">{{ $normalNcTotal > 0 ? $normalNcTotal : '' }}</td>
            </tr>
            <tr class="f6 tc">
                <td>COBQ</td>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    <td class="{{ $normalCobqData[$d]['bgClass'] }}">{{ $normalCobqData[$d]['val'] > 0 ? $normalCobqData[$d]['val'] : '' }}</td>
                @endfor
                <td class="b">{{ $normalCobqTotal > 0 ? $normalCobqTotal : '' }}</td>
            </tr>
            <tr class="f6 tc">
                <td rowspan="2" class="b">OT</td>
                <td>NC</td>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    <td class="{{ $otNcData[$d]['bgClass'] }}">{{ $otNcData[$d]['val'] > 0 ? $otNcData[$d]['val'] : '' }}</td>
                @endfor
                <td class="b">{{ $otNcTotal > 0 ? $otNcTotal : '' }}</td>
            </tr>
            <tr class="f6 tc">
                <td>COBQ</td>
                @for($d = 1; $d <= $daysInMonth; $d++)
                    <td class="{{ $otCobqData[$d]['bgClass'] }}">{{ $otCobqData[$d]['val'] > 0 ? $otCobqData[$d]['val'] : '' }}</td>
                @endfor
                <td class="b">{{ $otCobqTotal > 0 ? $otCobqTotal : '' }}</td>
            </tr>
            @php $projNum++; @endphp
        @endforeach

        {{-- TOTAL EXTERNAL PROJECT --}}
        @php
            $externalDayTotals = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dayTotal = 0;
                foreach ($projectRowsData as $project) {
                    $dayTotal += $project['hours'][$d]['normal_nc'] + $project['hours'][$d]['normal_cobq']
                               + $project['hours'][$d]['ot_nc'] + $project['hours'][$d]['ot_cobq'];
                }
                $externalDayTotals[$d] = $dayTotal;
                $grandTotalExternal += $dayTotal;
            }
        @endphp
        <tr class="f7 b tc blue">
            <td colspan="4">TOTAL EXTERNAL PROJECT</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td>{{ $externalDayTotals[$d] }}</td>
            @endfor
            <td class="b">{{ $grandTotalExternal }}</td>
        </tr>
    </table>

    {{-- ───── SUMMARY TABLE ───── --}}
    <table class="bd" style="width: 100%; margin-top: 2mm;">
        <tr style="height: 0; line-height: 0; font-size: 0;">
            <td style="width:2%; padding:0; border:0; height:0;"></td>
            <td style="width:10%; padding:0; border:0; height:0;"></td>
            <td style="width:3%; padding:0; border:0; height:0;"></td>
            <td style="width:2%; padding:0; border:0; height:0;"></td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td style="width:2.2%; padding:0; border:0; height:0;"></td>
            @endfor
            <td style="width:3%; padding:0; border:0; height:0;"></td>
        </tr>

        {{-- Spacer --}}
        <tr style="height: 5px;">
            <td colspan="{{4 + $daysInMonth + 1}}"></td>
        </tr>

        {{-- TOTAL WORKING HOURS --}}
        @php
            $workingDayTotals = [];
            $grandTotalWorking = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $adminTotal = 0;
                foreach ($adminData as $type => $hours) {
                    $adminTotal += $hours[$d];
                }
                $externalTotal = 0;
                foreach ($projectRowsData as $project) {
                    $externalTotal += $project['hours'][$d]['normal_nc'] + $project['hours'][$d]['normal_cobq']
                                    + $project['hours'][$d]['ot_nc'] + $project['hours'][$d]['ot_cobq'];
                }
                $dayTotal = $adminTotal + $externalTotal;
                $workingDayTotals[$d] = $dayTotal;
                $grandTotalWorking += $dayTotal;
            }
        @endphp
        <tr class="f7 b tc blue">
            <td colspan="4">TOTAL WORKING HOURS</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td>{{ $workingDayTotals[$d] }}</td>
            @endfor
            <td class="b">{{ $grandTotalWorking }}</td>
        </tr>

        {{-- HOURS AVAILABLE --}}
        @php
            $availDayTotals = [];
            $totalAvail = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $avail = $days[$d]['available_hours'];
                $availDayTotals[$d] = $avail;
                $totalAvail += $avail;
            }
        @endphp
        <tr class="f7 b tc red">
            <td colspan="4">HOURS AVAILABLE</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td>{{ $availDayTotals[$d] }}</td>
            @endfor
            <td class="b">{{ $totalAvail }}</td>
        </tr>

        {{-- OVERTIME --}}
        @php
            $overtimeDayTotals = [];
            $grandTotalOvertime = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $adminTotal = 0;
                foreach ($adminData as $type => $hours) {
                    $adminTotal += $hours[$d];
                }
                $externalTotal = 0;
                foreach ($projectRowsData as $project) {
                    $externalTotal += $project['hours'][$d]['normal_nc'] + $project['hours'][$d]['normal_cobq']
                                    + $project['hours'][$d]['ot_nc'] + $project['hours'][$d]['ot_cobq'];
                }
                $dayWorkingTotal = $adminTotal + $externalTotal;
                $avail = $days[$d]['available_hours'];
                $dayOvertime = $dayWorkingTotal - $avail;
                $overtimeDayTotals[$d] = $dayOvertime;
                $grandTotalOvertime += $dayOvertime;
            }
        @endphp
        <tr class="f7 b tc blue">
            <td colspan="4">OVERTIME</td>
            @for($d = 1; $d <= $daysInMonth; $d++)
                <td>{{ $overtimeDayTotals[$d] }}</td>
            @endfor
            <td class="b">{{ $grandTotalOvertime }}</td>
        </tr>
    </table>

    {{-- ───── BOTTOM SECTION: NOTES/LEGEND + REMARKS BOX ───── --}}
    <table style="margin-top: 2mm; width: 100%; font-size: 6pt;">
        <tr>
            <td style="width: 80%; vertical-align: top;">
                <table style="width: 100%;">
                    <tr>
                        <td class="b" style="width: 20%;">NOTE:-</td>
                        <td class="b" style="width: 5%;">LEGEND:</td>
                        <td style="width: 75%;">
                            <span class="b">NC</span> - NORMAL COST &nbsp;&nbsp;
                            <span class="b">PU</span> - PURCHASING &nbsp;&nbsp;
                            <span class="b">COBQ</span> - COST OF BAD QUALITY &nbsp;&nbsp;
                            <span class="b">RFQ</span> - REQUEST FOR QUOTATION
                        </td>
                    </tr>
                    <tr>
                        <td class="b">NORMAL DAY (EXCLUDE OT) -8 HOURS</td>
                        <td></td>
                        <td>
                            <span class="b">MKT</span> - MARKETING &nbsp;&nbsp;
                            <span class="b">R&D</span> - RESEARCH & DEV &nbsp;&nbsp;
                            <span class="b">TDR</span> - TENDER &nbsp;&nbsp;
                            <span class="b">A.S.S</span> - AFTER SALE SERVICE
                        </td>
                    </tr>
                    <tr>
                        <td class="b">FRIDAY ONLY (EXCLUDE OT) -7 HOURS</td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </td>
            <td style="width: 20%; vertical-align: top;">
                <table class="bd" style="width: 100%;">
                    <tr>
                        <td class="b tc" style="padding: 2px;">REMARKS</td>
                    </tr>
                    <tr style="height: 60px;">
                        <td style="padding: 4px; font-size: 5pt;">SUBMIT TO FINANCE ON 2ND WORKING DAYS END OF EACH MONTH</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
