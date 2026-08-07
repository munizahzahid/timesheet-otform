<?php

namespace App\Services;

use App\Models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GanttExcelExport
{
    private const THIN = Border::BORDER_THIN;

    private array $colors = [
        'plan'    => '2563EB',
        'revise'  => 'F97316',
        'actual'  => '16A34A',
        'effective' => '86EFAC',
    ];

    public function generate(Project $project, array $effectiveDates, array $visible, string $zoom = 'day'): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Gantt Chart');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $phases = $project->phases;
        $standaloneTasks = $project->tasks;

        $allDates = collect();
        foreach ($phases as $phase) {
            foreach ($phase->tasks as $task) {
                $this->collectDates($task, $effectiveDates[$task->id] ?? null, $allDates);
            }
        }
        foreach ($standaloneTasks as $task) {
            $this->collectDates($task, $effectiveDates[$task->id] ?? null, $allDates);
        }

        if ($allDates->isEmpty()) {
            $timelineStart = now()->copy()->subDays(30)->startOfDay();
            $timelineEnd = now()->copy()->addDays(90)->startOfDay();
        } else {
            $timelineStart = $allDates->min()->copy()->subDays(7)->startOfDay();
            $timelineEnd = $allDates->max()->copy()->addDays(7)->startOfDay();
        }
        $totalDays = max(1, $timelineStart->diffInDays($timelineEnd) + 1);

        $timeBlocks = $this->buildTimeBlocks($timelineStart, $timelineEnd, $zoom);
        $timeColCount = count($timeBlocks);

        $colIndex = 1;
        $colMap = [
            'no'      => $this->colLetter($colIndex++),
            'task'    => $this->colLetter($colIndex++),
            'assignee' => $this->colLetter($colIndex++),
        ];
        for ($i = 0; $i < $timeColCount; $i++) {
            $colMap['time_' . $i] = $this->colLetter($colIndex++);
        }
        $lastCol = $this->colLetter($colIndex - 1);

        // --- Header info ---
        $logoPath = public_path('images/Logo TSSB.jpeg');
        if (file_exists($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Logo TSSB');
            $drawing->setPath($logoPath);
            $drawing->setCoordinates('A1');
            $drawing->setHeight(40);
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension(1)->setRowHeight(30);
        }

        $sheet->setCellValue("A2", $project->project_name . ' (' . ($project->project_code ?? 'N/A') . ')');
        $sheet->getStyle("A2")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->setCellValue("C1", 'Date Last Update: ' . ($project->date_time_updated?->format('d/m/Y') ?? now()->format('d/m/Y')));
        $sheet->setCellValue("C2", 'Prepared By: ' . ($project->updated_by ?? $project->project_manager ?? '-'));
        $sheet->setCellValue("C3", 'Checked By: ' . ($project->project_manager ?? '-'));
        for ($r = 1; $r <= 3; $r++) {
            $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("C{$r}")->getFont()->setSize(8);
        }

        $row = 5;

        // --- Multi-row time header ---
        $yearRow = $row;
        $monthRow = $row + 1;
        $blockRow = $row + 2;

        if ($zoom !== 'year') {
            $current = $timelineStart->copy()->startOfYear();
            while ($current <= $timelineEnd) {
                $yStart = $current->copy();
                $yEnd = $current->copy()->endOfYear();
                if ($yEnd > $timelineEnd) $yEnd = $timelineEnd->copy();
                $startIdx = $this->findBlockIndex($timeBlocks, $yStart, $yEnd);
                if ($startIdx !== null) {
                    $blockCount = 0;
                    for ($i = $startIdx; $i < $timeColCount && $timeBlocks[$i]['startDate'] <= $yEnd; $i++) {
                        $blockCount++;
                    }
                    if ($blockCount > 0) {
                        $startCol = $this->colLetter(4 + $startIdx);
                        $endCol = $this->colLetter(4 + $startIdx + $blockCount - 1);
                        $sheet->mergeCells("{$startCol}{$yearRow}:{$endCol}{$yearRow}");
                        $sheet->setCellValue("{$startCol}{$yearRow}", $current->format('Y'));
                    }
                }
                $current->addYear()->startOfYear();
            }
        }

        if ($zoom === 'day' || $zoom === 'week') {
            $current = $timelineStart->copy()->startOfMonth();
            while ($current <= $timelineEnd) {
                $mStart = $current->copy()->startOfMonth();
                $mEnd = $current->copy()->endOfMonth();
                if ($mStart < $timelineStart) $mStart = $timelineStart->copy();
                if ($mEnd > $timelineEnd) $mEnd = $timelineEnd->copy();
                $startIdx = $this->findBlockIndex($timeBlocks, $mStart, $mEnd);
                if ($startIdx !== null) {
                    $blockCount = 0;
                    for ($i = $startIdx; $i < $timeColCount && $timeBlocks[$i]['startDate'] <= $mEnd; $i++) {
                        $blockCount++;
                    }
                    if ($blockCount > 0) {
                        $startCol = $this->colLetter(4 + $startIdx);
                        $endCol = $this->colLetter(4 + $startIdx + $blockCount - 1);
                        $sheet->mergeCells("{$startCol}{$monthRow}:{$endCol}{$monthRow}");
                        $sheet->setCellValue("{$startCol}{$monthRow}", $current->format('M'));
                    }
                }
                $current->addMonth()->startOfMonth();
            }
        }

        foreach ($timeBlocks as $i => $block) {
            $col = $colMap['time_' . $i];
            $sheet->setCellValue("{$col}{$blockRow}", $block['label']);
        }

        $sheet->mergeCells("{$colMap['no']}{$yearRow}:{$colMap['no']}{$blockRow}");
        $sheet->setCellValue("{$colMap['no']}{$yearRow}", 'No.');
        $sheet->mergeCells("{$colMap['task']}{$yearRow}:{$colMap['task']}{$blockRow}");
        $sheet->setCellValue("{$colMap['task']}{$yearRow}", 'Task');
        $sheet->mergeCells("{$colMap['assignee']}{$yearRow}:{$colMap['assignee']}{$blockRow}");
        $sheet->setCellValue("{$colMap['assignee']}{$yearRow}", 'Assigned To');

        for ($r = $yearRow; $r <= $blockRow; $r++) {
            for ($c = 1; $c <= ($timeColCount + 3); $c++) {
                $col = $this->colLetter($c);
                $sheet->getStyle("{$col}{$r}")->getFont()->setBold(true);
                $sheet->getStyle("{$col}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new Color('E8E8E8'));
                $sheet->getStyle("{$col}{$r}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("{$col}{$r}")->getBorders()->getAllBorders()->setBorderStyle(self::THIN);
            }
        }

        $row = $blockRow + 1;

        // --- Data rows ---
        $dataStartRow = $row;
        $rowNum = 1;
        $groupEndRows = [];

        $types = ['plan', 'revise', 'actual'];
        foreach ($phases as $phase) {
            $phaseStartRow = $row;
            foreach ($types as $type) {
                for ($c = 1; $c <= ($timeColCount + 3); $c++) {
                    $col = $this->colLetter($c);
                    $sheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color('D9D9D9'));
                    $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
                }
                $this->writePhaseRow($sheet, $row, $phase, $timeBlocks, $colMap, $visible, $type);
                $row++;
            }
            $phaseEndRow = $row - 1;
            $sheet->mergeCells("{$colMap['no']}{$phaseStartRow}:{$colMap['no']}{$phaseEndRow}");
            $sheet->mergeCells("{$colMap['task']}{$phaseStartRow}:{$colMap['task']}{$phaseEndRow}");
            $sheet->mergeCells("{$colMap['assignee']}{$phaseStartRow}:{$colMap['assignee']}{$phaseEndRow}");
            $groupEndRows[] = $phaseEndRow;

            foreach ($phase->tasks as $task) {
                $rowNum++;
                $eff = $effectiveDates[$task->id] ?? null;
                $taskStartRow = $row;
                foreach ($types as $type) {
                    $this->writeTaskRow($sheet, $row, $rowNum, $task, $eff, $timeBlocks, $colMap, $visible, $type);
                    $row++;
                }
                $taskEndRow = $row - 1;
                $sheet->mergeCells("{$colMap['no']}{$taskStartRow}:{$colMap['no']}{$taskEndRow}");
                $sheet->mergeCells("{$colMap['task']}{$taskStartRow}:{$colMap['task']}{$taskEndRow}");
                $sheet->mergeCells("{$colMap['assignee']}{$taskStartRow}:{$colMap['assignee']}{$taskEndRow}");
                $groupEndRows[] = $taskEndRow;
            }
        }

        if ($standaloneTasks->count()) {
            for ($c = 1; $c <= ($timeColCount + 3); $c++) {
                $col = $this->colLetter($c);
                $sheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new Color('D9D9D9'));
                $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
            }
            $sheet->setCellValue("{$colMap['task']}{$row}", 'Standalone Tasks');
            $row++;

            foreach ($standaloneTasks as $task) {
                $rowNum++;
                $eff = $effectiveDates[$task->id] ?? null;
                $taskStartRow = $row;
                foreach ($types as $type) {
                    $this->writeTaskRow($sheet, $row, $rowNum, $task, $eff, $timeBlocks, $colMap, $visible, $type);
                    $row++;
                }
                $taskEndRow = $row - 1;
                $sheet->mergeCells("{$colMap['no']}{$taskStartRow}:{$colMap['no']}{$taskEndRow}");
                $sheet->mergeCells("{$colMap['task']}{$taskStartRow}:{$colMap['task']}{$taskEndRow}");
                $sheet->mergeCells("{$colMap['assignee']}{$taskStartRow}:{$colMap['assignee']}{$taskEndRow}");
                $groupEndRows[] = $taskEndRow;
            }
        }

        $dataEndRow = $row - 1;

        if ($dataEndRow >= $dataStartRow) {
            $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataEndRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(self::THIN);
        }

        foreach ($groupEndRows as $endRow) {
            $this->setBoldBottomBorder($sheet, $endRow, $timeColCount);
        }

        $sheet->getColumnDimension($colMap['no'])->setWidth(5);
        $sheet->getColumnDimension($colMap['task'])->setWidth(35);
        $sheet->getColumnDimension($colMap['assignee'])->setWidth(22);
        $sheet->getStyle("{$colMap['task']}1:{$colMap['task']}{$dataEndRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("{$colMap['assignee']}1:{$colMap['assignee']}{$dataEndRow}")->getAlignment()->setWrapText(true);
        for ($i = 0; $i < $timeColCount; $i++) {
            $col = $colMap['time_' . $i];
            $sheet->getColumnDimension($col)->setWidth(4);
        }

        $sheet->freezePane($colMap['time_0'] . ($blockRow + 1));

        return $ss;
    }

    private function collectDates($task, ?array $effective, $allDates): void
    {
        if ($effective && $effective['start_date']) $allDates->push($effective['start_date']->copy());
        if ($effective && $effective['end_date']) $allDates->push($effective['end_date']->copy());
        if ($task->start_date_plan) $allDates->push($task->start_date_plan->copy());
        if ($task->end_date_plan) $allDates->push($task->end_date_plan->copy());
        if ($task->start_date_actual) $allDates->push($task->start_date_actual->copy());
        if ($task->end_date_actual) $allDates->push($task->end_date_actual->copy());
        if ($task->start_date_revise) $allDates->push($task->start_date_revise->copy());
        if ($task->end_date_revise) $allDates->push($task->end_date_revise->copy());
    }

    private function buildTimeBlocks($timelineStart, $timelineEnd, string $zoom): array
    {
        $timeBlocks = [];
        if ($zoom === 'day') {
            $current = $timelineStart->copy();
            while ($current <= $timelineEnd) {
                $timeBlocks[] = [
                    'label'     => $current->format('d'),
                    'startDate' => $current->copy()->startOfDay(),
                    'endDate'   => $current->copy()->endOfDay(),
                ];
                $current->addDay();
            }
        } elseif ($zoom === 'week') {
            $current = $timelineStart->copy()->startOfWeek();
            $weekIndex = 1;
            while ($current <= $timelineEnd) {
                $blockEnd = $current->copy()->endOfWeek();
                if ($blockEnd > $timelineEnd) $blockEnd = $timelineEnd->copy()->endOfDay();
                $timeBlocks[] = [
                    'label'     => 'W' . $weekIndex,
                    'startDate' => $current->copy()->startOfWeek(),
                    'endDate'   => $blockEnd,
                ];
                $current->addWeek();
                $weekIndex++;
            }
        } elseif ($zoom === 'month') {
            $current = $timelineStart->copy()->startOfMonth();
            while ($current <= $timelineEnd) {
                $blockEnd = $current->copy()->endOfMonth();
                if ($blockEnd > $timelineEnd) $blockEnd = $timelineEnd->copy()->endOfDay();
                $timeBlocks[] = [
                    'label'     => $current->format('M'),
                    'startDate' => $current->copy()->startOfMonth(),
                    'endDate'   => $blockEnd,
                ];
                $current->addMonth()->startOfMonth();
            }
        } else {
            $current = $timelineStart->copy()->startOfYear();
            while ($current <= $timelineEnd) {
                $blockEnd = $current->copy()->endOfYear();
                if ($blockEnd > $timelineEnd) $blockEnd = $timelineEnd->copy()->endOfDay();
                $timeBlocks[] = [
                    'label'     => $current->format('Y'),
                    'startDate' => $current->copy()->startOfYear(),
                    'endDate'   => $blockEnd,
                ];
                $current->addYear()->startOfYear();
            }
        }
        return $timeBlocks;
    }

    private function findBlockIndex($timeBlocks, $start, $end): ?int
    {
        foreach ($timeBlocks as $i => $block) {
            if ($block['startDate'] >= $start && $block['startDate'] <= $end) {
                return $i;
            }
        }
        return null;
    }

    private function writeTaskRow(Worksheet $sheet, int $row, int $rowNum, $task, ?array $effective, array $timeBlocks, array $colMap, array $visible, string $type): void
    {
        $sheet->setCellValue("{$colMap['no']}{$row}", $rowNum);
        $sheet->setCellValue("{$colMap['task']}{$row}", $task->task_name);
        $sheet->setCellValue("{$colMap['assignee']}{$row}", $task->assignedTo?->name ?? '-');

        if ($visible[$type]) {
            foreach ($timeBlocks as $i => $block) {
                $col = $colMap['time_' . $i];
                $colorType = $this->resolveRowCellType($block, $task, $type, $effective);
                if ($colorType) {
                    $sheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color($this->colors[$colorType]));
                }
            }
        }
    }

    private function writePhaseRow(Worksheet $sheet, int $row, $phase, array $timeBlocks, array $colMap, array $visible, string $type): void
    {
        $sheet->setCellValue("{$colMap['task']}{$row}", $phase->phase_name);

        if ($visible[$type]) {
            foreach ($timeBlocks as $i => $block) {
                $col = $colMap['time_' . $i];
                $colorType = $this->resolveRowCellType($block, $phase, $type, null);
                if ($colorType) {
                    $sheet->getStyle("{$col}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color($this->colors[$colorType]));
                }
            }
        }
    }

    private function resolveRowCellType($block, $task, string $type, ?array $effective): ?string
    {
        $cellStart = $block['startDate'];
        $cellEnd = $block['endDate'];

        if ($type === 'plan' && $task->start_date_plan && $task->end_date_plan) {
            $taskStart = $task->start_date_plan->copy()->startOfDay();
            $taskEnd = $task->end_date_plan->copy()->endOfDay();
            if ($cellStart <= $taskEnd && $cellEnd >= $taskStart) {
                return 'plan';
            }
        }

        if ($type === 'revise' && $task->start_date_revise && $task->end_date_revise) {
            $taskStart = $task->start_date_revise->copy()->startOfDay();
            $taskEnd = $task->end_date_revise->copy()->endOfDay();
            if ($cellStart <= $taskEnd && $cellEnd >= $taskStart) {
                return 'revise';
            }
        }

        if ($type === 'actual') {
            if ($task->start_date_actual) {
                $taskStart = $task->start_date_actual->copy()->startOfDay();
                $taskEnd = ($task->end_date_actual ?? now())->copy()->endOfDay();
                if ($cellStart <= $taskEnd && $cellEnd >= $taskStart) {
                    return 'actual';
                }
            }
            if ($effective && $effective['start_date'] && $effective['end_date']) {
                $taskStart = $effective['start_date']->copy()->startOfDay();
                $taskEnd = $effective['end_date']->copy()->endOfDay();
                if ($cellStart <= $taskEnd && $cellEnd >= $taskStart) {
                    return 'effective';
                }
            }
        }

        return null;
    }

    private function setBoldBottomBorder(Worksheet $sheet, int $row, int $timeColCount): void
    {
        for ($c = 1; $c <= ($timeColCount + 3); $c++) {
            $col = $this->colLetter($c);
            $sheet->getStyle("{$col}{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        }
    }

    private function colLetter(int $n): string
    {
        $result = '';
        while ($n > 0) {
            $n--;
            $result = chr(65 + ($n % 26)) . $result;
            $n = intdiv($n, 26);
        }
        return $result;
    }
}
