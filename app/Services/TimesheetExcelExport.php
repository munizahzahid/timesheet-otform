<?php

namespace App\Services;

use App\Models\Timesheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class TimesheetExcelExport
{
    private const THIN   = Border::BORDER_THIN;
    private const MEDIUM = Border::BORDER_MEDIUM;

    /**
     * Generate the timesheet Excel spreadsheet matching TIMESHEET KOSONG.xlsx layout.
     *
     * Column mapping:
     *   A(spacer) B(border) C(NO.) D(ADMIN JOB / PROJECT CODE)
     *   E(TIME/COST) F(NC/COBQ) G..AK(days 1-31) AL(TOTAL) AM(spacer) AN(border)
     */
    public function generate(Timesheet $timesheet): Spreadsheet
    {
        $timesheet->load([
            'user.department',
            'dayMetadata',
            'adminHours',
            'projectRows.projectCode',
            'projectRows.hours',
            'approvalLogs.user',
        ]);

        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('TIMESHEET');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.25)->setBottom(0.25)->setLeft(0.25)->setRight(0.25);
        $sheet->getSheetView()->setZoomScale(80);

        // ── Column widths ──────────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(3.86);
        $sheet->getColumnDimension('B')->setWidth(0.86);
        $sheet->getColumnDimension('C')->setWidth(9.43);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(10.14);
        $sheet->getColumnDimension('F')->setWidth(7.86);
        foreach (range('G', 'Z') as $col) {
            $sheet->getColumnDimension($col)->setWidth(6.57);
        }
        foreach (['AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(6.57);
        }
        $sheet->getColumnDimension('AL')->setWidth(11.86);
        $sheet->getColumnDimension('AM')->setWidth(0.86);

        // Default font
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $user       = $timesheet->user;
        $monthName  = strtoupper(Carbon::create($timesheet->year, $timesheet->month)->format('F'));
        $monthYear  = $monthName . ' ' . $timesheet->year;
        $calcSvc    = new TimesheetCalculationService();
        $days       = $calcSvc->generateDayMetadata($timesheet->month, $timesheet->year);
        $daysInMonth = count($days);

        // Merge DB metadata for day types
        foreach ($timesheet->dayMetadata as $meta) {
            $d = (int) $meta->entry_date->day;
            if (isset($days[$d])) {
                $days[$d]['day_type']         = $meta->day_type;
                $days[$d]['available_hours']  = (float) $meta->available_hours;
            }
        }

        // ── Build admin hours lookup ───────────────────────────────────────────
        $adminTypes = TimesheetCalculationService::ADMIN_TYPES;
        $adminData  = [];
        foreach ($adminTypes as $type => $label) {
            $adminData[$type] = array_fill(1, $daysInMonth, 0);
        }
        foreach ($timesheet->adminHours as $ah) {
            $day = (int) $ah->entry_date->day;
            if (isset($adminData[$ah->admin_type])) {
                $adminData[$ah->admin_type][$day] = (float) $ah->hours;
            }
        }

        // ── Build project rows data ────────────────────────────────────────────
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

        // Day column helper: day 1 = col G (index 7)
        $dayCol = function (int $day): string {
            return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6 + $day);
        };
        $lastDayCol = $dayCol($daysInMonth);

        $r = 1;

        // ══════════════════════════════════════════════════════════════════════
        // Row 1: spacer
        // ══════════════════════════════════════════════════════════════════════
        $sheet->getRowDimension($r)->setRowHeight(15.75);
        $r++;

        // Row 2: outer border top (medium)
        $sheet->getRowDimension($r)->setRowHeight(5.25);
        $this->mediumBorder($sheet, "B{$r}:AM{$r}", 'top');
        $r++;

        // Rows 3-4: talent synergy logo
        $sheet->mergeCells("C{$r}:C".($r+1));
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Company Logo');
        $drawing->setPath(public_path('images/ingress logo.png'));
        $drawing->setHeight(60);
        $drawing->setCoordinates("C{$r}");
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(-5);
        $drawing->setWorksheet($sheet);
        $sheet->mergeCells("D{$r}:D".($r+1));
        $sheet->setCellValue("D{$r}", "TALENT SYNERGY\nSDN BHD");
        $sheet->getStyle("D{$r}")->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D{$r}")->getFont()->setBold(true)->setSize(14);
        $sheet->getRowDimension($r)->setRowHeight(20);
        $this->borders($sheet, "C{$r}:AL{$r}", 'top');
        $r++;
        $sheet->getRowDimension($r)->setRowHeight(-1);
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Row 5: DAILY TIME SHEET title
        // ══════════════════════════════════════════════════════════════════════
        $sheet->getRowDimension($r)->setRowHeight(16);
        $sheet->setCellValue("C{$r}", 'DAILY TIME SHEET');
        $sheet->getStyle("C{$r}")->getFont()->setBold(true)->setName('Arial');
        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $this->borders($sheet, "C{$r}:AL{$r}", 'top');
        $this->borders($sheet, "C{$r}:AL{$r}", 'bottom');
        $sheet->getStyle("C{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Row 6: Approval headers (right side)
        // ══════════════════════════════════════════════════════════════════════
        $sheet->getRowDimension($r)->setRowHeight(-1);
        // Prepared By: 3 columns (AE:AG)
        $sheet->mergeCells("AE{$r}:AG{$r}");
        $sheet->setCellValue("AE{$r}", 'Prepared By');
        $this->b($sheet, "AE{$r}"); $this->c($sheet, "AE{$r}");
        $this->borders($sheet, "AE{$r}:AG{$r}");
        // Checked By: 3 columns (AH:AJ)
        $sheet->mergeCells("AH{$r}:AJ{$r}");
        $sheet->setCellValue("AH{$r}", 'Checked By');
        $this->b($sheet, "AH{$r}"); $this->c($sheet, "AH{$r}");
        $this->borders($sheet, "AH{$r}:AJ{$r}");
        // Verified By: 2 columns (AK:AL)
        $sheet->mergeCells("AK{$r}:AL{$r}");
        $sheet->setCellValue("AK{$r}", 'Verified By');
        $this->b($sheet, "AK{$r}"); $this->c($sheet, "AK{$r}");
        $this->borders($sheet, "AK{$r}:AL{$r}");
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Rows 7-9: Signature stamp boxes + MONTH/NAME info
        // ══════════════════════════════════════════════════════════════════════
        $stampStartRow = $r;
        // Rows 7-9: Approval stamps (merged vertically)
        // Prepared By: 3 columns (AE:AG), Checked By: 3 columns (AH:AJ), Verified By: 2 columns (AK:AL)
        $sheet->mergeCells("AE{$r}:AG" . ($r + 2));
        $sheet->mergeCells("AH{$r}:AJ" . ($r + 2));
        $sheet->mergeCells("AK{$r}:AL" . ($r + 2));
        $sheet->getRowDimension($r)->setRowHeight(90); // Increased for stamp image
        $r++; // row 8
        $sheet->setCellValue("C{$r}", 'MONTH :');
        $this->b($sheet, "C{$r}");
        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
        $sheet->setCellValue("D{$r}", $monthYear);
        $this->c($sheet, "D{$r}");
        $sheet->getStyle("D{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue("Q{$r}", 'STAFF NO :');
        $this->b($sheet, "Q{$r}");
        $sheet->setCellValue("S{$r}", $user->staff_no ?? '-');
        $this->b($sheet, "S{$r}"); $this->c($sheet, "S{$r}");
        $sheet->getRowDimension($r)->setRowHeight(18);

        $r++;

        // Row 10: Stamp boxes bottom row + approval stamps
        $stampEndRow = $r;
        // Row 10: Static labels (staff short name, HOD/EXEC/SPV, ASST MNGR/MNGR)
        // Prepared By: 3 columns (AE:AG), Checked By: 3 columns (AH:AJ), Verified By: 2 columns (AK:AL)
        $sheet->mergeCells("AE{$r}:AG{$r}");
        $sheet->mergeCells("AH{$r}:AJ{$r}");
        $sheet->mergeCells("AK{$r}:AL{$r}");
        // Set static labels at row 10
        $staffShortName = $user->name ? $this->shortName($user->name) : '';
        $sheet->setCellValue("AE{$r}", $staffShortName);
        $sheet->setCellValue("AH{$r}", 'HOD/EXEC/SPV');
        $sheet->setCellValue("AK{$r}", 'ASST MNGR/MNGR');
        $sheet->getStyle("AE{$r}")->getFont()->setSize(8)->setBold(true);
        $sheet->getStyle("AH{$r}")->getFont()->setSize(8)->setBold(true);
        $sheet->getStyle("AK{$r}")->getFont()->setSize(8)->setBold(true);
        $sheet->getStyle("AE{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("AH{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("AK{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $this->borders($sheet, "AE{$r}:AG{$r}");
        $this->borders($sheet, "AH{$r}:AJ{$r}");
        $this->borders($sheet, "AK{$r}:AL{$r}");
        // Call buildApprovalStampBoxes with stampStartRow (rows 7-9)
        $this->buildApprovalStampBoxes($sheet, $timesheet, $stampStartRow, $stampEndRow - 1);
        $sheet->getRowDimension($r)->setRowHeight(16.5);
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Row 11: Header row — NO. | (blank) | HOURS (merged G:AK) | TOTAL
        // ══════════════════════════════════════════════════════════════════════
        $hdrStart = $r; // row 11
        $sheet->getRowDimension($r)->setRowHeight(11.25);
        $sheet->mergeCells("C{$r}:C" . ($r + 2));
        $sheet->setCellValue("C{$r}", 'NO.');
        $this->b($sheet, "C{$r}"); $this->c($sheet, "C{$r}");

        $sheet->mergeCells("G{$r}:{$lastDayCol}{$r}");
        $sheet->setCellValue("G{$r}", 'HOURS');
        $this->b($sheet, "G{$r}"); $this->c($sheet, "G{$r}");
        $this->borders($sheet, "G{$r}:{$lastDayCol}{$r}");

        $sheet->mergeCells("AL{$r}:AL" . ($r + 2));
        $sheet->setCellValue("AL{$r}", 'TOTAL');
        $this->b($sheet, "AL{$r}"); $this->c($sheet, "AL{$r}");

        $this->borders($sheet, "C{$r}:C" . ($r + 2));
        $this->borders($sheet, "AL{$r}:AL" . ($r + 2));
        $this->borders($sheet, "D{$r}:F" . ($r + 2), 'outline');
        $r++;

        // Row 12: "ADMIN JOB" label
        $sheet->setCellValue("D{$r}", 'ADMIN JOB');
        $this->b($sheet, "D{$r}");
        $sheet->getStyle("D{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);

        // Day number cells in row 12 (day-of-week letters)
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            $sheet->setCellValue("{$col}{$r}", $days[$d]['day_of_week']);
            $sheet->getStyle("{$col}{$r}")->getFont()->setBold(true);
            $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $this->borders($sheet, "{$col}{$r}");
        }
        $r++;

        // Row 13: Day numbers 1..31
        $dayHeaderRow = $r; // Track this for column coloring
        $tableRows = []; // Track all table rows for coloring
        $firstAdminRow = null; // Track first admin row for coloring
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            $sheet->setCellValue("{$col}{$r}", $d);
            $this->b($sheet, "{$col}{$r}"); $this->c($sheet, "{$col}{$r}");
            $sheet->getStyle("{$col}{$r}")->getBorders()->getTop()->setBorderStyle(self::THIN);
            $sheet->getStyle("{$col}{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
            $sheet->getStyle("{$col}{$r}")->getBorders()->getRight()->setBorderStyle(self::THIN);

            // Yellow background for weekends/holidays
            $dayType = $days[$d]['day_type'] ?? 'working';
            if (in_array($dayType, ['off_day', 'public_holiday'])) {
                $color = ($dayType === 'public_holiday') ? 'FF0000' : 'FFFF00';
                $this->bg($sheet, "{$col}{$r}", $color);
                // Also color the day-of-week cell above
                $this->bg($sheet, "{$col}" . ($r - 1), $color);
            }
        }
        // Bottom border for header label row
        $this->borders($sheet, "D{$r}:F{$r}", 'bottom');
        $sheet->getStyle("C{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
        $tableRows[] = $r; // Add day header row to table rows
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Rows 14-21: Admin type rows (8 rows)
        // ══════════════════════════════════════════════════════════════════════
        $firstAdminRow = $r; // Track first admin row for coloring
        $adminTypeKeys = array_keys($adminTypes);
        $adminLabels = [
            'MC/LEAVE',
            'LATE',
            'MORNING ASSY/ADMIN JOB',
            '5S',
            'CERAMAH AGAMA',
            'ISO',
            'TRAINING/SEMINAR/VISIT',
            'RFQ / MKT / PUR / R & D / A.S.S / TDR',
        ];

        $rowNum = 1;
        foreach ($adminTypeKeys as $idx => $typeKey) {
            $label = $adminLabels[$idx] ?? $adminTypes[$typeKey];

            $sheet->setCellValue("C{$r}", $rowNum);
            $this->b($sheet, "C{$r}"); $this->c($sheet, "C{$r}");
            $this->borders($sheet, "C{$r}:D{$r}");

            $sheet->mergeCells("D{$r}:F{$r}");
            $sheet->setCellValue("D{$r}", $label);
            $this->b($sheet, "D{$r}");
            $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $this->borders($sheet, "D{$r}:F{$r}");

            // Day cells
            $rowTotal = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $col = $dayCol($d);
                $val = $adminData[$typeKey][$d] ?? 0;
                if ($val > 0) {
                    $sheet->setCellValue("{$col}{$r}", $val);
                    $this->b($sheet, "{$col}{$r}"); $this->c($sheet, "{$col}{$r}");
                    $rowTotal += $val;
                }
                $this->borders($sheet, "{$col}{$r}");
                
            }

            // TOTAL column
            $sheet->setCellValue("AL{$r}", $rowTotal > 0 ? $rowTotal : '');
            $this->c($sheet, "AL{$r}");
            $this->borders($sheet, "AL{$r}");

            $this->borders($sheet, "E{$r}:F{$r}");
            $tableRows[] = $r; // Add admin row to table rows
            $sheet->getRowDimension($r)->setRowHeight(20);

            $r++;
            $rowNum++;
        }

        // ══════════════════════════════════════════════════════════════════════
        // Row 22: TOTAL ADMIN JOB
        // ══════════════════════════════════════════════════════════════════════
        $sheet->getRowDimension($r)->setRowHeight(20.25);
        $sheet->mergeCells("D{$r}:F{$r}");
        $sheet->setCellValue("D{$r}", 'TOTAL ADMIN JOB');
        $this->b($sheet, "D{$r}");
        $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $this->borders($sheet, "D{$r}:F{$r}");

        // Sum admin hours per day
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            $dayTotal = 0;
            foreach ($adminTypeKeys as $typeKey) {
                $dayTotal += ($adminData[$typeKey][$d] ?? 0);
            }
            if ($dayTotal > 0) {
                $sheet->setCellValue("{$col}{$r}", $dayTotal);
                $this->c($sheet, "{$col}{$r}");
            }
            $sheet->getStyle("{$col}{$r}")->getBorders()->getTop()->setBorderStyle(self::THIN);
            $sheet->getStyle("{$col}{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
            $sheet->getStyle("{$col}{$r}")->getBorders()->getRight()->setBorderStyle(self::THIN);
        }

        // Grand total admin
        $grandAdminTotal = 0;
        foreach ($adminTypeKeys as $typeKey) {
            $grandAdminTotal += ($adminData[$typeKey]['total'] ?? 0);
        }
        $sheet->setCellValue("AL{$r}", $grandAdminTotal > 0 ? $grandAdminTotal : '');
        $this->c($sheet, "AL{$r}");
        $this->borders($sheet, "AL{$r}");
        $this->borders($sheet, "C{$r}:AL{$r}", 'bottom');
        $tableRows[] = $r; // Add total admin job row to table rows
        $lastAdminRow = $r; // Track last admin row for coloring
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Row 23: Spacer
        // ══════════════════════════════════════════════════════════════════════
        $sheet->getRowDimension($r)->setRowHeight(6);
        $this->borders($sheet, "C{$r}:F{$r}", 'bottom');
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            $sheet->getStyle("{$col}{$r}")->getBorders()->getTop()->setBorderStyle(self::THIN);
        }
        $sheet->getStyle("AL{$r}")->getBorders()->getTop()->setBorderStyle(self::THIN);
        $sheet->getStyle("AL{$r}")->getBorders()->getRight()->setBorderStyle(self::THIN);
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Row 24: Project section header
        // ══════════════════════════════════════════════════════════════════════
        $sheet->getRowDimension($r)->setRowHeight(21.75);
        $sheet->setCellValue("C{$r}", 'NO');
        $this->b($sheet, "C{$r}"); $this->c($sheet, "C{$r}");
        $sheet->getStyle("C{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
        $sheet->getStyle("C{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);

        $sheet->setCellValue("D{$r}", 'PROJECT CODE');
        $this->b($sheet, "D{$r}"); $this->c($sheet, "D{$r}");
        $sheet->getStyle("D{$r}")->getBorders()->getBottom()->setBorderStyle(self::THIN);
        $sheet->getStyle("D{$r}")->getBorders()->getLeft()->setBorderStyle(self::THIN);

        $sheet->mergeCells("E{$r}:F{$r}");
        $sheet->setCellValue("E{$r}", 'TIME / COST');
        $this->b($sheet, "E{$r}"); $this->c($sheet, "E{$r}");
        $this->borders($sheet, "E{$r}:F{$r}");

        $sheet->mergeCells("G{$r}:{$lastDayCol}{$r}");
        $sheet->setCellValue("G{$r}", 'HOURS');
        $this->b($sheet, "G{$r}"); $this->c($sheet, "G{$r}");
        $this->borders($sheet, "G{$r}:{$lastDayCol}{$r}");

        $sheet->setCellValue("AL{$r}", 'TOTAL');
        $this->b($sheet, "AL{$r}"); $this->c($sheet, "AL{$r}");
        $this->borders($sheet, "AL{$r}");
        $tableRows[] = $r; // Add project header row to table rows
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Project rows — each project = 4 rows (NORMAL NC, NORMAL COBQ, OT NC, OT COBQ)
        // ══════════════════════════════════════════════════════════════════════
        $firstProjectDataRow = $r; // Track first project data row for coloring
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

        $projNum = 1;
        foreach ($projectRowsData as $proj) {
            $baseRow = $r;

            // Merge C column for project number (4 rows)
            $sheet->mergeCells("C{$r}:C" . ($r + 3));
            $sheet->setCellValue("C{$r}", $projNum);
            $this->c($sheet, "C{$r}");

            // Merge D column for project code (4 rows)
            $sheet->mergeCells("D{$r}:D" . ($r + 3));
            $code = $proj['project_code'];
            $name = $proj['project_name'];
            $displayText = $code . ($name ? ' - ' . $name : '');
            $sheet->setCellValue("D{$r}", $displayText);
            $this->c($sheet, "D{$r}");

            // Row 1: NORMAL NC
            $sheet->mergeCells("E{$r}:E" . ($r + 1));
            $sheet->setCellValue("E{$r}", 'NORMAL');
            $this->c($sheet, "E{$r}");
            $sheet->getStyle("E{$r}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($r)->setRowHeight(20);


            $sheet->setCellValue("F{$r}", 'NC');
            $sheet->getStyle("F{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $this->writeProjectDayRow($sheet, $r, $proj['hours'], 'normal_nc', $daysInMonth, $dayCol);
            $sheet->getRowDimension($r)->setRowHeight(20);

            $r++;

            // Row 2: NORMAL COBQ
            $sheet->setCellValue("F{$r}", 'COBQ');
            $sheet->getStyle("F{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $this->writeProjectDayRow($sheet, $r, $proj['hours'], 'normal_cobq', $daysInMonth, $dayCol);
            $sheet->getRowDimension($r)->setRowHeight(20);
            $r++;

            // Row 3: OT NC
            $sheet->mergeCells("E{$r}:E" . ($r + 1));
            $sheet->setCellValue("E{$r}", 'OT');
            $this->c($sheet, "E{$r}");
            $sheet->getStyle("E{$r}")->getAlignment()->setWrapText(true);

            $sheet->setCellValue("F{$r}", 'NC');
            $sheet->getStyle("F{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $this->writeProjectDayRow($sheet, $r, $proj['hours'], 'ot_nc', $daysInMonth, $dayCol);
            $sheet->getRowDimension($r)->setRowHeight(20);
            $r++;

            // Row 4: OT COBQ
            $sheet->setCellValue("F{$r}", 'COBQ');
            $sheet->getStyle("F{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $this->writeProjectDayRow($sheet, $r, $proj['hours'], 'ot_cobq', $daysInMonth, $dayCol);
            $sheet->getRowDimension($r)->setRowHeight(20);

            // Borders for this 4-row block
            $this->borders($sheet, "C{$baseRow}:C" . ($baseRow + 3));
            $this->borders($sheet, "D{$baseRow}:D" . ($baseRow + 3));
            $this->borders($sheet, "E{$baseRow}:E" . ($baseRow + 1));
            $this->borders($sheet, "E" . ($baseRow + 2) . ":E" . ($baseRow + 3));
            for ($subR = $baseRow; $subR <= $baseRow + 3; $subR++) {
                $this->borders($sheet, "F{$subR}");
                $this->borders($sheet, "AL{$subR}");
                $tableRows[] = $subR; // Add each project row to table rows
            }

            $r++;
            $projNum++;
        }

        $lastProjectDataRow = $r - 1; // Track last project data row for coloring

        //TOTAL EXTERNAL PROJECT ROW
        $sheet->mergeCells("C{$r}:F{$r}");
        $sheet->setCellValue("C{$r}", 'TOTAL EXTERNAL PROJECT');
        $this->b($sheet, "C{$r}");
        $this->borders($sheet, "C{$r}:AL{$r}");
        // Calculate total external hours for each day
        $grandTotalExternal = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            $dayTotal = 0;
            foreach ($projectRowsData as $project) {
                $dayTotal += $project['hours'][$d]['normal_nc'] + $project['hours'][$d]['normal_cobq']
                           + $project['hours'][$d]['ot_nc'] + $project['hours'][$d]['ot_cobq'];
            }
            $grandTotalExternal += $dayTotal;
            $sheet->setCellValue("{$col}{$r}", $dayTotal);
            $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        }
        $sheet->setCellValue("AL{$r}", $grandTotalExternal);
        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$r}")->getFont()->getColor()->setRGB('00B0F0');
        $sheet->getRowDimension($r)->setRowHeight(20);
        $tableRows[] = $r; // Add total external row to table rows
        $r++;

        //spacer
        $sheet->getRowDimension($r)->setRowHeight(10);
        $r++;

        //TOTAL WORKING HOURS ROW
        $sheet->mergeCells("C{$r}:F{$r}");
        $sheet->setCellValue("C{$r}", 'TOTAL WORKING HOURS');
        $this->b($sheet, "C{$r}");
        $this->borders($sheet, "C{$r}:AL{$r}");
        // Calculate total working hours for each day (admin hours + external hours)
        $grandTotalWorking = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
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
            $grandTotalWorking += $dayTotal;
            $sheet->setCellValue("{$col}{$r}", $dayTotal);
            $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        }
        $sheet->setCellValue("AL{$r}", $grandTotalWorking);
        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$r}")->getFont()->getColor()->setRGB('00B0F0');
        $sheet->getRowDimension($r)->setRowHeight(20);
        $tableRows[] = $r; // Add total working hours row to table rows
        $r++;

        //HOURS AVAILABLE ROW
        $sheet->mergeCells("C{$r}:F{$r}");
        $sheet->setCellValue("C{$r}", 'HOURS AVAILABLE');
        $this->b($sheet, "C{$r}");
        $this->borders($sheet, "C{$r}:AL{$r}");
        // Calculate hours available from day metadata
        $totalAvail = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            $avail = $days[$d]['available_hours'];
            $totalAvail += $avail;
            $sheet->setCellValue("{$col}{$r}", $avail);
            $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("{$col}{$r}")->getFont()->getColor()->setRGB('FF0000');
        }
        $sheet->setCellValue("AL{$r}", $totalAvail);
        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$r}")->getFont()->getColor()->setRGB('FF0000');
        $sheet->getRowDimension($r)->setRowHeight(20);
        $tableRows[] = $r; // Add hours available row to table rows
        $r++;
        
        //OVERTIME ROW
        $sheet->mergeCells("C{$r}:F{$r}");
        $sheet->setCellValue("C{$r}", 'OVERTIME');
        $this->b($sheet, "C{$r}");
        $this->borders($sheet, "C{$r}:AL{$r}");
        // Calculate overtime for each day (total working hours - available hours)
        $grandTotalOvertime = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            // Calculate total working hours for this day
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
            $grandTotalOvertime += $dayOvertime;
            $sheet->setCellValue("{$col}{$r}", $dayOvertime);
            $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }
        $sheet->setCellValue("AL{$r}", $grandTotalOvertime);
        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$r}")->getFont()->getColor()->setRGB('00B0F0');
        $sheet->getRowDimension($r)->setRowHeight(20);
        $tableRows[] = $r; // Add overtime row to table rows
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Apply column coloring for weekends (yellow) and public holidays (red)
        // Color admin rows and project data rows (skip header, spacer, and summary rows)
        // ══════════════════════════════════════════════════════════════════════
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            $dayType = $days[$d]['day_type'] ?? 'working';

            if (in_array($dayType, ['off_day', 'public_holiday'])) {
                $color = ($dayType === 'public_holiday') ? 'FF0000' : 'FFFF00';
                // Color admin rows
                $sheet->getStyle("{$col}{$firstAdminRow}:{$col}{$lastAdminRow}")
                      ->getFill()
                      ->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()
                      ->setRGB($color);
                // Color project data rows
                $sheet->getStyle("{$col}{$firstProjectDataRow}:{$col}{$lastProjectDataRow}")
                      ->getFill()
                      ->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()
                      ->setRGB($color);
            }
        }


        //spacer
        $sheet->getRowDimension($r)->setRowHeight(10);
        $r++;
        
        //NOTE / LEGEND
        $sheet->setCellValue("C{$r}", 'NOTE:-');
        $sheet->getStyle("C{$r}")->getFont()->setBold(true)->setSize(8);
        $sheet->mergeCells("F{$r}:G{$r}");
        $sheet->setCellValue("F{$r}", 'LEGEND:');
        $sheet->getStyle("F{$r}")->getFont()->setBold(true)->setSize(8);
        $r++;

        //note 1, legend1, Remarks
        $sheet->mergeCells("C{$r}:E{$r}");
        $sheet->setCellValue("C{$r}", 'NORMAL DAY (EXCLUDE OT) -8 HOURS');
        $sheet->setCellValue("F{$r}", 'NC');
        $sheet->getStyle("C{$r}:F{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("G{$r}", '-');
        $sheet->mergeCells("H{$r}:J{$r}");
        $sheet->setCellValue("H{$r}", 'NORMAL COST');
        $sheet->setCellValue("L{$r}", 'PU');
        $sheet->getStyle("L{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("M{$r}", '-');
        $sheet->mergeCells("N{$r}:P{$r}");
        $sheet->setCellValue("N{$r}", 'PURCHASING');
        $sheet->setCellValue("R{$r}", 'COBQ');
        $sheet->getStyle("R{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("S{$r}", '-');
        $sheet->mergeCells("T{$r}:W{$r}");
        $sheet->setCellValue("T{$r}", 'COST OF BAD QUALITY');
        $sheet->setCellValue("Y{$r}", 'RFQ');
        $sheet->getStyle("Y{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("Z{$r}", '-');
        $sheet->mergeCells("AA{$r}:AD{$r}");
        $sheet->setCellValue("AA{$r}", 'REQUEST FOR QUOTATION');

        //REMARKS
        $sheet->mergeCells("AH{$r}:AL{$r}");
        $sheet->setCellValue("AH{$r}", 'REMARKS:');
        $sheet->getStyle("AH{$r}:AL{$r}")->getBorders()->getRight()->setBorderStyle(self::MEDIUM);
        $sheet->getStyle("AH{$r}:AL{$r}")->getBorders()->getTop()->setBorderStyle(self::MEDIUM);
        $sheet->getStyle("AH{$r}:AL{$r}")->getBorders()->getLeft()->setBorderStyle(self::MEDIUM);
        $sheet->getStyle("AH{$r}:AL{$r}")->getFont()->setBold(true);

        $sheet->getStyle("C{$r}:AL{$r}")->getFont()->setSize(8);
        $r++;

        //note 2, legennd 2, remarks1
        $sheet->mergeCells("C{$r}:E{$r}");
        $sheet->setCellValue("C{$r}", 'FRIDAY ONLY (EXCLUDE OT) -7 HOURS');
        $sheet->setCellValue("F{$r}", 'MKT');
        $sheet->getStyle("C{$r}:F{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("G{$r}", '-');
        $sheet->mergeCells("H{$r}:J{$r}");
        $sheet->setCellValue("H{$r}", 'MARKETING');
        $sheet->setCellValue("L{$r}", 'R&D');
        $sheet->getStyle("L{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("M{$r}", '-');
        $sheet->mergeCells("N{$r}:P{$r}");
        $sheet->setCellValue("N{$r}", 'RESEARCH & DEV');
        $sheet->setCellValue("R{$r}", 'TDR');
        $sheet->getStyle("R{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("S{$r}", '-');
        $sheet->mergeCells("T{$r}:W{$r}");
        $sheet->setCellValue("T{$r}", 'TENDER');
        $sheet->setCellValue("Y{$r}", 'A.S.S');
        $sheet->getStyle("Y{$r}")->getFont()->setBold(true);
        $sheet->setCellValue("Z{$r}", '-');
        $sheet->mergeCells("AA{$r}:AD{$r}");
        $sheet->setCellValue("AA{$r}", 'AFTER SALE SERVICE');

        $sheet->getStyle("C{$r}:AD{$r}")->getFont()->setSize(8);

        //REMARKS 1
        $sheet->mergeCells("AH{$r}:AL{$r}");
        $sheet->setCellValue("AH{$r}", 'SUBMIT TO FINANCE ON 2ND WORKING');
        $sheet->getStyle("AH{$r}:AL{$r}")->getBorders()->getRight()->setBorderStyle(self::MEDIUM);
        $sheet->getStyle("AH{$r}:AL{$r}")->getBorders()->getLeft()->setBorderStyle(self::MEDIUM);

        $sheet->getStyle("AH{$r}:AL{$r}")->getFont()->setSize(8);
        $r++;

        //REMARKS 2
        $sheet->mergeCells("AH{$r}:AL{$r}");
        $sheet->setCellValue("AH{$r}", 'DAYS END OF EACH MONTH');
        $sheet->getStyle("AH{$r}:AL{$r}")->getBorders()->getRight()->setBorderStyle(self::MEDIUM);
        $sheet->getStyle("AH{$r}:AL{$r}")->getBorders()->getLeft()->setBorderStyle(self::MEDIUM);
        $sheet->getStyle("AH{$r}:AL{$r}")->getBorders()->getBottom()->setBorderStyle(self::MEDIUM);

        $sheet->getStyle("F{$r}:AL{$r}")->getFont()->setSize(8);
        $r++;

        $sheet->getRowDimension($r)->setRowHeight(10);
        $r++;

        $sheet->getRowDimension($r)->setRowHeight(5.25);
        $this->borders($sheet, "C{$r}:AL{$r}", 'top');
        $r++;

        // ══════════════════════════════════════════════════════════════════════
        // Apply outer frame borders (medium border on B left, AN right)
        // ══════════════════════════════════════════════════════════════════════
        $lastRow = $r;
        $sheet->getRowDimension($lastRow)->setRowHeight(0.75);

        for ($br = 2; $br <= $lastRow; $br++) {
            $sheet->getStyle("B{$br}")->getBorders()->getLeft()->setBorderStyle(self::MEDIUM);
            $sheet->getStyle("AM{$br}")->getBorders()->getRight()->setBorderStyle(self::MEDIUM);
        }
        // Bottom medium border
        $this->mediumBorder($sheet, "B{$lastRow}:AM{$lastRow}", 'bottom');

        // Thin left border on C and right on AM for inner frame
        for ($br = 3; $br < $lastRow-1; $br++) {
            $sheet->getStyle("C{$br}")->getBorders()->getLeft()->setBorderStyle(self::THIN);
            $sheet->getStyle("AL{$br}")->getBorders()->getRight()->setBorderStyle(self::THIN);
        }

        return $ss;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Write a single project hours row (one of: normal_nc, normal_cobq, ot_nc, ot_cobq)
     */
    private function writeProjectDayRow(
        Worksheet $sheet, int $r, array $hours, string $key,
        int $daysInMonth, callable $dayCol
    ): void {
        $rowTotal = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = $dayCol($d);
            $val = $hours[$d][$key] ?? 0;
            if ($val > 0) {
                $sheet->setCellValue("{$col}{$r}", $val);
                $this->c($sheet, "{$col}{$r}");
                $rowTotal += $val;
            }
            $this->borders($sheet, "{$col}{$r}");
        }
        // SUM formula in AL
        $firstDayCol = $dayCol(1);
        $lastDayCol  = $dayCol($daysInMonth);
        $sheet->setCellValue("AL{$r}", "=SUM({$firstDayCol}{$r}:{$lastDayCol}{$r})");
        $sheet->getStyle("AL{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Build approval stamp boxes in the top-right area (AG-AL, rows 6-10).
     */
    private function buildApprovalStampBoxes(Worksheet $sheet, Timesheet $timesheet, int $startRow, int $endRow): void
    {
        // Stamp box outlines - Prepared By: 3 columns (AE:AG), Checked By: 3 columns (AH:AJ), Verified By: 2 columns (AK:AL)
        $this->borders($sheet, "AE{$startRow}:AG{$endRow}");
        $this->borders($sheet, "AH{$startRow}:AJ{$endRow}");
        $this->borders($sheet, "AK{$startRow}:AL{$endRow}");

        $approvalLogs = $timesheet->approvalLogs ? $timesheet->approvalLogs->sortBy('id') : collect();
        // Stamps go in the merged cells (startRow to endRow)
        $innerRow = $startRow;

        // Prepared By (staff)
        if (!in_array($timesheet->status, ['draft'])) {
            $staffName = $timesheet->staff_signature ?? ($timesheet->user->name ?? '');
            $staffDate = $timesheet->staff_signed_at ? $timesheet->staff_signed_at->format('d/m/Y') : '';
            $this->addStamp($sheet, "AE{$innerRow}", $staffName, $staffDate, 'STAFF');
        }

        // Checked By (HOD/Exec/SPV)
        $hodLog = $approvalLogs->where('level', 0.5)->where('action', 'approved')->first();
        if ($hodLog && $hodLog->user) {
            $hodName = $hodLog->user->name;
            $hodDate = $timesheet->hod_signed_at ? $timesheet->hod_signed_at->format('d/m/Y') : '';
            $this->addStamp($sheet, "AH{$innerRow}", $hodName, $hodDate, 'HOD/EXEC');
        }

        // Verified By (Asst Mgr/Mngr)
        $l1Log = $approvalLogs->where('level', 1)->where('action', 'approved')->first();
        if ($l1Log && $l1Log->user) {
            $l1Name = $l1Log->user->name;
            $l1Date = $timesheet->l1_signed_at ? $timesheet->l1_signed_at->format('d/m/Y') : '';
            $this->addStamp($sheet, "AK{$innerRow}", $l1Name, $l1Date, 'MNGR');
        }
    }

    private function addStamp(Worksheet $sheet, string $cell, string $name, string $date, string $role): void
    {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Stamp');
        $drawing->setDescription('Approval Stamp');

        // Create PNG stamp using GD
        $image = imagecreatetruecolor(80, 80);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);

        $red = imagecolorallocate($image, 220, 38, 38);
        imageellipse($image, 40, 40, 76, 76, $red);
        imageline($image, 40, 2, 40, 78, $red);
        imageline($image, 2, 40, 78, 40, $red);

        // Add TSSB text
        imagestring($image, 3, 28, 22, 'TSSB', $red);
        // Add name
        imagestring($image, 1, 5, 42, strtoupper(substr($name, 0, 18)), $red);
        // Add date
        imagestring($image, 1, 5, 54, $date, $red);

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
        $drawing->setHeight(80);
        $drawing->setWorksheet($sheet);
    }

    // ─── Style helpers ───────────────────────────────────────────────────────

    private function shortName(string $fullName): string
    {
        $name = strtoupper(trim($fullName));
        if (preg_match('/^(.+?)\s+(?:BIN|BINTI)\b/i', $name, $m)) {
            $parts = explode(' ', trim($m[1]));
            return end($parts);
        }
        $parts = explode(' ', $name);
        return end($parts);
    }

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
            case 'top':     $borders->getTop()->setBorderStyle(self::THIN); break;
            case 'bottom':  $borders->getBottom()->setBorderStyle(self::THIN); break;
            case 'left':    $borders->getLeft()->setBorderStyle(self::THIN); break;
            case 'right':   $borders->getRight()->setBorderStyle(self::THIN); break;
            case 'outline': $borders->getOutline()->setBorderStyle(self::THIN); break;
            default:        $borders->getAllBorders()->setBorderStyle(self::THIN); break;
        }
    }

    private function mediumBorder(Worksheet $sheet, string $range, string $side = 'all'): void
    {
        $borders = $sheet->getStyle($range)->getBorders();
        switch ($side) {
            case 'top':     $borders->getTop()->setBorderStyle(self::MEDIUM); break;
            case 'bottom':  $borders->getBottom()->setBorderStyle(self::MEDIUM); break;
            case 'left':    $borders->getLeft()->setBorderStyle(self::MEDIUM); break;
            case 'right':   $borders->getRight()->setBorderStyle(self::MEDIUM); break;
            default:        $borders->getAllBorders()->setBorderStyle(self::MEDIUM); break;
        }
    }

    private function bg(Worksheet $sheet, string $range, string $hex): void
    {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($hex);
    }
}
