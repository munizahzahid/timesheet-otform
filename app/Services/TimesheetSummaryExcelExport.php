<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetSummaryExcelExport
{
    private const THIN = Border::BORDER_THIN;
    private const MEDIUM = Border::BORDER_MEDIUM;
    private const THICK = Border::BORDER_THICK;

    public function generate(int $month, int $year, string $category, $staff, array $adminTypes, array $adminHours, array $projects, array $summary): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Summary');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.25)->setBottom(0.25)->setLeft(0.25)->setRight(0.25);
        $sheet->getSheetView()->setZoomScale(75);

        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);

        $staffIds = $staff->pluck('id')->toArray();
        $rowHourTypes = [
            ['key' => 'normal_nc', 'group' => 'NORMAL', 'cost' => 'NC', 'group_span' => 2],
            ['key' => 'normal_cobq', 'group' => '', 'cost' => 'COBQ', 'group_span' => 0],
            ['key' => 'ot_nc', 'group' => 'OT', 'cost' => 'NC', 'group_span' => 2],
            ['key' => 'ot_cobq', 'group' => '', 'cost' => 'COBQ', 'group_span' => 0],
        ];

        $prefixes = ['MUHAMMAD ', 'MOHAMMAD ', 'MOHAMMED ', 'MUHAMAD ', 'MOHAMED ', 'MOHAMAD ', 'MOHD ', 'MUHD ', 'NURUL ', 'NUR ', 'SITI '];
        $separators = [' BIN ', ' BINTI ', ' B ', ' BT '];
        $displayStaff = [];
        foreach ($staff as $user) {
            $name = strtoupper($user->name);
            foreach ($prefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $name = substr($name, strlen($prefix));
                    break;
                }
            }
            foreach ($separators as $sep) {
                $pos = strpos($name, $sep);
                if ($pos !== false) {
                    $name = substr($name, 0, $pos);
                    break;
                }
            }
            $displayStaff[] = ['id' => $user->id, 'name' => trim($name)];
        }
        while (count($displayStaff) < 13) {
            $displayStaff[] = ['id' => null, 'name' => ''];
        }
        $staffCount = count($displayStaff);

        $staffCol = fn($idx) => $this->columnLetter($idx + 5); // E = 5 (after NO, Description, group, cost)
        $totalCol = $this->columnLetter($staffCount + 5);      // R
        $projectTotalCol = $this->columnLetter($staffCount + 6); // S
        $lastCol = $projectTotalCol;

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(6);
        $sheet->getColumnDimension('D')->setWidth(6);
        for ($i = 0; $i < $staffCount; $i++) {
            $sheet->getColumnDimension($this->columnLetter($i + 5))->setWidth(10);
        }
        $sheet->getColumnDimension($totalCol)->setWidth(11);
        $sheet->getColumnDimension($projectTotalCol)->setWidth(11);

        $r = 1;

        // Title row
        $monthName = strtoupper(Carbon::create($year, $month)->format('F'));
        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        $sheet->setCellValue("A{$r}", 'MONTHLY TIMESHEET SUMMARY - ' . strtoupper(User::CATEGORIES[$category] ?? $category));
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;

        // Month row
        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        $sheet->setCellValue("A{$r}", "MONTH: {$monthName}-" . substr((string) $year, -2));
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $r += 2;

        // Admin Job header
        $startHeader = $r;
        $sheet->mergeCells("A{$r}:A" . ($r + 2));
        $sheet->setCellValue("A{$r}", 'NO');
        $sheet->mergeCells("B{$r}:D" . ($r + 2));
        $sheet->setCellValue("B{$r}", 'ADMIN JOB');
        $sheet->mergeCells("E{$r}:{$this->columnLetter($staffCount + 4)}" . ($r + 1));
        $sheet->setCellValue("E{$r}", 'HOURS');
        $sheet->mergeCells($totalCol . "{$r}:" . $totalCol . ($r + 2));
        $sheet->setCellValue($totalCol . "{$r}", 'TOTAL');
        $sheet->mergeCells($projectTotalCol . "{$r}:" . $projectTotalCol . ($r + 2));
        $sheet->setCellValue($projectTotalCol . "{$r}", '');
        $r++;
        $sheet->mergeCells("E{$r}:{$this->columnLetter($staffCount + 4)}{$r}");
        $sheet->setCellValue("E{$r}", strtoupper(User::CATEGORIES[$category] ?? $category));
        $r++;
        foreach ($displayStaff as $idx => $user) {
            $col = $staffCol($idx);
            $sheet->setCellValue("{$col}{$r}", $user['name']);
            $sheet->getStyle("{$col}{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        }
        $r++;
        $this->applyHeaderStyle($sheet, "A{$startHeader}:{$lastCol}" . ($r - 1));
        $this->blankCol($sheet, "{$projectTotalCol}{$startHeader}:{$projectTotalCol}" . ($r - 1));

        // Admin rows
        $adminIndex = 1;
        foreach ($adminTypes as $type => $label) {
            $sheet->setCellValue("A{$r}", $adminIndex++);
            $sheet->mergeCells("B{$r}:D{$r}");
            $sheet->setCellValue("B{$r}", $label);
            $rowTotal = 0;
            foreach ($displayStaff as $idx => $user) {
                $col = $staffCol($idx);
                $value = $user['id'] ? ($adminHours[$type][$user['id']] ?? 0) : 0;
                $rowTotal += $value;
                $sheet->setCellValue("{$col}{$r}", $value > 0 ? $value : '');
            }
            $sheet->setCellValue("{$totalCol}{$r}", $rowTotal > 0 ? $rowTotal : '');
            $this->borders($sheet, "A{$r}:{$totalCol}{$r}");
            $this->blankCol($sheet, "{$projectTotalCol}{$r}");
            $r++;
        }

        // Total admin job
        $sheet->setCellValue("A{$r}", '');
        $sheet->mergeCells("B{$r}:D{$r}");
        $sheet->setCellValue("B{$r}", 'TOTAL ADMIN JOB');
        $grandAdminTotal = 0;
        foreach ($displayStaff as $idx => $user) {
            $col = $staffCol($idx);
            $userTotal = 0;
            if ($user['id']) {
                foreach ($adminHours as $hours) {
                    $userTotal += $hours[$user['id']] ?? 0;
                }
            }
            $grandAdminTotal += $userTotal;
            $sheet->setCellValue("{$col}{$r}", $userTotal > 0 ? $userTotal : '');
        }
        $sheet->setCellValue("{$totalCol}{$r}", $grandAdminTotal > 0 ? $grandAdminTotal : '');
        $this->borders($sheet, "A{$r}:{$totalCol}{$r}");
        $this->blankCol($sheet, "{$projectTotalCol}{$r}");
        $this->fill($sheet, "A{$r}:{$totalCol}{$r}", 'D9D9D9');
        $r += 2;

        // Project Code header
        $startHeader = $r;
        $sheet->mergeCells("A{$r}:A" . ($r + 1));
        $sheet->setCellValue("A{$r}", 'NO');
        $sheet->mergeCells("B{$r}:B" . ($r + 1));
        $sheet->setCellValue("B{$r}", 'PROJECT CODE');
        $sheet->mergeCells("C{$r}:D{$r}");
        $sheet->setCellValue("C{$r}", 'TIME / COST');
        $sheet->mergeCells("C" . ($r + 1) . ":D" . ($r + 1));
        $sheet->setCellValue("C" . ($r + 1), '');
        foreach ($displayStaff as $idx => $user) {
            $col = $staffCol($idx);
            $sheet->mergeCells("{$col}{$r}:{$col}" . ($r + 1));
            $sheet->setCellValue("{$col}{$r}", '');
        }
        $sheet->mergeCells($totalCol . "{$r}:" . $totalCol . ($r + 1));
        $sheet->setCellValue($totalCol . "{$r}", 'TOTAL');
        $sheet->mergeCells($projectTotalCol . "{$r}:" . $projectTotalCol . ($r + 1));
        $sheet->setCellValue($projectTotalCol . "{$r}", '');
        $r += 2;
        $this->applyHeaderStyle($sheet, "A{$startHeader}:{$lastCol}" . ($r - 1));
        $this->blankCol($sheet, "{$projectTotalCol}{$startHeader}:{$projectTotalCol}" . ($r - 1));

        // Project rows
        $projectIndex = 1;
        foreach ($projects as $project) {
            $pTotal = 0;
            foreach ($displayStaff as $user) {
                if ($user['id']) {
                    $pTotal += ($project['hours'][$user['id']]['normal_nc'] ?? 0)
                        + ($project['hours'][$user['id']]['normal_cobq'] ?? 0)
                        + ($project['hours'][$user['id']]['ot_nc'] ?? 0)
                        + ($project['hours'][$user['id']]['ot_cobq'] ?? 0);
                }
            }

            $projectStartRow = $r;
            foreach ($rowHourTypes as $rIdx => $rowType) {
                if ($rIdx === 0) {
                    $sheet->mergeCells("A{$r}:A" . ($r + 3));
                    $sheet->setCellValue("A{$r}", $projectIndex);
                    $sheet->mergeCells("B{$r}:B" . ($r + 3));
                    $sheet->setCellValue("B{$r}", $project['code'] . "\n" . $project['name']);
                    $sheet->getStyle("B{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->mergeCells("{$projectTotalCol}{$r}:" . $projectTotalCol . ($r + 3));
                    $sheet->setCellValue("{$projectTotalCol}{$r}", $pTotal > 0 ? $pTotal : '');
                    $sheet->getStyle("{$projectTotalCol}{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }
                if ($rowType['group_span'] > 0) {
                    $sheet->mergeCells("C{$r}:C" . ($r + $rowType['group_span'] - 1));
                    $sheet->setCellValue("C{$r}", $rowType['group']);
                }
                $sheet->setCellValue("D{$r}", $rowType['cost']);
                $rowTotal = 0;
                foreach ($displayStaff as $idx => $user) {
                    $col = $staffCol($idx);
                    $value = $user['id'] ? ($project['hours'][$user['id']][$rowType['key']] ?? 0) : 0;
                    $rowTotal += $value;
                    $sheet->setCellValue("{$col}{$r}", $value > 0 ? $value : '');
                }
                $sheet->setCellValue("{$totalCol}{$r}", $rowTotal > 0 ? $rowTotal : '');
                $this->borders($sheet, "A{$r}:{$lastCol}{$r}");
                if ($rIdx === 3) {
                    $this->thickBottom($sheet, "A{$projectStartRow}:A{$r}");
                    $this->thickBottom($sheet, "B{$projectStartRow}:B{$r}");
                    $this->thickBottom($sheet, "C{$r}:{$totalCol}{$r}");
                    $this->thickBottom($sheet, "{$projectTotalCol}{$projectStartRow}:{$projectTotalCol}{$r}");
                }
                $r++;
            }
            $projectIndex++;
        }

        if (empty($projects)) {
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            $sheet->setCellValue("A{$r}", 'No project data found for this category and month.');
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $r++;
        }

        // Summary rows
        $summaryRows = [
            ['label' => 'TOTAL EXTERNAL PROJECT', 'key' => 'total_external_project', 'fill' => 'DDEBF7'],
            ['label' => 'TOTAL WORKING HOURS', 'key' => 'total_working_hours', 'fill' => 'E2EFDA'],
            ['label' => 'HOURS AVAILABLE', 'key' => 'hours_available', 'fill' => 'D9D9D9'],
            ['label' => 'OVERTIME', 'key' => 'overtime', 'fill' => 'DDEBF7'],
        ];

        foreach ($summaryRows as $sRow) {
            $sheet->mergeCells("A{$r}:D{$r}");
            $sheet->setCellValue("A{$r}", $sRow['label']);
            $showZero = in_array($sRow['key'], ['hours_available', 'overtime']);
            $grandTotal = 0;
            foreach ($displayStaff as $idx => $user) {
                $col = $staffCol($idx);
                $actualValue = $user['id'] ? ($summary[$user['id']][$sRow['key']] ?? 0) : 0;
                if ($sRow['key'] === 'overtime') {
                    $userWorking = $user['id'] ? ($summary[$user['id']]['total_working_hours'] ?? 0) : 0;
                    $userAvailable = $user['id'] ? ($summary[$user['id']]['hours_available'] ?? 0) : 0;
                    $value = $userWorking - $userAvailable;
                } else {
                    $value = $actualValue;
                }
                $grandTotal += $value;
                $sheet->setCellValue("{$col}{$r}", $showZero || $value != 0 ? $value : '');
                if ($value < 0) {
                    $sheet->getStyle("{$col}{$r}")->getFont()->getColor()->setRGB('FF0000');
                }
            }
            $sheet->setCellValue("{$totalCol}{$r}", $showZero || $grandTotal != 0 ? $grandTotal : '');
            if ($grandTotal < 0) {
                $sheet->getStyle("{$totalCol}{$r}")->getFont()->getColor()->setRGB('FF0000');
            }
            $this->borders($sheet, "A{$r}:{$totalCol}{$r}");
            $this->fill($sheet, "A{$r}:{$totalCol}{$r}", $sRow['fill']);
            if ($sRow['label'] === 'TOTAL EXTERNAL PROJECT') {
                $sheet->setCellValue("{$projectTotalCol}{$r}", $grandTotal != 0 ? $grandTotal : '');
                $this->borders($sheet, "{$projectTotalCol}{$r}");
                if ($grandTotal < 0) {
                    $sheet->getStyle("{$projectTotalCol}{$r}")->getFont()->getColor()->setRGB('FF0000');
                }
            } else {
                $sheet->setCellValue("{$projectTotalCol}{$r}", '');
                $this->blankCol($sheet, "{$projectTotalCol}{$r}");
            }
            $r++;
        }

        return $ss;
    }

    private function columnLetter(int $idx): string
    {
        $letter = '';
        while ($idx > 0) {
            $idx--;
            $letter = chr(65 + ($idx % 26)) . $letter;
            $idx = intdiv($idx, 26);
        }
        return $letter;
    }

    private function borders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(self::THIN);
    }

    private function thickBottom(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(self::THICK);
    }

    private function blankCol(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_NONE);
        $borders = $style->getBorders();
        $borders->getTop()->setBorderStyle(Border::BORDER_NONE);
        $borders->getRight()->setBorderStyle(Border::BORDER_NONE);
        $borders->getBottom()->setBorderStyle(Border::BORDER_NONE);
        $borders->getLeft()->setBorderStyle(self::THIN);
    }

    private function fill(Worksheet $sheet, string $range, string $color): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
    }

    private function applyHeaderStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $this->borders($sheet, $range);
        $this->fill($sheet, $range, 'F2F2F2');
    }
}
