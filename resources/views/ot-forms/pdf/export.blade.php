<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Borang Kerja Lebih Masa</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 3mm 4mm;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 13pt;
            color: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .outer {
            border: 3pt solid #000;
            padding: 3mm 3.5mm;
            box-sizing: border-box;
        }
        .inner-border {
            border: 1pt solid #000;
            padding: 2.5mm;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        td, th {
            padding: 4px 5px;
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
        .f11 { font-size: 11pt; }
        .f12 { font-size: 12pt; }
        .f14 { font-size: 14pt; }
        .blue { color: #002060; font-weight: bold; }
        .red  { color: #FF0000; }
        .dbl { border: 1.8pt double #000 !important; }
        .nb { border: 0 !important; }

        /* Vertical text */
        .vert {
            display: inline-block;
            transform: rotate(-90deg);
            transform-origin: center center;
            white-space: nowrap;
        }

        /* Top header */
        .ribbon-title {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 2pt;
            text-align: center;
        }
        .ribbon-title .small {
            display: block;
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 2pt;
            margin-top: 1px;
        }
        .note-text {
            font-size: 8pt;
            line-height: 1.3;
            padding: 3px 4px;
        }
        .note-text b { text-decoration: underline; }

        /* Info row labels & boxes */
        .info-tbl { table-layout: auto; width: 100%; }
        .lbl  { font-size: 7pt; font-weight: bold; padding: 1px 2px; white-space: nowrap; width: 1%; }
        .colon{ font-size: 7pt; font-weight: bold; padding: 1px 1px; width: 1%; text-align: center; white-space: nowrap; }
        .ibox { font-size: 7pt; font-weight: bold; border: 0.5pt solid #000; padding: 1px 4px; text-align: left; }

        /* Circular signature stamp */
        .stamp {
            width: 60px;
            height: 60px;
            border: 1pt solid #002060;
            border-radius: 50%;
            margin: 1px auto;
            padding: 4px 3px;
            text-align: center;
            color: #002060;
            line-height: 1;
        }
        .stamp .nm  { font-size: 5pt; font-weight: bold; }
        .stamp .ds  { font-size: 4.5pt; }
        .stamp .dt  { font-size: 4.5pt; margin-top: 1px; }

        /* Non-executive scale down to fit A4 */
        .non-executive-scale {
            font-size: 6.5pt;
        }
        .non-executive-scale td,
        .non-executive-scale th {
            padding: 1px 1px;
        }
        .non-executive-scale .outer {
            padding: 1.5mm 2mm;
        }
        .non-executive-scale .inner-border {
            padding: 1mm;
        }
    </style>
</head>
<body>
    @php
        $user     = $otForm->user;
        $dept     = $user->department->name ?? '-';
        $monthStr = strtoupper(\DateTime::createFromFormat('!m', $otForm->month)->format('F')) . ' ' . $otForm->year;
        $isExecutive = $otForm->isExecutive();

        // Approval signers
        $logs = \App\Models\ApprovalLog::where('approvable_type', 'ot_form')
            ->where('approvable_id', $otForm->id)
            ->where('action', 'approved')
            ->with('approver')
            ->orderBy('level')
            ->get();

        $staffShortName = \App\Services\OtFormExcelExport::shortName($user->name ?? '');
        $hodLog = $logs->where('level', 2)->first();
        $hodSignerName = ($hodLog && $hodLog->approver) ? \App\Services\OtFormExcelExport::shortName($hodLog->approver->name) : '';
        $gmLog  = $logs->where('level', 1)->first();
        $gmSignerName  = ($gmLog && $gmLog->approver) ? \App\Services\OtFormExcelExport::shortName($gmLog->approver->name) : '';

        // Fallback designated approvers
        $hodApp = null; $gmApp = null;
        if (!$hodSignerName && in_array($otForm->status, ['pending_gm','approved'])) {
            if ($otForm->form_type === 'executive' && $user->ot_exec_approver_id) {
                $hodApp = \App\Models\User::find($user->ot_exec_approver_id);
            } elseif ($otForm->form_type === 'non_executive' && $user->ot_non_exec_approver_id) {
                $hodApp = \App\Models\User::find($user->ot_non_exec_approver_id);
            }
            if (!$hodApp && $user->reports_to) $hodApp = \App\Models\User::find($user->reports_to);
            if ($hodApp) $hodSignerName = \App\Services\OtFormExcelExport::shortName($hodApp->name);
        }
        if (!$gmSignerName && $otForm->status === 'approved') {
            if ($otForm->form_type === 'executive' && $user->ot_exec_final_approver_id) {
                $gmApp = \App\Models\User::find($user->ot_exec_final_approver_id);
            } elseif ($otForm->form_type === 'non_executive' && $user->ot_non_exec_final_approver_id) {
                $gmApp = \App\Models\User::find($user->ot_non_exec_final_approver_id);
            }
            if ($gmApp) $gmSignerName = \App\Services\OtFormExcelExport::shortName($gmApp->name);
        }

        // Stamp full info
        $staffStamp = [
            'name'        => strtoupper($user->name ?? ''),
            'designation' => strtoupper($user->designation ?? ''),
            'date'        => $otForm->plan_submitted_at ? $otForm->plan_submitted_at->format('d/m/Y') : '',
            'show'        => !in_array($otForm->status, ['draft']),
        ];
        $hodStamp = ['name' => '', 'designation' => '', 'date' => '', 'show' => false];
        if ($hodLog && $hodLog->approver) {
            $hodStamp = [
                'name'        => strtoupper($hodLog->approver->name),
                'designation' => strtoupper($hodLog->approver->designation ?? ''),
                'date'        => $hodLog->acted_at ? $hodLog->acted_at->format('d/m/Y') : '',
                'show'        => true,
            ];
        } elseif ($hodApp) {
            $hodStamp = [
                'name'        => strtoupper($hodApp->name),
                'designation' => strtoupper($hodApp->designation ?? ''),
                'date'        => '',
                'show'        => true,
            ];
        }
        $gmStamp = ['name' => '', 'designation' => '', 'date' => '', 'show' => false];
        if ($gmLog && $gmLog->approver) {
            $gmStamp = [
                'name'        => strtoupper($gmLog->approver->name),
                'designation' => strtoupper($gmLog->approver->designation ?? ''),
                'date'        => $gmLog->acted_at ? $gmLog->acted_at->format('d/m/Y') : '',
                'show'        => true,
            ];
        } elseif ($gmApp) {
            $gmStamp = [
                'name'        => strtoupper($gmApp->name),
                'designation' => strtoupper($gmApp->designation ?? ''),
                'date'        => '',
                'show'        => true,
            ];
        }

        $entries = $otForm->entries()->with('projectCode')->get()->keyBy(fn($e) => $e->entry_date->day);

        $totalPlan = $totalActual = 0.0;
        $totalMeal = $totalShift = 0;
        $totalOt1 = $totalOt2 = $totalOt3 = $totalOt4 = 0.0;
        $totalOt5 = 0;
    @endphp

    @if (!$isExecutive)
    {{-- BKLM (Non-Executive) Layout --}}
    <div class="non-executive-scale">
    <div class="outer">
        <div class="inner-border">
        {{-- ───── TOP HEADER: INGRESS title + right info panel ───── --}}
        <table>
            <tr>
                <td style="width: 65%; padding: 1mm 0;">
                    <div class="ribbon-title">
                        KUMPULAN &nbsp;&nbsp;&nbsp; SYARIKAT
                        <span class="small">INGRESS</span>
                    </div>
                </td>
                <td style="width: 35%; padding: 0; vertical-align: top;">
                    <table class="bd">
                        <tr>
                            <td class="f7 tc" style="width: 45%;">DEPARTMENT</td>
                            <td class="f7 b tc" style="width: 55%;">SUMBER MANUSIA</td>
                        </tr>
                        <tr>
                            <td class="f7 tc">DOC NO</td>
                            <td class="f7 tc">ISSUE NO&nbsp;:&nbsp;-</td>
                        </tr>
                        <tr>
                            <td class="f7 tc">PAGE&nbsp;1&nbsp;OF&nbsp;1&nbsp;PAGES</td>
                            <td class="f7 tc">REV NO&nbsp;:&nbsp;0</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ───── TAJUK ───── --}}
        <table style="border-top: 1pt solid #000; border-bottom: 1pt solid #000; margin-top: 1mm;">
            <tr>
                <td class="f7 b" style="padding: 1px 3px; width: 8%;">TAJUK :</td>
                <td class="f7 b tl" style="padding: 1px 0;">BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF)</td>
            </tr>
        </table>

        {{-- ───── Company grid ───── --}}
        <table class="bd" style="margin-top: 0.5mm;">
            <tr>
                <td class="f6 b tc" style="width: 16.5%;">INGRESS<br>CORPORATION</td>
                <td class="f6 b tc" style="width: 16.5%;">INGRESS<br>INDUSTRIAL</td>
                <td class="f6 b tc" style="width: 16.5%;">INGRESS<br>ENGINEERING</td>
                <td class="f6 b tc" style="width: 16.5%;">INGRESS<br>PRECISION</td>
                <td class="f6 b tc" style="width: 16.5%;">INGRESS<br>KATAYAMA</td>
                <td class="f6 b tc" style="width: 14%;">TALENT<br>SYNERGY</td>
                <td class="f14 b tc" style="width: 4%;">/</td>
            </tr>
        </table>

        {{-- ───── Info rows: 3-column layout ───── --}}
        {{-- Left col: NAMA (long box) on top; JAWATAN + NO.KT side-by-side below --}}
        {{-- Mid col:  JABATAN / SEKSYEN/BAHG. (stacked) --}}
        {{-- Right col: BULAN alone --}}
        <table style="margin-top: 1mm;">
            <tr>
                {{-- LEFT 50% --}}
                <td style="width: 50%; padding-right: 4mm; vertical-align: top;">
                    <table class="info-tbl">
                        <tr>
                            <td class="lbl">NAMA</td>
                            <td class="colon">:</td>
                            <td class="ibox" colspan="4">{{ strtoupper($user->name ?? '-') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">JAWATAN</td>
                            <td class="colon">:</td>
                            <td class="ibox" style="width: 45%;">{{ strtoupper($user->designation ?? '-') }}</td>
                            <td class="lbl">NO. KT</td>
                            <td class="colon">:</td>
                            <td class="ibox">{{ $user->staff_no ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                {{-- MIDDLE 30% --}}
                <td style="width: 30%; padding-right: 4mm; vertical-align: top;">
                    <table class="info-tbl">
                        <tr>
                            <td class="lbl">JABATAN</td>
                            <td class="colon">:</td>
                            <td class="ibox">{{ strtoupper($dept) }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">SEKSYEN/BAHG.</td>
                            <td class="colon">:</td>
                            <td class="ibox">{{ strtoupper($otForm->section_line ?? '-') }}</td>
                        </tr>
                    </table>
                </td>
                {{-- RIGHT 20% --}}
                <td style="width: 20%; vertical-align: top;">
                    <table class="info-tbl">
                        <tr>
                            <td class="lbl">BULAN</td>
                            <td class="colon">:</td>
                            <td class="ibox">{{ $monthStr }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ───── MAIN DATA TABLE ───── --}}
        <table class="bd" style="margin-top: 1mm; width: 100%;">
            <colgroup>
                <col style="width: 3%;"/>     {{-- TARIKH --}}
                <col style="width: 22%;"/>    {{-- TUGAS (wider) --}}
                <col style="width: 3.5%;"/><col style="width: 3.5%;"/><col style="width: 3.5%;"/>   {{-- plan MULA/TAMAT/JUMLAH --}}
                <col style="width: 3.5%;"/><col style="width: 3.5%;"/><col style="width: 3.5%;"/>   {{-- actual MULA/TAMAT/JUMLAH --}}
                <col style="width: 2.5%;"/>    {{-- MAKAN --}}
                <col style="width: 2.5%;"/>    {{-- SHIFT --}}
                <col style="width: 5%;"/><col style="width: 5%;"/><col style="width: 5%;"/> {{-- KELULUSAN --}}
                <col style="width: 2%;"/><col style="width: 2%;"/><col style="width: 2%;"/><col style="width: 2%;"/> {{-- JENIS OT --}}
                <col style="width: 2%;"/><col style="width: 2%;"/><col style="width: 2%;"/><col style="width: 2%;"/><col style="width: 2%;"/> {{-- PENGIRAAN OT --}}
            </colgroup>
            {{-- Width-setter row (zero-height, borderless) to force dompdf column widths --}}
            <tr style="height: 0; line-height: 0; font-size: 0;">
                <td style="width:3%; padding:0; border:0; height:0;"></td>
                <td style="width:22%; padding:0; border:0; height:0;"></td>
                <td style="width:3.5%; padding:0; border:0; height:0;"></td>
                <td style="width:3.5%; padding:0; border:0; height:0;"></td>
                <td style="width:3.5%; padding:0; border:0; height:0;"></td>
                <td style="width:3.5%; padding:0; border:0; height:0;"></td>
                <td style="width:3.5%; padding:0; border:0; height:0;"></td>
                <td style="width:3.5%; padding:0; border:0; height:0;"></td>
                <td style="width:2.5%; padding:0; border:0; height:0;"></td>
                <td style="width:2.5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
                <td style="width:2%; padding:0; border:0; height:0;"></td>
            </tr>
            {{-- Header row 1 --}}
            <tr class="f6 b tc">
                <td rowspan="2" style="height: 100px;"><span class="vert">TARIKH</span></td>
                <td rowspan="2">TUGAS ATAU AKTIVITI</td>
                <td colspan="3">MASA DIRANCANG</td>
                <td colspan="3">MASA SEBENAR</td>
                <td rowspan="2"><span class="vert f6">MAKAN(&gt;3 JAM)</span></td>
                <td rowspan="2"><span class="vert">SHIFT</span></td>
                <td colspan="3">KELULUSAN</td>
                <td colspan="4">JENIS OT</td>
                <td colspan="5">PENGIRAAN OT</td>
            </tr>
            {{-- Header row 2 --}}
            <tr class="f6 b tc" style="height: 100px;">
                <td style="height: 100px;"><span class="vert">MULA</span></td>
                <td style="height: 100px;"><span class="vert">TAMAT</span></td>
                <td style="height: 100px;"><span class="vert">JUMLAH</span></td>
                <td style="height: 100px;"><span class="vert">MULA</span></td>
                <td style="height: 100px;"><span class="vert">TAMAT</span></td>
                <td style="height: 100px;"><span class="vert">JUMLAH</span></td>
                <td class="f6">KAKITANGAN<br>/EXEC./<br>ASST.MGR</td>
                <td class="f6">HOD</td>
                <td class="f6">CEO</td>
                <td style="height: 100px;"><span class="vert">NORMAL</span></td>
                <td style="height: 100px;"><span class="vert">TRAINING</span></td>
                <td style="height: 100px;"><span class="vert">KAIZEN</span></td>
                <td style="height: 100px;"><span class="vert">5S</span></td>
                <td class="f6">OT 1</td>
                <td class="f6">OT 2</td>
                <td class="f6">OT 3</td>
                <td class="f6">OT 4</td>
                <td class="f6">OT 5</td>
            </tr>

            {{-- Data rows: 31 days --}}
            @for ($day = 1; $day <= 31; $day++)
                @php
                    $e = $entries->get($day);
                    $isFilled = $e && ($e->project_code_id || $e->planned_start_time || $e->actual_start_time);
                    $tugas = '';
                    if ($e) {
                        $tugas = trim(($e->projectCode ? $e->projectCode->code : '') . ' ' . ($e->project_name ?? ''));
                    }

                    $pStart = $e ? $e->planned_start_time : null;
                    $pEnd   = $e ? $e->planned_end_time   : null;
                    $pHours = $e ? ((float)$e->planned_total_hours ?: \App\Services\OtFormExcelExport::calcHours($pStart, $pEnd)) : 0;
                    $aStart = $e ? $e->actual_start_time : null;
                    $aEnd   = $e ? $e->actual_end_time   : null;
                    $aHours = $e ? ((float)$e->actual_total_hours ?: \App\Services\OtFormExcelExport::calcHours($aStart, $aEnd)) : 0;

                    $totalPlan   += $pHours;
                    $totalActual += $aHours;
                    if ($e && $e->meal_break) $totalMeal++;
                    if ($e && $e->is_shift)   $totalShift++;

                    // OT calc
                    $ot1 = $ot2 = $ot3 = $ot4 = 0.0; $ot5 = 0;
                    if ($aHours > 0 && $e) {
                        $dow = $e->entry_date->dayOfWeek;
                        $isPH = $e->is_public_holiday ?? false;
                        $isRest = in_array($dow, [0,6]);
                        $ot1 = (float)($e->ot_normal_day_hours ?? 0);
                        $ot2 = (float)($e->ot_rest_day_hours ?? 0);
                        $ot3 = (float)($e->ot_rest_day_excess_hours ?? 0);
                        $ot4 = (float)($e->ot_ph_hours ?? 0);
                        $ot5 = (int)($e->ot_rest_day_count ?? 0);
                        if ($ot1 <= 0 && !$isPH && !$isRest) $ot1 = $aHours;
                        if ($ot2 <= 0 && $isRest && !$isPH)  $ot2 = min($aHours, 7.5);
                        if ($ot3 <= 0 && $isRest && !$isPH && $aHours > 7.5) $ot3 = $aHours - 7.5;
                        if ($ot4 <= 0 && $isPH && $aHours > 7.5) $ot4 = $aHours - 7.5;
                        if ($ot5 <= 0 && $isRest && !$isPH) $ot5 = 1;
                        $totalOt1 += $ot1; $totalOt2 += $ot2; $totalOt3 += $ot3; $totalOt4 += $ot4; $totalOt5 += $ot5;
                    }
                    $showStaff = $isFilled && !in_array($otForm->status, ['draft']);
                    $showHod   = $isFilled && in_array($otForm->status, ['pending_gm','approved']) && $hodSignerName;
                    $showGm    = $isFilled && $otForm->status === 'approved' && $gmSignerName;
                @endphp
                <tr class="f6 tc">
                    <td class="b f6">{{ $day }}</td>
                    <td class="tl f6" style="padding-left: 3px;">{{ $tugas }}</td>
                    <td class="f6">{{ $pStart ? substr($pStart, 0, 5) : '' }}</td>
                    <td class="f6">{{ $pEnd   ? substr($pEnd,   0, 5) : '' }}</td>
                    <td class="f6">{{ $pHours > 0 ? number_format($pHours, 2) : '' }}</td>
                    <td class="f6">{{ $aStart ? substr($aStart, 0, 5) : '' }}</td>
                    <td class="f6">{{ $aEnd   ? substr($aEnd,   0, 5) : '' }}</td>
                    <td class="f6">{{ $aHours > 0 ? number_format($aHours, 2) : '' }}</td>
                    <td>{{ $e && $e->meal_break ? '/' : '' }}</td>
                    <td>{{ $e && $e->is_shift   ? '/' : '' }}</td>
                    <td class="blue f6">{{ $showStaff ? $staffShortName : '' }}</td>
                    <td class="blue f6">{{ $showHod   ? $hodSignerName  : '' }}</td>
                    <td class="blue f6">{{ $showGm    ? $gmSignerName   : '' }}</td>
                    <td>{{ $e && $e->jenis_ot_normal   ? '/' : '' }}</td>
                    <td>{{ $e && $e->jenis_ot_training ? '/' : '' }}</td>
                    <td>{{ $e && $e->jenis_ot_kaizen   ? '/' : '' }}</td>
                    <td>{{ $e && $e->jenis_ot_5s       ? '/' : '' }}</td>
                    <td class="f6">{{ $ot1 > 0 ? number_format($ot1, 2) : '' }}</td>
                    <td class="f6">{{ $ot2 > 0 ? number_format($ot2, 2) : '' }}</td>
                    <td class="f6">{{ $ot3 > 0 ? number_format($ot3, 2) : '' }}</td>
                    <td class="f6">{{ $ot4 > 0 ? number_format($ot4, 2) : '' }}</td>
                    <td class="f6">{{ $ot5 > 0 ? $ot5 : '' }}</td>
                </tr>
            @endfor

            {{-- JUMLAH + signature labels + OT totals row --}}
            <tr class="f6 b tc">
                <td class="tl f7 nb" colspan="2" style="padding-left: 4px; border-left: 0 !important; border-bottom: 0 !important;">NOTA :</td>
                <td colspan="2" class="f7 nb" style="border-left: 0 !important; border-bottom: 0 !important;">JUMLAH</td>
                <td class="dbl f7">{{ $totalPlan > 0 ? number_format($totalPlan, 0) : '' }}</td>
                <td colspan="2" class="nb" style="border-bottom: 0 !important;"></td>
                <td class="dbl f7">{{ $totalActual > 0 ? number_format($totalActual, 0) : '' }}</td>
                <td class="dbl f7">{{ $totalMeal > 0 ? $totalMeal : '' }}</td>
                <td class="dbl f7">{{ $totalShift > 0 ? $totalShift : '' }}</td>
                <td class="f5">Disediakan<br>Oleh :</td>
                <td class="f5">Disokong<br>Oleh :</td>
                <td class="f5">Diluluskan<br>Oleh :</td>
                <td colspan="4" class="f6 nb" style="border-bottom: 0 !important;">JUMLAH JAM OT</td>
                <td class="dbl f6">{{ $totalOt1 > 0 ? number_format($totalOt1, 2) : '' }}</td>
                <td class="dbl f6">{{ $totalOt2 > 0 ? number_format($totalOt2, 2) : '' }}</td>
                <td class="dbl f6">{{ $totalOt3 > 0 ? number_format($totalOt3, 2) : '' }}</td>
                <td class="dbl f6">{{ $totalOt4 > 0 ? number_format($totalOt4, 2) : '' }}</td>
                <td class="dbl f6">{{ $totalOt5 > 0 ? $totalOt5 : '' }}</td>
            </tr>

            {{-- NOTA + circular stamps row --}}
            <tr>
                <td colspan="10" class="f6 tl nb" rowspan="2" style="vertical-align: top; padding: 2px 3px;">
                    1)&nbsp; Borang OT mesti sampai ke Jabatan Sumber Manusia (Unit Payroll)<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;selewat-lewatnya pada atau sebelum <b style="text-decoration:underline;">5hb. setiap bulan</b> (bulan berikutnya).
                </td>
                <td style="vertical-align: middle; padding: 2px;">
                    @if($staffStamp['show'])
                        <div class="stamp">
                            <div class="nm">{{ $staffStamp['name'] }}</div>
                            <div class="ds">{{ $staffStamp['designation'] }}</div>
                            <div class="dt">{{ $staffStamp['date'] }}</div>
                        </div>
                    @endif
                </td>
                <td style="vertical-align: middle; padding: 2px;">
                    @if($hodStamp['show'])
                        <div class="stamp">
                            <div class="nm">{{ $hodStamp['name'] }}</div>
                            <div class="ds">{{ $hodStamp['designation'] }}</div>
                            <div class="dt">{{ $hodStamp['date'] }}</div>
                        </div>
                    @endif
                </td>
                <td style="vertical-align: middle; padding: 2px;">
                    @if($gmStamp['show'])
                        <div class="stamp">
                            <div class="nm">{{ $gmStamp['name'] }}</div>
                            <div class="ds">{{ $gmStamp['designation'] }}</div>
                            <div class="dt">{{ $gmStamp['date'] }}</div>
                        </div>
                    @endif
                </td>
                <td colspan="9" class="nb"></td>
            </tr>

            {{-- Role labels row --}}
            <tr class="f6 b tc">
                <td>STAFF</td>
                <td>MGR / HOD</td>
                <td>DGM / CEO</td>
                <td colspan="9" class="nb"></td>
            </tr>
        </table>
        </div>
    </div>
    </div>
    @else
    {{-- OCF (Executive) Layout --}}
    <div class="outer">
        <div class="inner-border">
        {{-- ───── TOP HEADER: INGRESS + DEPARTMENT ───── --}}
        <table>
            <tr>
                <td style="width: 70%; padding: 2mm 0;">
                    <div class="ribbon-title" style="font-size: 12pt;">
                        INGRESS<br>GROUP OF COMPANIES
                    </div>
                </td>
                <td style="width: 30%; padding: 0; vertical-align: top;">
                    <table class="bd">
                        <tr>
                            <td class="f7 tc" style="width: 50%;">DEPARTMENT</td>
                            <td class="f7 b tc" style="width: 50%;">SUMBER MANUSIA</td>
                        </tr>
                        <tr>
                            <td class="f7 tc">DOC NO</td>
                            <td class="f7 tc">ISSUE NO&nbsp;:&nbsp;-</td>
                        </tr>
                        <tr>
                            <td class="f7 tc">PAGE&nbsp;1&nbsp;OF&nbsp;1&nbsp;PAGES</td>
                            <td class="f7 tc">REV NO&nbsp;:&nbsp;0</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ───── TITLE + SERIAL NO ───── --}}
        <table style="border-top: 1pt solid #000; border-bottom: 1pt solid #000; margin-top: 1mm;">
            <tr>
                <td class="f7 b" style="padding: 2px 3px; width: 8%;">TITLE :</td>
                <td class="f8 b tl" style="padding: 2px 0;">OVERTIME CLAIM FORM (EXECUTIVE) ~ OCF</td>
                <td class="f7 b tr" style="padding: 2px 3px; width: 12%;">SERIAL NO.</td>
            </tr>
        </table>

        {{-- ───── Company grid (4 companies) ───── --}}
        <table class="bd" style="margin-top: 1mm;">
            <tr>
                <td class="f6 b tc" style="width: 25%;">INGRESS CORPORATION</td>
                <td class="f6 b tc" style="width: 25%;">INGRESS ENGINEERING</td>
                <td class="f6 b tc" style="width: 25%;">INGRESS PRECISION</td>
                <td class="f6 b tc" style="width: 20%;">TALENT SYNERGY</td>
                <td class="f14 b tc" style="width: 5%;">X</td>
            </tr>
        </table>

        {{-- ───── Info rows: NAME/STAFF NO, DEPARTMENT/SECTION, MONTH ───── --}}
        <table style="margin-top: 1.5mm; table-layout: auto;">
            <tr>
                <td class="lbl" style="width: auto; white-space: nowrap;">NAME</td>
                <td class="colon" style="width: auto;">:</td>
                <td class="ibox" style="width: 30%;">{{ strtoupper($user->name ?? '-') }}</td>
                <td class="lbl" style="width: auto; white-space: nowrap;">DEPARTMENT</td>
                <td class="colon" style="width: auto;">:</td>
                <td class="ibox" style="width: 18%;">{{ strtoupper($dept) }}</td>
                <td class="lbl" style="width: auto; white-space: nowrap;">MONTH</td>
                <td class="colon" style="width: auto;">:</td>
                <td class="ibox" style="width: 14%;">{{ $monthStr }}</td>
            </tr>
            <tr>
                <td class="lbl" style="width: auto; white-space: nowrap;">STAFF NO.</td>
                <td class="colon" style="width: auto;">:</td>
                <td class="ibox" style="width: 30%;">{{ $user->staff_no ?? '-' }}</td>
                <td class="lbl" style="width: auto; white-space: nowrap;">SECTION/LINE</td>
                <td class="colon" style="width: auto;">:</td>
                <td class="ibox">{{ strtoupper($otForm->section_line ?? '-') }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </table>

        {{-- ───── MAIN DATA TABLE (OCF) with A/B/C labels ───── --}}
        <table class="bd" style="margin-top: 1.5mm; width: 100%;">
            {{-- Sizing row --}}
            <tr style="height: 0; line-height: 0; font-size: 0;">
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:29%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:7%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:5%; padding:0; border:0; height:0;"></td>
                <td style="width:6%; padding:0; border:0; height:0;"></td>
                <td style="width:6%; padding:0; border:0; height:0;"></td>
                <td style="width:6%; padding:0; border:0; height:0;"></td>
            </tr>
            {{-- A/B/C labels row --}}
            <tr class="f7 b tc">
                <td colspan="5" style="border-bottom: 0;">A</td>
                <td colspan="3" style="border-bottom: 0;">B</td>
                <td colspan="3" style="border-bottom: 0;"></td>
                <td colspan="3" style="border-bottom: 0;">C</td>
            </tr>
            {{-- Header row 1 --}}
            <tr class="f6 b tc">
                <td rowspan="2">DATE</td>
                <td rowspan="2">PARTICULARS</td>
                <td colspan="3">PLAN</td>
                <td colspan="3">APPROVAL BEFORE OVERTIME</td>
                <td colspan="3">ACTUAL</td>
                <td colspan="3">TOTAL HOURS</td>
            </tr>
            {{-- Header row 2 --}}
            <tr class="f6 b tc" style="height: 100px;">
                <td>START</td>
                <td>END</td>
                <td>TOTAL<br>HOURS</td>
                <td class="f6">EXEC.</td>
                <td class="f6">HOD</td>
                <td class="f6">DGM/<br>CEO</td>
                <td>START</td>
                <td>END</td>
                <td>TOTAL<br>HOURS</td>
                <td>NORMAL<br>DAY</td>
                <td>REST<br>DAY</td>
                <td>PUBLIC<br>HOLIDAY</td>
            </tr>

            {{-- Data rows: 18 rows --}}
            @php
                $filledEntries = $otForm->entries()->with('projectCode')->get()
                    ->filter(fn($e) => $e->project_code_id || $e->planned_start_time || $e->actual_start_time)
                    ->values();
                $rowCount = max($filledEntries->count(), 18);
                $totalPlanOCF = 0; $totalActualOCF = 0; $totalNorm = 0; $totalRest = 0; $totalPH = 0;
            @endphp
            @for ($i = 0; $i < $rowCount; $i++)
                @php
                    $e = $filledEntries[$i] ?? null;
                    $particulars = $e ? trim(($e->projectCode ? $e->projectCode->code : '') . ' ' . ($e->project_name ?? '')) : '';
                    
                    $pStart = $e ? $e->planned_start_time : null;
                    $pEnd = $e ? $e->planned_end_time : null;
                    $pHours = $e ? (float)$e->planned_total_hours : 0;
                    if ($pHours <= 0 && $e && $pStart && $pEnd) {
                        $pHours = \App\Services\OtFormExcelExport::calcHours($pStart, $pEnd);
                    }
                    $totalPlanOCF += $pHours;
                    
                    $aStart = $e ? $e->actual_start_time : null;
                    $aEnd = $e ? $e->actual_end_time : null;
                    $aHours = $e ? (float)$e->actual_total_hours : 0;
                    if ($aHours <= 0 && $e && $aStart && $aEnd) {
                        $aHours = \App\Services\OtFormExcelExport::calcHours($aStart, $aEnd);
                    }
                    $totalActualOCF += $aHours;
                    
                    // OT hours
                    $otNormal = $e ? (float)$e->ot_normal_day_hours : 0;
                    $otRest = $e ? (float)$e->ot_rest_day_hours : 0;
                    $otPH = $e ? (float)$e->ot_ph_hours : 0;
                    if ($aHours > 0 && ($otNormal + $otRest + $otPH) <= 0 && $e) {
                        if ($e->is_public_holiday) {
                            $otPH = $aHours;
                        } elseif ($e->entry_date && $e->entry_date->isWeekend()) {
                            $otRest = $aHours;
                        } else {
                            $otNormal = $aHours;
                        }
                    }
                    $totalNorm += $otNormal;
                    $totalRest += $otRest;
                    $totalPH += $otPH;
                    
                    $isFilled = $e && ($e->project_code_id || $e->planned_start_time || $e->actual_start_time);
                @endphp
                <tr class="f6 tc" style="height: 18px;">
                    <td style="height: 18px;">{{ $e ? $e->entry_date->format('d/m/Y') : '' }}</td>
                    <td class="tl" style="height: 18px; padding-left: 3px;">{{ $particulars ?: '' }}</td>
                    <td>{{ $pStart ? substr($pStart, 0, 5) : '' }}</td>
                    <td>{{ $pEnd ? substr($pEnd, 0, 5) : '' }}</td>
                    <td>{{ $pHours > 0 ? number_format($pHours, 2) : '' }}</td>
                    @if($isFilled && !in_array($otForm->status, ['draft']))
                        <td class="blue f6">{{ $staffShortName }}</td>
                    @else
                        <td></td>
                    @endif
                    @if($isFilled && in_array($otForm->status, ['pending_gm', 'approved']) && $hodSignerName)
                        <td class="blue f6">{{ $hodSignerName }}</td>
                    @else
                        <td></td>
                    @endif
                    @if($isFilled && $otForm->status === 'approved' && $gmSignerName)
                        <td class="blue f6">{{ $gmSignerName }}</td>
                    @else
                        <td></td>
                    @endif
                    <td>{{ $aStart ? substr($aStart, 0, 5) : '' }}</td>
                    <td>{{ $aEnd ? substr($aEnd, 0, 5) : '' }}</td>
                    <td>{{ $aHours > 0 ? number_format($aHours, 2) : '' }}</td>
                    <td>{{ $otNormal > 0 ? number_format($otNormal, 2) : '' }}</td>
                    <td>{{ $otRest > 0 ? number_format($otRest, 2) : '' }}</td>
                    <td>{{ $otPH > 0 ? number_format($otPH, 2) : '' }}</td>
                </tr>
            @endfor

            {{-- Empty row with wider height --}}
            <tr style="height: 70px;">
                <td colspan="14"></td>
            </tr>

            {{-- Totals row --}}
            <tr class="f6 b tc">
                <td class="nb"></td>
                <td class="nb"></td>
                <td colspan="2" class="f7 nb">TOTAL (HOURS)</td>
                <td class="dbl f7">{{ $totalPlanOCF > 0 ? number_format($totalPlanOCF, 2) : '' }}</td>
                <td class="nb"></td>
                <td class="nb"></td>
                <td class="nb"></td>
                <td colspan="2" class="f7 nb">TOTAL (HOURS)</td>
                <td class="dbl f7">{{ $totalActualOCF > 0 ? number_format($totalActualOCF, 2) : '' }}</td>
                <td class="dbl f7">{{ $totalNorm > 0 ? number_format($totalNorm, 2) : '' }}</td>
                <td class="dbl f7">{{ $totalRest > 0 ? number_format($totalRest, 2) : '' }}</td>
                <td class="dbl f7">{{ $totalPH > 0 ? number_format($totalPH, 2) : '' }}</td>
            </tr>
        </table>

        {{-- ───── SIGNATURE SECTION (separate table) ───── --}}
        <table style="margin-top: 2mm; width: 100%;">
            <tr>
                <td style="width: 20%; vertical-align: top;">
                    <table class="bd" style="width: 100%;">
                        <tr>
                            <td colspan="2" class="f7 b tc" style="padding: 2px;">APPROVAL AFTER OVERTIME</td>
                        </tr>
                        <tr>
                            <td class="f7 b tc" style="width: 50%; padding: 2px;">Claimed by</td>
                            <td class="f7 b tc" style="width: 50%; padding: 2px;">Approved by</td>
                        </tr>
                        <tr style="height: 70px;">
                            <td style="vertical-align: middle; padding: 2px;">
                                @if($staffStamp['show'])
                                    <div class="stamp">
                                        <div class="nm">{{ $staffStamp['name'] }}</div>
                                        <div class="ds">STAFF</div>
                                        <div class="dt">{{ $staffStamp['date'] }}</div>
                                    </div>
                                @endif
                            </td>
                            <td style="vertical-align: middle; padding: 2px;">
                                @if($hodStamp['show'])
                                    <div class="stamp">
                                        <div class="nm">{{ $hodStamp['name'] }}</div>
                                        <div class="ds">MGR/HOD</div>
                                        <div class="dt">{{ $hodStamp['date'] }}</div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="f6 b tc">Executive</td>
                            <td class="f6 b tc">HOD</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <table style="width: 100%;">
                        <tr>
                            <td class="f7 b tl" style="padding: 3px;">NOTE :</td>
                        </tr>
                        <tr style="height: 50px;">
                            <td class="f7 tl" style="padding: 3px; line-height: 1.3;">
                                1) Overtime submission should be presented to <b style="text-decoration: underline;">HOD/DGM/MD</b> before 4.30 pm for approval.<br>
                                2) OT claim shall submitted to Payroll section every <b style="text-decoration: underline;">05th of the month</b> and the maximum claim shall not exceed <b style="text-decoration: underline;">RM500.00</b> per month.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        </div>
    </div>
    @endif
    </div>
</body>
</html>
