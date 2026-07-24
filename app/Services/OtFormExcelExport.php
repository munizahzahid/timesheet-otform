<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\OtForm;
use App\Models\OtFormEntry;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class OtFormExcelExport
{
    private const THIN = Border::BORDER_THIN;
    private const MEDIUM = Border::BORDER_MEDIUM;

    public function generate(OtForm $otForm): Spreadsheet
    {
        return $otForm->isExecutive()
            ? $this->buildExecutive($otForm)
            : $this->buildNonExecutive($otForm);
    }

    // ─── NON-EXECUTIVE (BKLM) ────────────────────────────────────────────────

    private function buildNonExecutive(OtForm $otForm): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('BKLM');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.3)->setRight(0.3);
        $sheet->getSheetView()->setZoomScale(85);

        $lastCol = 'AF';

        // Column widths — A is narrow spacer; rest default (~8.43) scaled by fit-to-page
        $sheet->getColumnDimension('A')->setWidth(1);
        $sheet->getColumnDimension('AG')->setWidth(1);

        $user     = $otForm->user;
        $dept     = $user->department->name ?? '-';
        $monthStr = strtoupper(\DateTime::createFromFormat('!m', $otForm->month)->format('F')) . ' ' . $otForm->year;
        $checked  = $otForm->company_name ?? 'TALENT SYNERGY';

        $r = 1;

        // ── Row 1-2: spacer ────────────────────────────────────────────────────────
        
        $r++;
        $sheet->getStyle("A{$r}:AG{$r}")->getBorders()->getTop()->setBorderStyle(self::MEDIUM);
        $this->borders($sheet, "B{$r}:{$lastCol}{$r}", 'bottom');
        $sheet->getRowDimension($r)->setRowHeight(3.75);
        $r++;

        // ── Rows 3-6: KUMPULAN SYARIKAT INGRESS + right info panel ───────────────
        $sheet->mergeCells("G{$r}:Q" . ($r + 3));
        $sheet->setCellValue("G{$r}", "KUMPULAN                   SYARIKAT\nINGRESS");
        $sheet->getStyle("G{$r}")->getFont()->setBold(true)->setSize(26);
        $this->c($sheet, "G{$r}");
        $sheet->getStyle("G{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        // Right: DEPARTMENT | SUMBER MANUSIA
        $sheet->mergeCells("Y{$r}:AA{$r}");
        $sheet->setCellValue("Y{$r}", 'DEPARTMENT');
        $sheet->getStyle("Y{$r}")->getFont()->setSize(10);
        $sheet->mergeCells("AB{$r}:{$lastCol}{$r}");
        $sheet->setCellValue("AB{$r}", 'SUMBER MANUSIA');
        $sheet->getStyle("AB{$r}")->getFont()->setBold(true)->setSize(10);
        $this->c($sheet, "AB{$r}");
        $this->borders($sheet, "Y{$r}:{$lastCol}{$r}");
        $sheet->getRowDimension($r)->setRowHeight(20.25);
        $r++;
        // DOC NO | ISSUE NO
        $sheet->mergeCells("Y{$r}:AA{$r}");
        $sheet->setCellValue("Y{$r}", 'DOC NO');
        $sheet->getStyle("Y{$r}")->getFont()->setSize(10);
        $sheet->mergeCells("AB{$r}:AD{$r}");
        $sheet->setCellValue("AE{$r}", 'ISSUE NO  :      -');
        $sheet->getStyle("AE{$r}")->getFont()->setSize(10);
        $this->borders($sheet, "Y{$r}:{$lastCol}{$r}");
        $sheet->getRowDimension($r)->setRowHeight(20.25);
        $r++;
        // PAGE / REV NO
        $sheet->mergeCells("Y{$r}:AD{$r}");
        $sheet->setCellValue("Y{$r}", 'PAGE            1            OF              1           PAGES'); $sheet->getStyle("Y{$r}")->getFont()->setSize(10);
        $sheet->setCellValue("AE{$r}", 'REV NO     :     0');
        $sheet->getStyle("AE{$r}")->getFont()->setSize(10);
        $this->borders($sheet, "Y{$r}:{$lastCol}{$r}");
        $sheet->getRowDimension($r)->setRowHeight(18.75);
        $r++;
        // End of header merge
        $sheet->getRowDimension($r)->setRowHeight(4.5);
        $r++;

        // ── Row 7: TAJUK ─────────────────────────────────────────────────────────
        $sheet->setCellValue("B{$r}", 'TAJUK : ');
        $sheet->getStyle("B{$r}")->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue("E{$r}", 'BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF)');
        $sheet->getStyle("E{$r}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $this->borders($sheet, "B{$r}:{$lastCol}{$r}", 'top');
        $this->borders($sheet, "B{$r}:{$lastCol}{$r}", 'bottom');
        $sheet->getRowDimension($r)->setRowHeight(23.25);
        $r++;

        // ── Rows 8-9: Company logo ───────────────────────────────────────────────
        $logoPath = public_path('images/Logo TSSB.jpeg');
        if (file_exists($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Company Logo');
            $drawing->setDescription('Talent Synergy Logo');
            $drawing->setPath($logoPath);
            $drawing->setHeight(35);
            $drawing->setCoordinates("B{$r}");
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet);
        }
        $sheet->mergeCells("B{$r}:AF" . ($r + 1));
        $sheet->getRowDimension($r)->setRowHeight(20);
        $sheet->getRowDimension($r + 1)->setRowHeight(20);
        $r += 2;

        // ── Rows 10-11: spacers ───────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(4.5); $r++;
        $sheet->getRowDimension($r)->setRowHeight(12); $r++;

        // ── Row 12: NAMA, JABATAN, BULAN ─────────────────────────────────────────
        $sheet->setCellValue("C{$r}", 'NAMA'); $this->b($sheet, "C{$r}");
        $sheet->setCellValue("D{$r}", ':'); $this->b($sheet, "D{$r}");
        $sheet->mergeCells("E{$r}:M{$r}"); $sheet->setCellValue("E{$r}", strtoupper($user->name ?? '-'));
        $this->borders($sheet, "E{$r}:M{$r}");
        $sheet->setCellValue("Q{$r}", 'JABATAN'); $this->b($sheet, "Q{$r}");
        $sheet->setCellValue("S{$r}", ':'); $this->b($sheet, "S{$r}");
        $sheet->mergeCells("T{$r}:W{$r}"); $sheet->setCellValue("T{$r}", strtoupper($dept));
        $this->borders($sheet, "T{$r}:W{$r}");
        $sheet->setCellValue("AB{$r}", 'BULAN'); $this->b($sheet, "AB{$r}");
        $sheet->setCellValue("AC{$r}", ':'); $this->b($sheet, "AC{$r}");
        $sheet->mergeCells("AD{$r}:{$lastCol}{$r}"); $sheet->setCellValue("AD{$r}", $monthStr);
        $this->borders($sheet, "AD{$r}:{$lastCol}{$r}");
        $sheet->getRowDimension($r)->setRowHeight(16);
        $r++;

        // ── Row 13: spacer ───────────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(6);
        $r++;

        // ── Row 14: NO.KT, JAWATAN, SEKSYEN/BAHG. ───────────────────────────────
        $sheet->setCellValue("C{$r}", 'NO. KT'); $this->b($sheet, "C{$r}");
        $sheet->setCellValue("D{$r}", ':'); $this->b($sheet, "D{$r}");
        $sheet->mergeCells("E{$r}:G{$r}"); $sheet->setCellValue("E{$r}", $user->staff_no ?? '-');
        $this->borders($sheet, "E{$r}:G{$r}");
        $sheet->mergeCells("H{$r}:I{$r}");
        $sheet->setCellValue("H{$r}", 'JAWATAN'); $this->b($sheet, "H{$r}");
        $sheet->setCellValue("J{$r}", ':'); $this->b($sheet, "J{$r}");
        $sheet->mergeCells("K{$r}:M{$r}"); $sheet->setCellValue("K{$r}", strtoupper($user->designation ?? '-'));
        $this->borders($sheet, "K{$r}:M{$r}");
        $sheet->setCellValue("Q{$r}", 'SEKSYEN/BAHG.'); $this->b($sheet, "Q{$r}");
        $sheet->setCellValue("S{$r}", ':'); $this->b($sheet, "S{$r}");
        $sheet->mergeCells("T{$r}:W{$r}"); $sheet->setCellValue("T{$r}", strtoupper($otForm->section_line ?? '-'));
        $this->borders($sheet, "T{$r}:W{$r}");
        $sheet->getRowDimension($r)->setRowHeight(16);
        $r++;

        // ── Row 15: spacer ───────────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(4.5);
        $r++;

        // ── Rows 16-17: Column headers (2 rows) ─────────────────────────────────
        $hr = $r;

        // Row 16: Group headers
        $sheet->mergeCells("B{$hr}:B" . ($hr + 1)); $sheet->setCellValue("B{$hr}", 'TARIKH');
        $this->vt($sheet, "B{$hr}");
        $sheet->mergeCells("C{$hr}:H" . ($hr + 1)); $sheet->setCellValue("C{$hr}", 'TUGAS ATAU AKTIVITI');
        $sheet->mergeCells("I{$hr}:K{$hr}"); $sheet->setCellValue("I{$hr}", 'MASA DIRANCANG');
        $sheet->mergeCells("L{$hr}:N{$hr}"); $sheet->setCellValue("L{$hr}", 'MASA SEBENAR');
        $sheet->mergeCells("O{$hr}:O" . ($hr + 1)); $sheet->setCellValue("O{$hr}", "MAKAN\n(> 3 JAM)");
        $this->vt($sheet, "O{$hr}");
        $sheet->mergeCells("P{$hr}:P" . ($hr + 1)); $sheet->setCellValue("P{$hr}", 'SHIFT');
        $this->vt($sheet, "P{$hr}");
        $sheet->mergeCells("Q{$hr}:V{$hr}"); $sheet->setCellValue("Q{$hr}", 'KELULUSAN');
        $sheet->mergeCells("W{$hr}:AA{$hr}"); $sheet->setCellValue("W{$hr}", 'JENIS OT');
        $sheet->mergeCells("AB{$hr}:{$lastCol}{$hr}"); $sheet->setCellValue("AB{$hr}", 'PENGIRAAN OT');
        $this->b($sheet, "B{$hr}:{$lastCol}{$hr}");
        $this->c($sheet, "B{$hr}:{$lastCol}{$hr}");
        $this->borders($sheet, "B{$hr}:{$lastCol}{$hr}");
        $sheet->getStyle("B{$hr}:{$lastCol}{$hr}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("B{$hr}:{$lastCol}{$hr}")->getFont()->setSize(10);
        $sheet->getRowDimension($hr)->setRowHeight(20.25);
        $r++;

        // Row 17: Sub-headers
        $sheet->setCellValue("I{$r}", 'MULA');   $this->vt($sheet, "I{$r}");
        $sheet->setCellValue("J{$r}", 'TAMAT');  $this->vt($sheet, "J{$r}");
        $sheet->setCellValue("K{$r}", 'JUMLAH'); $this->vt($sheet, "K{$r}");
        $sheet->setCellValue("L{$r}", 'MULA');   $this->vt($sheet, "L{$r}");
        $sheet->setCellValue("M{$r}", 'TAMAT');  $this->vt($sheet, "M{$r}");
        $sheet->setCellValue("N{$r}", 'JUMLAH'); $this->vt($sheet, "N{$r}");
        $sheet->mergeCells("Q{$r}:R{$r}"); $sheet->setCellValue("Q{$r}", "KAKITANGAN\n/EXEC./ASST.MGR");
        $sheet->getStyle("Q{$r}")->getFont()->setSize(8);
        $sheet->getStyle("Q{$r}")->getAlignment()->setWrapText(true);
        $sheet->mergeCells("S{$r}:T{$r}"); $sheet->setCellValue("S{$r}", 'HOD');
        $sheet->mergeCells("U{$r}:V{$r}"); $sheet->setCellValue("U{$r}", 'CEO');
        $sheet->setCellValue("W{$r}", 'NORMAL');   $this->vt($sheet, "W{$r}");
        $sheet->setCellValue("X{$r}", 'TRAINING'); $this->vt($sheet, "X{$r}");
        $sheet->setCellValue("Y{$r}", 'KAIZEN');   $this->vt($sheet, "Y{$r}");
        $sheet->setCellValue("Z{$r}", '5S');       $this->vt($sheet, "Z{$r}");
        $sheet->setCellValue("AB{$r}", 'OT 1');
        $sheet->setCellValue("AC{$r}", 'OT 2');
        $sheet->setCellValue("AD{$r}", 'OT 3');
        $sheet->setCellValue("AE{$r}", 'OT 4');
        $sheet->setCellValue("{$lastCol}{$r}", 'OT 5');
        $this->b($sheet, "B{$r}:{$lastCol}{$r}");
        $this->c($sheet, "B{$r}:{$lastCol}{$r}");
        $this->borders($sheet, "B{$r}:{$lastCol}{$r}");
        $sheet->getStyle("B{$r}:{$lastCol}{$r}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("B{$r}:{$lastCol}{$r}")->getFont()->setSize(10);
        $sheet->getRowDimension($r)->setRowHeight(55.5);
        $r++;

        // ── Data rows (31 days) ──────────────────────────────────────────────────
        $allEntries = $otForm->entries()->with('projectCode')->get()
            ->groupBy(fn($e) => $e->entry_date->format('Y-m-d'));

        // Fetch approval logs — HOD=level2, GM=level1 (per OtApprovalController)
        $logs = ApprovalLog::where('approvable_type', 'ot_form')
            ->where('approvable_id', $otForm->id)
            ->where('action', 'approved')
            ->with('approver')
            ->orderBy('level')
            ->get();
        $staffSignerName = $this->shortName($user);
        $staffDesignation = $user->designation ?? '';
        $hodLog = $logs->where('level', 2)->first();
        $hodSignerName = ($hodLog && $hodLog->approver) ? $this->shortName($hodLog->approver) : '';
        $hodDesignation = ($hodLog && $hodLog->approver) ? ($hodLog->approver->designation ?? '') : '';
        $gmLog = $logs->where('level', 1)->first();
        $gmSignerName = ($gmLog && $gmLog->approver) ? $this->shortName($gmLog->approver) : '';
        $gmDesignation = ($gmLog && $gmLog->approver) ? ($gmLog->approver->designation ?? '') : '';

        // Fallback: use designated approvers if no approval logs exist
        $formUser = $otForm->user;
        if (!$hodSignerName && in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved'])) {
            if ($formUser->ot_approver_id) {
                $hodApprover = \App\Models\User::find($formUser->ot_approver_id);
            }
            // Legacy fallback for old fields
            if (!isset($hodApprover) && $otForm->form_type === 'executive' && $formUser->ot_exec_approver_id) {
                $hodApprover = \App\Models\User::find($formUser->ot_exec_approver_id);
            } elseif (!isset($hodApprover) && $otForm->form_type === 'non_executive' && $formUser->ot_non_exec_approver_id) {
                $hodApprover = \App\Models\User::find($formUser->ot_non_exec_approver_id);
            }
            if (isset($hodApprover)) {
                $hodSignerName = $this->shortName($hodApprover);
                $hodDesignation = $hodApprover->designation ?? '';
            }
        }
        if (!$gmSignerName && $otForm->status === 'approved') {
            if ($formUser->ot_final_approver_id) {
                $gmApprover = \App\Models\User::find($formUser->ot_final_approver_id);
            }
            // Legacy fallback for old fields
            if (!isset($gmApprover) && $otForm->form_type === 'executive' && $formUser->ot_exec_final_approver_id) {
                $gmApprover = \App\Models\User::find($formUser->ot_exec_final_approver_id);
            } elseif (!isset($gmApprover) && $otForm->form_type === 'non_executive' && $formUser->ot_non_exec_final_approver_id) {
                $gmApprover = \App\Models\User::find($formUser->ot_non_exec_final_approver_id);
            }
            if (isset($gmApprover)) {
                $gmSignerName = $this->shortName($gmApprover);
                $gmDesignation = $gmApprover->designation ?? '';
            }
        }
        $blueFont = ['font' => ['size' => 8, 'bold' => true, 'color' => ['argb' => 'FF002060']]];

        // Tracking totals
        $totalPlan = 0; $totalActual = 0;
        $totalMeal = 0; $totalShift = 0;
        $totalOt1 = 0; $totalOt2 = 0; $totalOt3 = 0; $totalOt4 = 0; $totalOt5 = 0;

        for ($day = 1; $day <= 31; $day++) {
            $dateStr = \Carbon\Carbon::create($otForm->year, $otForm->month, $day)->format('Y-m-d');
            $dayEntries = $allEntries->get($dateStr, collect());
            foreach ($dayEntries as $e) {
                $isFilled = $e && ($e->project_code_id || $e->planned_start_time || $e->actual_start_time);
                $tugas = $e ? trim(($e->projectCode ? $e->projectCode->code : '') . ' ' . ($e->project_name ?? '')) : '';

                $sheet->setCellValue("B{$r}", $day);
                $sheet->getStyle("B{$r}")->getFont()->setBold(true)->setSize(12);
                $sheet->mergeCells("C{$r}:H{$r}");
                $sheet->setCellValue("C{$r}", $tugas);
                $sheet->getStyle("C{$r}")->getAlignment()->setWrapText(true);

                // Planned times & hours (calc from times if stored value is 0)
                $pStart = $e ? $e->planned_start_time : null;
                $pEnd   = $e ? $e->planned_end_time : null;
                $pHours = $e ? ((float)$e->planned_total_hours ?: $this->calcHours($pStart, $pEnd)) : 0;
                $sheet->setCellValue("I{$r}", $this->correctedTimeCell($e, 'planned_start_time') ?? ($pStart ? substr($pStart, 0, 5) : ''));
                $sheet->setCellValue("J{$r}", $this->correctedTimeCell($e, 'planned_end_time') ?? ($pEnd ? substr($pEnd, 0, 5) : ''));
                $sheet->getStyle("I{$r}:J{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->setCellValue("K{$r}", $pHours > 0 ? number_format($pHours, 2) : '');

                // Actual times & hours
                $aStart = $e ? $e->actual_start_time : null;
                $aEnd   = $e ? $e->actual_end_time : null;
                $aHours = $e ? ((float)$e->actual_total_hours ?: $this->calcHours($aStart, $aEnd)) : 0;
                $aHours = floor($aHours * 4) / 4;
                $sheet->setCellValue("L{$r}", $this->correctedTimeCell($e, 'actual_start_time') ?? ($aStart ? substr($aStart, 0, 5) : ''));
                $sheet->setCellValue("M{$r}", $this->correctedTimeCell($e, 'actual_end_time') ?? ($aEnd ? substr($aEnd, 0, 5) : ''));
                $sheet->getStyle("L{$r}:M{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->setCellValue("N{$r}", $aHours > 0 ? number_format($aHours, 2) : '');

                $totalPlan += $pHours;
                $totalActual += $aHours;

                // Meal & Shift
                $sheet->setCellValue("O{$r}", $e && $e->meal_break ? '/' : '');
                $sheet->setCellValue("P{$r}", $e && $e->is_shift ? '/' : '');
                if ($e && $e->meal_break) $totalMeal++;
                if ($e && $e->is_shift) $totalShift++;

                // KELULUSAN: signer short name in dark blue (merged Q-R, S-T, U-V)
                $sheet->mergeCells("Q{$r}:R{$r}");
                $sheet->mergeCells("S{$r}:T{$r}");
                $sheet->mergeCells("U{$r}:V{$r}");
                if ($isFilled && !in_array($otForm->status, ['draft'])) {
                    $sheet->setCellValue("Q{$r}", $staffSignerName);
                    $sheet->getStyle("Q{$r}")->applyFromArray($blueFont);
                }
                if ($isFilled && in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved']) && $hodSignerName) {
                    $sheet->setCellValue("S{$r}", $hodSignerName);
                    $sheet->getStyle("S{$r}")->applyFromArray($blueFont);
                }
                if ($isFilled && $otForm->status === 'approved' && $gmSignerName) {
                    $sheet->setCellValue("U{$r}", $gmSignerName);
                    $sheet->getStyle("U{$r}")->applyFromArray($blueFont);
                }

                // JENIS OT (W-Z)
                $sheet->setCellValue("W{$r}", $e && $e->jenis_ot_normal   ? '/' : '');
                $sheet->setCellValue("X{$r}", $e && $e->jenis_ot_training ? '/' : '');
                $sheet->setCellValue("Y{$r}", $e && $e->jenis_ot_kaizen   ? '/' : '');
                $sheet->setCellValue("Z{$r}", $e && $e->jenis_ot_5s       ? '/' : '');

                // PENGIRAAN OT: AB=OT1, AC=OT2, AD=OT3, AE=OT4, AF=OT5
                // Use stored values if available, otherwise calculate
                 // Non-exec: weekend first 7.5h to OT2, PH first 7.5h to OT2, excess to OT4
                if ($aHours > 0 && $e) {
                    $dow = $e->entry_date->dayOfWeek; // 0=Sun, 6=Sat
                    $isPH = $e->is_public_holiday ?? false;
                    $isRestDay = in_array($dow, [0, 6]);

                    // Use stored OT values if available
                    $ot1 = (float)($e->ot_normal_day_hours ?? 0);
                    $ot2 = (float)($e->ot_rest_day_hours ?? 0);
                    $ot3 = (float)($e->ot_rest_day_excess_hours ?? 0);
                    $ot4 = (float)($e->ot_ph_hours ?? 0);
                    $ot5 = (int)($e->ot_rest_day_count ?? 0);

                    if ($ot1 > 0) {
                        $sheet->setCellValue("AB{$r}", number_format($ot1, 2));
                        $totalOt1 += $ot1;
                    } elseif (!$isPH && !$isRestDay) {
                        // Normal day: OT1 = all hours
                        $sheet->setCellValue("AB{$r}", number_format($aHours, 2));
                        $totalOt1 += $aHours;
                    }

                    if ($isRestDay && !$isPH) {
                        // Weekend: OT2 = first 7.5h, OT3 = excess, OT5 = 1
                        if ($ot2 > 0) {
                            $sheet->setCellValue("AC{$r}", number_format($ot2, 2));
                            $totalOt2 += $ot2;
                        } else {
                            $ot2h = min($aHours, 7.5);
                            $sheet->setCellValue("AC{$r}", number_format($ot2h, 2));
                            $totalOt2 += $ot2h;
                        }
                        if ($ot3 > 0) {
                            $sheet->setCellValue("AD{$r}", number_format($ot3, 2));
                            $totalOt3 += $ot3;
                        } elseif ($aHours > 7.5) {
                            $ot3h = max($aHours - 7.5, 0);
                            $sheet->setCellValue("AD{$r}", number_format($ot3h, 2));
                            $totalOt3 += $ot3h;
                        }
                        if ($ot5 > 0) {
                            $sheet->setCellValue("{$lastCol}{$r}", $ot5);
                            $totalOt5 += $ot5;
                        } else {
                            $sheet->setCellValue("{$lastCol}{$r}", '1');
                            $totalOt5 += 1;
                        }
                    }

                    if ($isPH) {
                        // Public holiday: OT2 = first 7.5h, OT4 = excess
                        if ($ot2 > 0) {
                            $sheet->setCellValue("AC{$r}", number_format($ot2, 2));
                            $totalOt2 += $ot2;
                        } else {
                            $ot2h = min($aHours, 7.5);
                            $sheet->setCellValue("AC{$r}", number_format($ot2h, 2));
                            $totalOt2 += $ot2h;
                        }
                        if ($ot4 > 0) {
                            $sheet->setCellValue("AE{$r}", number_format($ot4, 2));
                            $totalOt4 += $ot4;
                        } elseif ($aHours > 7.5) {
                            $ot4h = max($aHours - 7.5, 0);
                            $sheet->setCellValue("AE{$r}", number_format($ot4h, 2));
                            $totalOt4 += $ot4h;
                        }
                    }
                }

                // Row styling
                $this->c($sheet, "B{$r}:{$lastCol}{$r}");
                $this->borders($sheet, "B{$r}:{$lastCol}{$r}");
                $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("B{$r}:{$lastCol}{$r}")->getFont()->setSize(10);
                $sheet->getRowDimension($r)->setRowHeight($day <= 3 ? 24 : 18);
                $r++;
            }
        }

        // ── Spacer before footer ─────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(4.5);
        $r++;

        // ── JUMLAH row + signature labels ────────────────────────────────────────
        $sheet->mergeCells("B{$r}:H{$r}");
        $sheet->setCellValue("B{$r}", 'NOTA :');
        $this->b($sheet, "B{$r}"); $sheet->getStyle("B{$r}")->getFont()->setSize(14);
        $sheet->mergeCells("I{$r}:J{$r}");
        $sheet->setCellValue("I{$r}", 'JUMLAH');
        $this->b($sheet, "I{$r}"); $this->c($sheet, "I{$r}");
        $sheet->getStyle("I{$r}")->getFont()->setSize(14);
        // Plan total (double border)
        $sheet->setCellValue("K{$r}", $totalPlan > 0 ? number_format($totalPlan, 2) : '');
        $this->doubleBorders($sheet, "K{$r}"); $this->c($sheet, "K{$r}");
        // Actual total (double border)
        $sheet->setCellValue("N{$r}", $totalActual > 0 ? number_format($totalActual, 2) : '');
        $this->doubleBorders($sheet, "N{$r}"); $this->c($sheet, "N{$r}");
        // Meal & Shift totals
        $sheet->setCellValue("O{$r}", $totalMeal > 0 ? $totalMeal : '');
        $this->doubleBorders($sheet, "O{$r}"); $this->c($sheet, "O{$r}");
        $sheet->setCellValue("P{$r}", $totalShift > 0 ? $totalShift : '');
        $this->doubleBorders($sheet, "P{$r}"); $this->c($sheet, "P{$r}");
        // Signature labels in KELULUSAN area (merged Q-R, S-T, U-V)
        $sheet->mergeCells("Q{$r}:R{$r}");
        $sheet->setCellValue("Q{$r}", 'Disediakan Oleh :');
        $this->b($sheet, "Q{$r}"); $this->c($sheet, "Q{$r}"); $this->borders($sheet, "Q{$r}:R{$r}");
        $sheet->mergeCells("S{$r}:T{$r}");
        $sheet->setCellValue("S{$r}", 'Disokong Oleh :');
        $this->b($sheet, "S{$r}"); $this->c($sheet, "S{$r}"); $this->borders($sheet, "S{$r}:T{$r}");
        $sheet->mergeCells("U{$r}:V{$r}");
        $sheet->setCellValue("U{$r}", 'Diluluskan Oleh :');
        $this->b($sheet, "U{$r}"); $this->c($sheet, "U{$r}"); $this->borders($sheet, "U{$r}:V{$r}");
        $sheet->getStyle("Q{$r}:V{$r}")->getFont()->setSize(9);
        // JUMLAH JAM OT label + OT totals (AB-AF)
        $sheet->mergeCells("W{$r}:AA{$r}");
        $sheet->setCellValue("W{$r}", 'JUMLAH JAM OT');
        $this->b($sheet, "W{$r}"); $this->c($sheet, "W{$r}");
        $sheet->getStyle("W{$r}")->getFont()->setSize(14);
        $otTotals = [$totalOt1, $totalOt2, $totalOt3, $totalOt4, $totalOt5];
        $otCols = ['AB','AC','AD','AE',$lastCol];
        foreach ($otCols as $idx => $oc) {
            $val = $otTotals[$idx];
            $sheet->setCellValue("{$oc}{$r}", $val > 0 ? ($idx === 4 ? (int)$val : number_format($val, 2)) : '');
            $this->doubleBorders($sheet, "{$oc}{$r}"); $this->c($sheet, "{$oc}{$r}");
        }
        $sheet->getRowDimension($r)->setRowHeight(23.25);
        $r++;

        // ── Stamp boxes (3 rows) under KELULUSAN ─────────────────────────────────
        $ss1 = $r; $ss3 = $r + 2;

        // Note text (left side)
        $noteEnd = $ss3 + 1;
        $sheet->mergeCells("B{$ss1}:P{$noteEnd}");
        $noteRun = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $run1 = $noteRun->createTextRun("1)  Borang OT mesti sampai ke Jabatan Sumber Manusia (Unit Payroll)\n     selewat-lewatnya pada atau sebelum ");
        $run1->getFont()->setSize(12);
        $run2 = $noteRun->createTextRun('5hb. setiap bulan');
        $run2->getFont()->setSize(12)->setBold(true)->setUnderline(true);
        $run3 = $noteRun->createTextRun(' (bulan berikutnya).');
        $run3->getFont()->setSize(12);
        $sheet->setCellValue("B{$ss1}", $noteRun);
        $sheet->getStyle("B{$ss1}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        // Stamp boxes (merged Q-R, S-T, U-V spanning 3 rows)
        $sheet->mergeCells("Q{$ss1}:R{$ss3}"); $this->borders($sheet, "Q{$ss1}:R{$ss3}");
        $sheet->mergeCells("S{$ss1}:T{$ss3}"); $this->borders($sheet, "S{$ss1}:T{$ss3}");
        $sheet->mergeCells("U{$ss1}:V{$ss3}"); $this->borders($sheet, "U{$ss1}:V{$ss3}");

        // Staff stamp (DISEDIAKAN)
        if (!in_array($otForm->status, ['draft'])) {
            $staffDate = $otForm->plan_submitted_at ? $otForm->plan_submitted_at->format('d/m/Y') : '';
            $this->addStamp($sheet, "Q{$ss1}", $this->shortName($user), $staffDate, 'STAFF', $staffDesignation);
        }

        // HOD stamp (DISOKONG) — level 2, with fallback
        $hodStampName = null; $hodStampDate = ''; $hodStampDesignation = $hodDesignation;
        if ($hodLog && $hodLog->approver) {
            $hodStampName = $this->shortName($hodLog->approver);
            $hodStampDate = $hodLog->acted_at ? $hodLog->acted_at->format('d/m/Y') : '';
        } elseif (in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved']) && isset($hodApprover)) {
            $hodStampName = $this->shortName($hodApprover);
            $hodStampDesignation = $hodApprover->designation ?? '';
        }
        if ($hodStampName) {
            $this->addStamp($sheet, "S{$ss1}", $hodStampName, $hodStampDate ?: '', 'MGR/HOD', $hodStampDesignation);
        }

        // GM stamp (DILULUSKAN) — level 1, with fallback
        $gmStampName = null; $gmStampDate = ''; $gmStampDesignation = $gmDesignation;
        if ($gmLog && $gmLog->approver) {
            $gmStampName = $this->shortName($gmLog->approver);
            $gmStampDate = $gmLog->acted_at ? $gmLog->acted_at->format('d/m/Y') : '';
        } elseif ($otForm->status === 'approved' && isset($gmApprover)) {
            $gmStampName = $this->shortName($gmApprover);
            $gmStampDesignation = $gmApprover->designation ?? '';
        }
        if ($gmStampName) {
            $this->addStamp($sheet, "U{$ss1}", $gmStampName, $gmStampDate ?: '', 'DGM/CEO', $gmStampDesignation);
        }

        $sheet->getRowDimension($ss1)->setRowHeight(70);
        $sheet->getRowDimension($ss1 + 1)->setRowHeight(20);
        $sheet->getRowDimension($ss3)->setRowHeight(20);
        $r = $ss3 + 1;

        // ── Role labels under stamp boxes ────────────────────────────────────────
        $sheet->mergeCells("Q{$r}:R{$r}");
        $sheet->setCellValue("Q{$r}", 'STAFF');     $this->b($sheet, "Q{$r}"); $this->c($sheet, "Q{$r}"); $this->borders($sheet, "Q{$r}:R{$r}");
        $sheet->mergeCells("S{$r}:T{$r}");
        $sheet->setCellValue("S{$r}", 'MGR / HOD'); $this->b($sheet, "S{$r}"); $this->c($sheet, "S{$r}"); $this->borders($sheet, "S{$r}:T{$r}");
        $sheet->mergeCells("U{$r}:V{$r}");
        $sheet->setCellValue("U{$r}", 'DGM / CEO'); $this->b($sheet, "U{$r}"); $this->c($sheet, "U{$r}"); $this->borders($sheet, "U{$r}:V{$r}");
        $sheet->getRowDimension($r)->setRowHeight(16.5);

        $r = $r + 2;

        $sheet->getStyle("B{$r}:{$lastCol}{$r}")->getBorders()->getTop()->setBorderStyle(self::THIN);
        $sheet->getStyle("A{$r}:AG{$r}")->getBorders()->getBottom()->setBorderStyle(self::MEDIUM);
        $sheet->getRowDimension($r)->setRowHeight(5);

        // ── Add outer medium borders (left of A, right of AG) from row 1 to last row ───
        $lastRow = $r;
        $sheet->getStyle("A2:A{$lastRow}")->getBorders()->getLeft()->setBorderStyle(self::MEDIUM);
        $sheet->getStyle("AG2:AG{$lastRow}")->getBorders()->getRight()->setBorderStyle(self::MEDIUM);

        // ── Add inner thin borders (left of B, left of AG) from row 2 to lastrow-1 ───
        $sheet->getStyle("B3:B" . ($lastRow - 1))->getBorders()->getLeft()->setBorderStyle(self::THIN);
        $sheet->getStyle("AG3:AG" . ($lastRow - 1))->getBorders()->getLeft()->setBorderStyle(self::THIN);

        return $ss;

    }

    // ─── EXECUTIVE (OCF) ─────────────────────────────────────────────────────

    private function buildExecutive(OtForm $otForm): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('OCF');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.3)->setRight(0.3);
        $sheet->getSheetView()->setZoomScale(85);

        // Column widths matching reference layout
        foreach ([
            'A' => 1, 'B' => 1, 'C' => 12, 'D' => 14, 'E' => 14, 'F' => 12, 'G' => 12,
            'H' => 8.5, 'I' => 8.5, 'J' => 8.5, 'K' => 10, 'L' => 10, 'M' => 10,
            'N' => 9, 'O' => 9, 'P' => 9, 'Q' => 2, 'R' => 11, 'S' => 11, 'T' => 10,
            'U' => 8, 'V' => 1,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $user    = $otForm->user;
        $dept    = $user->department->name ?? '-';
        $month   = strtoupper(\DateTime::createFromFormat('!m', $otForm->month)->format('F')) . ' ' . $otForm->year;

        $r = 1;

        // ── Row 1-2: spacers ────────────────────────────────────────────────────
        $sheet->mergeCells("B{$r}:V{$r}");
        $sheet->getStyle("B{$r}:V{$r}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getRowDimension($r)->setRowHeight(10); $r++;
        $sheet->mergeCells("B{$r}:U{$r}");
        $this->borders($sheet, "C{$r}:U{$r}", 'bottom');
        $sheet->getStyle("B{$r}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("C{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
        $sheet->getStyle("V{$r}")->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getRowDimension($r)->setRowHeight(5);  $r++;

        // ── Row 3: INGRESS + DEPARTMENT / SUMBER MANUSIA ────────────────────────
        $sheet->mergeCells("C{$r}:O{$r}");
        $sheet->setCellValue("C{$r}", 'INGRESS');
        $sheet->getStyle("C{$r}")->getFont()->setBold(true)->setSize(16);
        $this->c($sheet, "C{$r}");
        $sheet->mergeCells("P{$r}:R{$r}");
        $sheet->setCellValue("P{$r}", 'DEPARTMENT');
        $sheet->getStyle("P{$r}")->getFont()->setSize(10);
        $sheet->mergeCells("S{$r}:U{$r}");
        $sheet->setCellValue("S{$r}", 'SUMBER MANUSIA');
        $sheet->getStyle("S{$r}")->getFont()->setBold(true)->setSize(10);
        $this->c($sheet, "S{$r}");
        $this->borders($sheet, "P{$r}:U{$r}");
        $sheet->getRowDimension($r)->setRowHeight(16.5);
        $r++;

        // ── Row 4: GROUP OF COMPANIES + DOC NO / ISSUE NO ───────────────────────
        $sheet->mergeCells("C{$r}:O{$r}");
        $sheet->setCellValue("C{$r}", 'GROUP OF COMPANIES');
        $sheet->getStyle("C{$r}")->getFont()->setBold(true)->setSize(16);
        $this->c($sheet, "C{$r}");
        $sheet->mergeCells("P{$r}:S{$r}");
        $sheet->setCellValue("P{$r}", 'DOC NO');
        $sheet->getStyle("P{$r}")->getFont()->setSize(10);
        $sheet->setCellValue("T{$r}", 'ISSUE NO  :');
        $sheet->getStyle("T{$r}")->getFont()->setSize(10);
        $this->borders($sheet, "P{$r}:U{$r}");
        $sheet->getRowDimension($r)->setRowHeight(16.5);
        $r++;

        // ── Row 5: PAGE / REV NO ────────────────────────────────────────────────
        $sheet->mergeCells("P{$r}:S{$r}");
        $sheet->setCellValue("P{$r}", 'PAGE           1             OF              1             PAGES');
        $sheet->getStyle("P{$r}")->getFont()->setSize(10);
        $sheet->setCellValue("T{$r}", 'REV NO     :');
        $sheet->getStyle("T{$r}")->getFont()->setSize(10);
        $this->borders($sheet, "P{$r}:U{$r}");
        $sheet->getRowDimension($r)->setRowHeight(16.5);
        $r++;

        // ── Row 6: spacer ───────────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(9);
        $r++;

        // ── Row 7: TITLE + SERIAL NO. ───────────────────────────────────────────
        $sheet->setCellValue("C{$r}", 'TITLE :');
        $sheet->getStyle("C{$r}")->getFont()->setBold(true)->setSize(12);
        $sheet->setCellValue("E{$r}", 'OVERTIME CLAIM FORM  (EXECUTIVE) ~ OCF');
        $sheet->getStyle("E{$r}")->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue("T{$r}", 'SERIAL NO.');
        $sheet->getStyle("T{$r}")->getFont()->setSize(10);
        $this->borders($sheet, "C{$r}:U{$r}", 'top');
        $this->borders($sheet, "C{$r}:U{$r}", 'bottom');
        $sheet->getRowDimension($r)->setRowHeight(19.5);
        $r++;

        // ── Row 8: SPACER ────────────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(10.5); 
        $r++;

        // ── Rows 9-10: Company logo ───────────────────────────────────────────────
        $logoPath = public_path('images/Logo TSSB.jpeg');
        if (file_exists($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Company Logo');
            $drawing->setDescription('Talent Synergy Logo');
            $drawing->setPath($logoPath);
            $drawing->setHeight(35);
            $drawing->setCoordinates("C{$r}");
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet);
        }
        $sheet->mergeCells("C{$r}:T" . ($r + 1));
        $sheet->getRowDimension($r)->setRowHeight(20);
        $sheet->getRowDimension($r + 1)->setRowHeight(20);
        $r += 2;

        // ── Row 11: spacer ──────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(13.5); 
        $r++;

        // ── Row 12: NAME, DEPARTMENT, MONTH ─────────────────────────────────────
        $sheet->setCellValue("D{$r}", 'NAME'); $sheet->setCellValue("E{$r}", ':');
        $sheet->mergeCells("F{$r}:J{$r}"); $sheet->setCellValue("F{$r}", strtoupper($user->name ?? '-'));
        $this->borders($sheet, "F{$r}:J{$r}");
        $sheet->setCellValue("K{$r}", 'DEPARTMENT'); $sheet->setCellValue("M{$r}", ':');
        $sheet->mergeCells("N{$r}:O{$r}"); $sheet->setCellValue("N{$r}", strtoupper($dept));
        $this->borders($sheet, "N{$r}:O{$r}");
        $sheet->setCellValue("P{$r}", 'MONTH'); $this->c($sheet, "P{$r}");
        $sheet->setCellValue("Q{$r}", ':');
        $sheet->mergeCells("Q{$r}:S{$r}"); $sheet->setCellValue("R{$r}", $month);
        $this->borders($sheet, "R{$r}:S{$r}");
        $sheet->getRowDimension($r)->setRowHeight(15.6);
        $r++;

        // ── Row 13: spacer ──────────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(12);
        $r++;

        // ── Row 14: STAFF NO., SECTION/LINE ─────────────────────────────────────
        $sheet->setCellValue("D{$r}", 'STAFF NO.'); $sheet->setCellValue("E{$r}", ':');
        $sheet->mergeCells("F{$r}:J{$r}"); $sheet->setCellValue("F{$r}", $user->staff_no ?? '-');
        $this->borders($sheet, "F{$r}:J{$r}");
        $sheet->setCellValue("K{$r}", 'SECTION/LINE'); $sheet->setCellValue("M{$r}", ':');
        $sheet->mergeCells("N{$r}:O{$r}"); $sheet->setCellValue("N{$r}", strtoupper($otForm->section_line ?? '-'));
        $this->borders($sheet, "N{$r}:O{$r}");
        $sheet->getRowDimension($r)->setRowHeight(15.6);
        $r++;

        // ── Row 15: spacer ──────────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(12);
        $r++;

        // Row 16: part "A", "B", "C"
        $sheet->mergeCells("C{$r}:J{$r}");
        $sheet->setCellValue("C{$r}", 'A');
        $this->borders($sheet, "C{$r}:J{$r}");
        $sheet->getStyle("C{$r}")->getFont()->setBold(true);
        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells("K{$r}:M{$r}");
        $sheet->setCellValue("K{$r}", 'B');
        $this->borders($sheet, "K{$r}:M{$r}");
        $sheet->getStyle("K{$r}")->getFont()->setBold(true);
        $sheet->getStyle("K{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells("R{$r}:T{$r}");
        $sheet->setCellValue("R{$r}", 'C');
        $this->borders($sheet, "R{$r}:T{$r}");
        $sheet->getStyle("R{$r}")->getFont()->setBold(true);
        $sheet->getStyle("R{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
       
        $sheet->getRowDimension($r)->setRowHeight(15.6);
        $r++;

        // ── Row 17: Table group headers ─────────────────────────────────────────
        $hr = $r;
        $sheet->mergeCells("C{$hr}:C" . ($hr + 1)); $sheet->setCellValue("C{$hr}", 'DATE');
        $sheet->getStyle("C{$hr}")->getFont()->setSize(10);
        $this->c($sheet, "C{$hr}");
        $sheet->getStyle("C{$hr}")->getAlignment()->setWrapText(true);
        $sheet->mergeCells("D{$hr}:G" . ($hr + 1)); $sheet->setCellValue("D{$hr}", 'PARTICULARS');
        $sheet->getStyle("D{$hr}")->getFont()->setSize(10);
        $this->c($sheet, "D{$hr}");
        $sheet->mergeCells("H{$hr}:J{$hr}"); $sheet->setCellValue("H{$hr}", 'PLAN');
        $sheet->getStyle("H{$hr}")->getFont()->setSize(10);
        $this->c($sheet, "H{$hr}");
        $sheet->mergeCells("K{$hr}:M{$hr}"); $sheet->setCellValue("K{$hr}", 'APPROVAL BEFORE OVERTIME');
        $sheet->getStyle("K{$hr}")->getFont()->setBold(true);
        $this->c($sheet, "K{$hr}");
        $sheet->getStyle("K{$hr}")->getAlignment()->setWrapText(true);
        $sheet->mergeCells("N{$hr}:P{$hr}"); $sheet->setCellValue("N{$hr}", 'ACTUAL');
        $sheet->getStyle("N{$hr}")->getFont()->setSize(10);
        $this->c($sheet, "N{$hr}");
        $sheet->mergeCells("R{$hr}:T{$hr}"); $sheet->setCellValue("R{$hr}", 'TOTAL HOURS');
        $sheet->getStyle("R{$hr}")->getFont()->setSize(10);
        $this->c($sheet, "R{$hr}");
        $this->borders($sheet, "C{$hr}:P{$hr}");
        $this->borders($sheet, "R{$hr}:T{$hr}");
        $sheet->getRowDimension($hr)->setRowHeight(25.5);
        $r++;

        // ── Row 18: Sub-headers ─────────────────────────────────────────────────
        $sheet->setCellValue("H{$r}", 'START');       $sheet->getStyle("H{$r}")->getFont()->setSize(10); $this->c($sheet, "H{$r}");
        $sheet->setCellValue("I{$r}", 'END');         $sheet->getStyle("I{$r}")->getFont()->setSize(10); $this->c($sheet, "I{$r}");
        $sheet->setCellValue("J{$r}", 'TOTAL HOURS'); $sheet->getStyle("J{$r}")->getFont()->setSize(10); $this->c($sheet, "J{$r}");
        $sheet->getStyle("J{$r}")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("K{$r}", 'EXEC.');       $sheet->getStyle("K{$r}")->getFont()->setBold(true)->setSize(10); $this->c($sheet, "K{$r}");
        $sheet->setCellValue("L{$r}", 'HOD');         $sheet->getStyle("L{$r}")->getFont()->setBold(true)->setSize(10); $this->c($sheet, "L{$r}");
        $sheet->getStyle("L{$r}")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("M{$r}", "DGM/CEO"); $sheet->getStyle("M{$r}")->getFont()->setBold(true)->setSize(10); $this->c($sheet, "M{$r}");
        $sheet->getStyle("M{$r}")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("N{$r}", 'START');       $sheet->getStyle("N{$r}")->getFont()->setSize(10); $this->c($sheet, "N{$r}");
        $sheet->setCellValue("O{$r}", 'END');         $sheet->getStyle("O{$r}")->getFont()->setSize(10); $this->c($sheet, "O{$r}");
        $sheet->setCellValue("P{$r}", 'TOTAL HOURS'); $sheet->getStyle("P{$r}")->getFont()->setSize(10); $this->c($sheet, "P{$r}");
        $sheet->getStyle("P{$r}")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("R{$r}", 'NORMAL DAY');  $sheet->getStyle("R{$r}")->getFont()->setSize(10); $this->c($sheet, "R{$r}");
        $sheet->getStyle("R{$r}")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("S{$r}", 'REST DAY');    $sheet->getStyle("S{$r}")->getFont()->setSize(10); $this->c($sheet, "S{$r}");
        $sheet->setCellValue("T{$r}", 'PUBLIC HOLIDAY'); $sheet->getStyle("T{$r}")->getFont()->setSize(10); $this->c($sheet, "T{$r}");
        $sheet->getStyle("T{$r}")->getAlignment()->setWrapText(true);
        $this->borders($sheet, "C{$r}:P{$r}");
        $this->borders($sheet, "R{$r}:T{$r}");
        $sheet->getRowDimension($r)->setRowHeight(25.5);
        $r++;

        // ── Fetch approval logs ────────────────────────────────────────────────
        $logs = ApprovalLog::where('approvable_type', 'ot_form')
            ->where('approvable_id', $otForm->id)
            ->where('action', 'approved')
            ->with('approver')
            ->orderBy('level')
            ->get();
        $staffSignerName = $this->shortName($user);
        $staffDesignation = $user->designation ?? '';
        $hodLog = $logs->where('level', 2)->first();
        $hodSignerName = ($hodLog && $hodLog->approver) ? $this->shortName($hodLog->approver) : '';
        $hodDesignation = ($hodLog && $hodLog->approver) ? ($hodLog->approver->designation ?? '') : '';
        $gmLog = $logs->where('level', 1)->first();
        $gmSignerName = ($gmLog && $gmLog->approver) ? $this->shortName($gmLog->approver) : '';
        $gmDesignation = ($gmLog && $gmLog->approver) ? ($gmLog->approver->designation ?? '') : '';

        // Fallback: use designated approvers if no approval logs exist
        $formUser = $otForm->user;
        if (!$hodSignerName && in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved'])) {
            if ($formUser->ot_approver_id) {
                $hodApprover = \App\Models\User::find($formUser->ot_approver_id);
            }
            // Legacy fallback for old fields
            if (!isset($hodApprover) && $otForm->form_type === 'executive' && $formUser->ot_exec_approver_id) {
                $hodApprover = \App\Models\User::find($formUser->ot_exec_approver_id);
            } elseif (!isset($hodApprover) && $otForm->form_type === 'non_executive' && $formUser->ot_non_exec_approver_id) {
                $hodApprover = \App\Models\User::find($formUser->ot_non_exec_approver_id);
            }
            if (isset($hodApprover)) {
                $hodSignerName = $this->shortName($hodApprover);
                $hodDesignation = $hodApprover->designation ?? '';
            }
        }
        if (!$gmSignerName && $otForm->status === 'approved') {
            if ($formUser->ot_final_approver_id) {
                $gmApprover = \App\Models\User::find($formUser->ot_final_approver_id);
            }
            // Legacy fallback for old fields
            if (!isset($gmApprover) && $otForm->form_type === 'executive' && $formUser->ot_exec_final_approver_id) {
                $gmApprover = \App\Models\User::find($formUser->ot_exec_final_approver_id);
            } elseif (!isset($gmApprover) && $otForm->form_type === 'non_executive' && $formUser->ot_non_exec_final_approver_id) {
                $gmApprover = \App\Models\User::find($formUser->ot_non_exec_final_approver_id);
            }
            if (isset($gmApprover)) {
                $gmSignerName = $this->shortName($gmApprover);
                $gmDesignation = $gmApprover->designation ?? '';
            }
        }
        $blueFont = ['font' => ['size' => 8, 'bold' => true, 'color' => ['argb' => 'FF002060']]];

        // ── Data rows (18 rows, rows 19-36) ─────────────────────────────────────
        $filled = $otForm->entries()->with('projectCode')->get()
            ->filter(fn($e) => $e->project_code_id || $e->planned_start_time || $e->actual_start_time)
            ->groupBy(fn($e) => $e->entry_date->format('Y-m-d'))
            ->sortKeys();
        $rowCount = max($filled->count(), 18);

        $rowIdx = 0;
        foreach ($filled as $dateStr => $dateEntries) {
            foreach ($dateEntries as $e) {
                $particulars = $e ? trim(($e->projectCode ? $e->projectCode->code : '') . ' ' . ($e->project_name ?? '')) : '';
                $sheet->setCellValue("C{$r}", $e ? $e->entry_date->format('d/m/Y') : '');
                $sheet->mergeCells("D{$r}:G{$r}");
                $sheet->setCellValue("D{$r}", $particulars);
                $sheet->getStyle("D{$r}")->getAlignment()->setWrapText(true);
                $sheet->setCellValue("H{$r}", $this->correctedTimeCell($e, 'planned_start_time') ?? ($e && $e->planned_start_time ? substr($e->planned_start_time, 0, 5) : ''));
                $sheet->setCellValue("I{$r}", $this->correctedTimeCell($e, 'planned_end_time') ?? ($e && $e->planned_end_time ? substr($e->planned_end_time, 0, 5) : ''));
                $sheet->getStyle("H{$r}:I{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                // Compute planned total hours from times if stored value is invalid
                $plannedHours = $e ? (float)$e->planned_total_hours : 0;
                if ($plannedHours <= 0 && $e && $e->planned_start_time && $e->planned_end_time) {
                    $plannedHours = $this->calcHoursFromTimes($e->planned_start_time, $e->planned_end_time);
                }
                $sheet->setCellValue("J{$r}", $plannedHours > 0 ? number_format($plannedHours, 2) : '');
                // K, L, M = approval signature columns (EXEC, HOD, DGM/CEO)
                $isFilled = $e && ($e->project_code_id || $e->planned_start_time || $e->actual_start_time);
                if ($isFilled && !in_array($otForm->status, ['draft'])) {
                    $sheet->setCellValue("K{$r}", $staffSignerName);
                    $sheet->getStyle("K{$r}")->applyFromArray($blueFont);
                }
                if ($isFilled && in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved']) && $hodSignerName) {
                    $sheet->setCellValue("L{$r}", $hodSignerName);
                    $sheet->getStyle("L{$r}")->applyFromArray($blueFont);
                }
                if ($isFilled && $otForm->status === 'approved' && $gmSignerName) {
                    $sheet->setCellValue("M{$r}", $gmSignerName);
                    $sheet->getStyle("M{$r}")->applyFromArray($blueFont);
                }
                $sheet->setCellValue("N{$r}", $this->correctedTimeCell($e, 'actual_start_time') ?? ($e && $e->actual_start_time ? substr($e->actual_start_time, 0, 5) : ''));
                $sheet->setCellValue("O{$r}", $this->correctedTimeCell($e, 'actual_end_time') ?? ($e && $e->actual_end_time ? substr($e->actual_end_time, 0, 5) : ''));
                $sheet->getStyle("N{$r}:O{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                // Compute actual total hours from times if stored value is invalid
                $actualHours = $e ? (float)$e->actual_total_hours : 0;
                if ($actualHours <= 0 && $e && $e->actual_start_time && $e->actual_end_time) {
                    $actualHours = $this->calcHoursFromTimes($e->actual_start_time, $e->actual_end_time);
                }
                $actualHours = floor($actualHours * 4) / 4;
                $sheet->setCellValue("P{$r}", $actualHours > 0 ? number_format($actualHours, 2) : '');
                // Distribute OT hours: use stored values, or fallback to actual hours by day type
                $otNormal = $e ? (float)$e->ot_normal_day_hours : 0;
                $otRest = $e ? (float)$e->ot_rest_day_hours : 0;
                $otPH = $e ? (float)$e->ot_ph_hours : 0;
                if ($actualHours > 0 && ($otNormal + $otRest + $otPH) <= 0 && $e) {
                    if ($e->is_public_holiday) {
                        $otPH = $actualHours;
                    } elseif ($e->entry_date && $e->entry_date->isWeekend()) {
                        $otRest = $actualHours;
                    } else {
                        $otNormal = $actualHours;
                    }
                }
                $sheet->setCellValue("R{$r}", $otNormal > 0 ? number_format($otNormal, 2) : '');
                $sheet->setCellValue("S{$r}", $otRest > 0 ? number_format($otRest, 2) : '');
                $sheet->setCellValue("T{$r}", $otPH > 0 ? number_format($otPH, 2) : '');
                // Borders: C-G bottom+left, H-P all thin, R-T all thin
                $this->borders($sheet, "H{$r}:P{$r}");
                $this->borders($sheet, "R{$r}:T{$r}");
                $sheet->getStyle("C{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
                $sheet->getStyle("C{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
                $sheet->getStyle("D{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
                $sheet->getStyle("D{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
                $sheet->getStyle("E{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
                $sheet->getStyle("F{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
                $sheet->getStyle("G{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
                $this->c($sheet, "C{$r}:T{$r}");
                $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                // Auto-calculate row height based on text length for wrapped PARTICULARS
                $textLen = strlen($particulars);
                if ($textLen > 45) {
                    $lines = ceil($textLen / 45);
                    $sheet->getRowDimension($r)->setRowHeight($lines * 13);
                } else {
                    $sheet->getRowDimension($r)->setRowHeight(15.75);
                }
                $r++;
                $rowIdx++;
            }
        }
        // Fill remaining empty rows to reach 18
        while ($rowIdx < 18) {
            $sheet->setCellValue("C{$r}", '');
            $sheet->mergeCells("D{$r}:G{$r}");
            $sheet->setCellValue("D{$r}", '');
            $sheet->setCellValue("H{$r}", '');
            $sheet->setCellValue("I{$r}", '');
            $sheet->setCellValue("J{$r}", '');
            $sheet->setCellValue("K{$r}", '');
            $sheet->setCellValue("L{$r}", '');
            $sheet->setCellValue("M{$r}", '');
            $sheet->setCellValue("N{$r}", '');
            $sheet->setCellValue("O{$r}", '');
            $sheet->setCellValue("P{$r}", '');
            $sheet->setCellValue("R{$r}", '');
            $sheet->setCellValue("S{$r}", '');
            $sheet->setCellValue("T{$r}", '');
            $this->borders($sheet, "H{$r}:P{$r}");
            $this->borders($sheet, "R{$r}:T{$r}");
            $sheet->getStyle("C{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
            $sheet->getStyle("C{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
            $sheet->getStyle("D{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
            $sheet->getStyle("D{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
            $sheet->getStyle("E{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
            $sheet->getStyle("F{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
            $sheet->getStyle("G{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
            $this->c($sheet, "C{$r}:T{$r}");
            $sheet->getRowDimension($r)->setRowHeight(15.75);
            $r++;
            $rowIdx++;
        }

        // ── Row 37: spacer ──────────────────────────────────────────────────────
        $sheet->getRowDimension($r)->setRowHeight(6);
        $r++;

        // ── Row 38: Totals ──────────────────────────────────────────────────────
        $totalPlan = 0; $totalActual = 0; $totalNorm = 0; $totalRest = 0; $totalPH = 0;
        foreach ($filled as $dateEntries) {
            foreach ($dateEntries as $e) {
                $ph = (float)$e->planned_total_hours;
                if ($ph <= 0 && $e->planned_start_time && $e->planned_end_time) {
                    $ph = $this->calcHoursFromTimes($e->planned_start_time, $e->planned_end_time);
                }
                $totalPlan += $ph;

                $ah = (float)$e->actual_total_hours;
                if ($ah <= 0 && $e->actual_start_time && $e->actual_end_time) {
                    $ah = $this->calcHoursFromTimes($e->actual_start_time, $e->actual_end_time);
                }
                $ah = floor($ah * 4) / 4;
                $totalActual += $ah;

                $on = (float)$e->ot_normal_day_hours;
                $or = (float)$e->ot_rest_day_hours;
                $op = (float)$e->ot_ph_hours;
                if ($ah > 0 && ($on + $or + $op) <= 0) {
                    if ($e->is_public_holiday) { $op = $ah; }
                    elseif ($e->entry_date && $e->entry_date->isWeekend()) { $or = $ah; }
                    else { $on = $ah; }
                }
                $totalNorm += $on;
                $totalRest += $or;
                $totalPH += $op;
            }
        }

        $sheet->mergeCells("C{$r}:F{$r}");
        $sheet->setCellValue("C{$r}", 'APPROVAL AFTER OVERTIME');
        $this->c($sheet, "C{$r}"); 
        $this->borders($sheet, "C{$r}:F{$r}");

        $sheet->mergeCells("H{$r}:I{$r}"); $sheet->setCellValue("H{$r}", 'TOTAL (HOURS)');
        $this->c($sheet, "H{$r}"); $this->borders($sheet, "H{$r}:I{$r}");
        $sheet->setCellValue("J{$r}", $totalPlan > 0 ? number_format($totalPlan, 2) : '');
        $this->c($sheet, "J{$r}"); $this->b($sheet, "J{$r}"); $sheet->getStyle("J{$r}")->getFont()->setSize(12);
        $this->borders($sheet, "J{$r}");
        $sheet->mergeCells("N{$r}:O{$r}"); $sheet->setCellValue("N{$r}", 'TOTAL (HOURS)');
        $this->c($sheet, "N{$r}"); $this->borders($sheet, "N{$r}:O{$r}");
        $sheet->setCellValue("P{$r}", $totalActual > 0 ? number_format($totalActual, 2) : '');
        $this->c($sheet, "P{$r}"); $this->b($sheet, "P{$r}"); $sheet->getStyle("P{$r}")->getFont()->setSize(12);
        $this->borders($sheet, "P{$r}");
        // Total hours boxes with double borders
        $sheet->setCellValue("R{$r}", $totalNorm > 0 ? number_format($totalNorm, 2) : '');
        $sheet->setCellValue("S{$r}", $totalRest > 0 ? number_format($totalRest, 2) : '');
        $sheet->setCellValue("T{$r}", $totalPH   > 0 ? number_format($totalPH,   2) : '');
        $this->c($sheet, "R{$r}:T{$r}");
        $this->doubleBorders($sheet, "R{$r}"); $this->doubleBorders($sheet, "S{$r}"); $this->doubleBorders($sheet, "T{$r}");
        $sheet->getRowDimension($r)->setRowHeight(21);
        $r++;

        // ── Row 39: NOTE ────────────────────────────────────────────────────────
        $sheet->mergeCells("C{$r}:D{$r}");
        $sheet->setCellValue("C{$r}", 'Claimed by');
        $this->c($sheet, "C{$r}");
        $sheet->mergeCells("E{$r}:F{$r}");
        $sheet->setCellValue("E{$r}", 'Approved by');
        $this->c($sheet, "E{$r}");
        $this->borders($sheet, "C{$r}:F{$r}");
        $sheet->setCellValue("J{$r}", 'NOTE :');
        $sheet->getStyle("J{$r}")->getFont()->setBold(true)->setUnderline(true);
        $sheet->getRowDimension($r)->setRowHeight(15.75);
        $r++;

        // ── Signature stamp boxes with notes on same row ─────────────────────────
        $ss1 = $r;
        $ss2 = $r + 1;
        $ss3 = $r + 2;

        // Stamp boxes (merged C-D, E-F spanning 3 rows)
        $sheet->mergeCells("C{$ss1}:D{$ss3}"); $this->borders($sheet, "C{$ss1}:D{$ss3}");
        $sheet->mergeCells("E{$ss1}:F{$ss3}"); $this->borders($sheet, "E{$ss1}:F{$ss3}");

        // Staff stamp (Executive/Claimed by)
        if (!in_array($otForm->status, ['draft'])) {
            $staffDate = $otForm->plan_submitted_at ? $otForm->plan_submitted_at->format('d/m/Y') : '';
            $this->addStamp($sheet, "C{$ss1}", $this->shortName($user), $staffDate, 'STAFF', $staffDesignation);
        }

        // HOD stamp (Approved by) — use log or fallback to designated approver
        if ($hodLog && $hodLog->approver) {
            $hodDate = $hodLog->acted_at ? $hodLog->acted_at->format('d/m/Y') : '';
            $this->addStamp($sheet, "E{$ss1}", $this->shortName($hodLog->approver), $hodDate, 'MGR/HOD', $hodDesignation);
        } elseif (in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved'])) {
            $hodStampName = $hodSignerName ?? '';
            $hodStampDesignation = $hodDesignation;
            if (isset($hodApprover)) {
                $hodStampDesignation = $hodApprover->designation ?? '';
            }
            if ($hodStampName) {
                $this->addStamp($sheet, "E{$ss1}", $hodStampName, '', 'MGR/HOD', $hodStampDesignation);
            }
        }

        $sheet->getRowDimension($ss1)->setRowHeight(90);
        $sheet->getRowDimension($ss2)->setRowHeight(16.5);
        $sheet->getRowDimension($ss3)->setRowHeight(16.5);

        // Notes on the same rows as signature boxes (columns J-T)
        // Note 1 on first row
        $note = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $r1 = $note->createTextRun("1) \n2) ");
        $r1->getFont()->setSize(11);
        $sheet->setCellValue("J{$ss1}", $note);
        $sheet->getStyle("J{$ss1}")->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_TOP);
        $note1 = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $sheet->mergeCells("K{$ss1}:T{$ss1}");
        $r1a = $note1->createTextRun('Overtime submission should be presented to ');
        $r1a->getFont()->setSize(11);
        $r1b = $note1->createTextRun('HOD/DGM/MD');
        $r1b->getFont()->setSize(11)->setBold(true);
        $r1c = $note1->createTextRun(' before 4.30 pm for approval.');
        $r1c->getFont()->setSize(11);
        $r1d = $note1->createTextRun("\nOT claim shall submitted to Payroll section");
        $r1d->getFont()->setSize(11);
        $r1e = $note1->createTextRun(' every 05th of the month');
        $r1e->getFont()->setSize(11)->setBold(true);
        $r1f = $note1->createTextRun(' and the maximum claim shall not exceed');
        $r1f->getFont()->setSize(11);
        $r1g = $note1->createTextRun(' RM500.00');
        $r1g->getFont()->setSize(11)->setBold(true);
        $r1h = $note1->createTextRun(' per month.');
        $r1h->getFont()->setSize(11);
        $sheet->setCellValue("K{$ss1}", $note1);
        $sheet->getStyle("K{$ss1}")->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_TOP);

        $r = $ss3 + 1;

        //Row 42
        $sheet->mergeCells("C{$r}:D{$r}");
        $sheet->setCellValue("C{$r}", 'Executive');
        $this->c($sheet, "C{$r}");
        $sheet->mergeCells("E{$r}:F{$r}");
        $sheet->setCellValue("E{$r}", 'HOD');
        $this->c($sheet, "E{$r}");
        $this->borders($sheet, "C{$r}:F{$r}");
        $sheet->getRowDimension($r)->setRowHeight(16.5);
        $sheet->getStyle("C{$r}:U{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
        $r++;

        // ── Apply form border (medium left on B, medium right on V) ─────────────
        $firstDataRow = 3;
        $lastDataRow = $r;
        for ($br = $firstDataRow; $br <= $lastDataRow; $br++) {
            $sheet->getStyle("B{$br}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
            $sheet->getStyle("C{$br}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
            $sheet->getStyle("U{$br}")->getBorders()->getRight()->setBorderStyle(self::THIN);
            $sheet->getStyle("V{$br}")->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM);
        }
        $sheet->mergeCells("B{$r}:V{$r}");
        $sheet->getRowDimension($r)->setRowHeight(5);
        $sheet->getStyle("B{$r}:V{$r}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        //$this->borders($sheet, "B{$r}:U{$r}", 'bottom', Border::BORDER_MEDIUM);
        return $ss;
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function b(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
    }

    private function c(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function borders(Worksheet $sheet, string $range, string $side = 'all'): void
    {
        $borders = $sheet->getStyle($range)->getBorders();

        switch ($side) {
            case 'top':
                $borders->getTop()->setBorderStyle(self::THIN);
                break;
            case 'bottom':
                $borders->getBottom()->setBorderStyle(self::THIN);
                break;
            case 'left':
                $borders->getLeft()->setBorderStyle(self::THIN);
                break;
            case 'right':
                $borders->getRight()->setBorderStyle(self::THIN);
                break;
            case 'outline':
                $borders->getOutline()->setBorderStyle(self::THIN);
                break;
            case 'inside':
                $borders->getInside()->setBorderStyle(self::THIN);
                break;
            default:
                $borders->getAllBorders()->setBorderStyle(self::THIN);
                break;
        }
    }

    private function bg(Worksheet $sheet, string $range, string $hex): void
    {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($hex);
    }

    private function vt(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getAlignment()->setTextRotation(90);
    }

    private function thickBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);
    }

    private function doubleBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_DOUBLE);
    }

    private function addStamp(Worksheet $sheet, string $cell, string $name, string $date, string $role, ?string $designation = null): void
    {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Stamp');
        $drawing->setDescription('Approval Stamp');

        // Determine stamp code based on role
        $code = strtoupper(substr($role, 0, 4));
        if (stripos($role, 'STAFF') !== false || stripos($role, 'EXEC') !== false) {
            $code = 'CLMD';
        } else {
            $code = 'APRV';
        }

        // Display label uses designation if provided, otherwise role
        $displayLabel = strtoupper(self::shortDesignation($designation ?: $role));

        // Create larger PNG stamp (120x120 to accommodate name/role below)
        $image = imagecreatetruecolor(120, 150);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);

        $red = imagecolorallocate($image, 220, 38, 38);
        $darkRed = imagecolorallocate($image, 180, 30, 30);

        // Circle center at (60, 60) with radius 50
        $cx = 60;
        $cy = 60;
        $radius = 50;

        // Outer border (thick)
        imageellipse($image, $cx, $cy, $radius * 2, $radius * 2, $darkRed);
        imageellipse($image, $cx, $cy, $radius * 2 - 4, $radius * 2 - 4, $darkRed);
        imageellipse($image, $cx, $cy, $radius * 2 - 8, $radius * 2 - 8, $darkRed);

        // Inner border (thin)
        imageellipse($image, $cx, $cy, $radius * 2 - 12, $radius * 2 - 12, $red);

        // Top: TSSB
        imagestring($image, 5, $cx - 15, $cy - 35, 'TSSB', $red);

        // Center: Code (CLMD/APRV)
        imagestring($image, 5, $cx - 15, $cy - 8, $code, $darkRed);

        // Center: Date
        imagestring($image, 3, $cx - 35, $cy + 5, $date, $red);

        // Bottom: 3 stars (using asterisk)
        imagestring($image, 3, $cx - 10, $cy + 25, '***', $red);

        // Below circle: Name (centered, font 2 = 6px per char)
        $name = strtoupper(substr($name, 0, 18));
        $nameWidth = strlen($name) * 6;
        imagestring($image, 2, $cx - (int)($nameWidth / 2), $cy + 55, $name, $red);

        // Below name: designation/role (centered, font 2 = 6px per char)
        $displayLabel = strtoupper(substr($displayLabel, 0, 18));
        $labelWidth = strlen($displayLabel) * 6;
        imagestring($image, 2, $cx - (int)($labelWidth / 2), $cy + 70, $displayLabel, $darkRed);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $tempPath = sys_get_temp_dir() . '/stamp_' . uniqid() . '.png';
        file_put_contents($tempPath, $imageData);

        $drawing->setPath($tempPath);
        $drawing->setCoordinates($cell);
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(2);
        $drawing->setHeight(150);
        $drawing->setWorksheet($sheet);
    }

    public static function shortName(User|string $input): string
    {
        if ($input instanceof User) {
            if ($input->short_name) {
                return strtoupper(trim($input->short_name));
            }
            return self::shortNameFromString($input->name ?? '');
        }
        return self::shortNameFromString($input);
    }

    private static function shortNameFromString(string $fullName): string
    {
        $name = strtoupper(trim($fullName));
        if (preg_match('/^(.+?)\s+(?:BIN|BINTI|B|BT)\b/i', $name, $m)) {
            $parts = explode(' ', trim($m[1]));
            return end($parts);
        }
        $parts = explode(' ', $name);
        return end($parts);
    }

    public static function shortDesignation(?string $designation): string
    {
        if (!$designation) return '';
        $upper = strtoupper(trim($designation));
        $fullMappings = [
            'CHIEF EXECUTIVE OFFICER' => 'CEO',
            'HEAD OF DEPARTMENT' => 'HOD',
            'SENIOR MANAGER' => 'SNR MGR',
            'ASSISTANT MANAGER' => 'AST MGR',
            'SENIOR EXECUTIVE' => 'SNR EXEC',
            'SENIOR CLERK' => 'SNR CLERK',
            'MANAGER' => 'MGR',
            'EXECUTIVE' => 'EXEC',
            'SUPERVISOR' => 'SPV',
            'TECHNICIAN' => 'TECH',
            'MACHINIST' => 'MACH',
            'CLERK' => 'CLERK',
            'HOD' => 'HOD',
        ];
        foreach ($fullMappings as $full => $short) {
            if ($upper === $full) return $short;
        }
        $replacements = [
            'SENIOR' => 'SNR',
            'ASSISTANT' => 'AST',
            'MANAGER' => 'MGR',
            'EXECUTIVE' => 'EXEC',
            'OFFICER' => 'OFR',
            'TECHNICIAN' => 'TECH',
            'MACHINIST' => 'MACH',
            'SUPERVISOR' => 'SPV',
            'CLERK' => 'CLERK',
        ];
        $result = $upper;
        foreach ($replacements as $word => $short) {
            $result = preg_replace('/\b' . preg_quote($word, '/') . '\b/', $short, $result);
        }
        return $result;
    }

    public static function calcHours(?string $start, ?string $end): float
    {
        if (!$start || !$end) return 0;
        $s = strtotime("2000-01-01 $start");
        $e = strtotime("2000-01-01 $end");
        if ($e <= $s) $e += 86400;
        return round(($e - $s) / 3600, 2);
    }

    private function calcHoursFromTimes(?string $start, ?string $end): float
    {
        return $this->calcHours($start, $end);
    }

    private function correctedTimeCell(?OtFormEntry $entry, string $field): ?RichText
    {
        if (!$entry) {
            return null;
        }

        $current = $entry->$field ? substr($entry->$field, 0, 5) : '';
        $original = null;
        $corrections = $entry->hr_corrections ?? [];
        if (is_array($corrections)) {
            $original = $corrections[$field] ?? null;
        }
        $originalStr = $original ? substr($original, 0, 5) : '';

        if (!$current && !$originalStr) {
            return null;
        }

        $rt = new RichText();
        if ($current) {
            $run = $rt->createTextRun($current);
            $run->getFont()->setSize(10);
        }
        if ($originalStr && $originalStr !== $current) {
            $run = $rt->createTextRun("\n" . $originalStr);
            $run->getFont()->setSize(9)->setStrikethrough(true)->setColor(['argb' => 'FFFF0000']);
        }
        return $rt;
    }
}
