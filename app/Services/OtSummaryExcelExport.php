<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OtSummaryExcelExport
{
    private const THIN = Border::BORDER_THIN;
    private const THICK = Border::BORDER_THICK;

    public function generate(int $month, int $year, string $category, $staff, array $projects, array $totals): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('OT Summary');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.25)->setBottom(0.25)->setLeft(0.25)->setRight(0.25);
        $sheet->getSheetView()->setZoomScale(75);

        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);

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

        $staffCol = fn($idx) => $this->columnLetter($idx + 3); // C = 3 (after NO, PROJECT)
        $staffCount = count($displayStaff);
        $totalCol = $this->columnLetter($staffCount + 3);
        $lastCol = $totalCol;

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(34);
        for ($i = 0; $i < $staffCount; $i++) {
            $sheet->getColumnDimension($this->columnLetter($i + 3))->setWidth(10);
        }
        $sheet->getColumnDimension($totalCol)->setWidth(12);

        $r = 1;

        // Title row
        $monthName = strtoupper(Carbon::create($year, $month)->format('F'));
        $categoryLabel = $category === 'all' ? 'ALL STAFF' : strtoupper($category);
        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        $sheet->setCellValue("A{$r}", 'OT SUMMARY - ' . $categoryLabel);
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;

        // Month row
        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        $sheet->setCellValue("A{$r}", "MONTH: {$monthName}-" . substr((string) $year, -2));
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $r += 2;

        // Header
        $startHeader = $r;
        $sheet->setCellValue("A{$r}", 'NO');
        $sheet->setCellValue("B{$r}", 'PROJECT');
        foreach ($displayStaff as $idx => $user) {
            $col = $staffCol($idx);
            $sheet->setCellValue("{$col}{$r}", $user['name']);
            $sheet->getStyle("{$col}{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        }
        $sheet->setCellValue("{$totalCol}{$r}", 'TOTAL');
        $this->applyHeaderStyle($sheet, "A{$startHeader}:{$lastCol}{$r}");
        $r++;

        // Project rows
        $projectIndex = 1;
        foreach ($projects as $project) {
            $sheet->setCellValue("A{$r}", $projectIndex++);
            $sheet->setCellValue("B{$r}", $project['code'] . "\n" . $project['name']);
            $sheet->getStyle("B{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
            $rowTotal = 0;
            foreach ($displayStaff as $idx => $user) {
                $col = $staffCol($idx);
                $value = $user['id'] ? ($project['hours'][$user['id']] ?? 0) : 0;
                $rowTotal += $value;
                $sheet->setCellValue("{$col}{$r}", $value != 0 ? $value : '');
            }
            $sheet->setCellValue("{$totalCol}{$r}", $rowTotal != 0 ? $rowTotal : '');
            $this->borders($sheet, "A{$r}:{$lastCol}{$r}");
            $r++;
        }

        if (empty($projects)) {
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            $sheet->setCellValue("A{$r}", 'No OT data found for this period.');
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $r++;
        }

        // Total row
        $grandTotal = 0;
        foreach ($totals as $total) {
            $grandTotal += $total;
        }
        $sheet->setCellValue("A{$r}", '');
        $sheet->setCellValue("B{$r}", 'TOTAL OT HOURS');
        foreach ($displayStaff as $idx => $user) {
            $col = $staffCol($idx);
            $value = $user['id'] ? ($totals[$user['id']] ?? 0) : 0;
            $sheet->setCellValue("{$col}{$r}", $value != 0 ? $value : '');
        }
        $sheet->setCellValue("{$totalCol}{$r}", $grandTotal != 0 ? $grandTotal : '');
        $this->borders($sheet, "A{$r}:{$lastCol}{$r}");
        $this->fill($sheet, "A{$r}:{$lastCol}{$r}", 'DDEBF7');

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
