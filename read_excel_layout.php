<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;

$file = 'D:\\Users\\User\\Desktop\\INTERN 2026\\MATERIALS\\OT FORM NON EXEC.xls';

$reader = new Xls();
$reader->setReadDataOnly(false);
try {
    $spreadsheet = @$reader->load($file);
} catch (\Throwable $e) {
    echo "Error loading: " . $e->getMessage() . "\n";
    echo "Trying IOFactory...\n";
    $spreadsheet = @IOFactory::load($file);
}
$sheet = $spreadsheet->getActiveSheet();

$highestRow = $sheet->getHighestRow();
$highestCol = $sheet->getHighestColumn();

echo "=== SHEET INFO ===\n";
echo "Title: " . $sheet->getTitle() . "\n";
echo "Highest Row: {$highestRow}\n";
echo "Highest Column: {$highestCol}\n\n";

// Column widths
echo "=== COLUMN WIDTHS ===\n";
foreach (range('A', $highestCol) as $col) {
    $dim = $sheet->getColumnDimension($col);
    $w = $dim->getWidth();
    $auto = $dim->getAutoSize() ? 'auto' : 'fixed';
    echo "{$col}: width={$w} ({$auto})\n";
}

// Row heights
echo "\n=== ROW HEIGHTS ===\n";
for ($r = 1; $r <= min($highestRow, 55); $r++) {
    $h = $sheet->getRowDimension($r)->getRowHeight();
    echo "Row {$r}: height={$h}\n";
}

// Merged cells
echo "\n=== MERGED CELLS ===\n";
foreach ($sheet->getMergeCells() as $range) {
    echo $range . "\n";
}

// Cell values, alignment, and text rotation for first 20 rows
echo "\n=== CELL VALUES (rows 1-20) ===\n";
for ($r = 1; $r <= min($highestRow, 20); $r++) {
    echo "--- Row {$r} ---\n";
    foreach (range('A', $highestCol) as $col) {
        $cell = $sheet->getCell("{$col}{$r}");
        $val = $cell->getValue();
        if ($val === null || $val === '') continue;
        
        $style = $sheet->getStyle("{$col}{$r}");
        $align = $style->getAlignment();
        $rotation = $align->getTextRotation();
        $hAlign = $align->getHorizontal();
        $vAlign = $align->getVertical();
        $wrap = $align->getWrapText() ? 'wrap' : 'no-wrap';
        $bold = $style->getFont()->getBold() ? 'BOLD' : '';
        $size = $style->getFont()->getSize();
        $underline = $style->getFont()->getUnderline();
        
        $borders = '';
        $borderStyles = $style->getBorders();
        if ($borderStyles->getTop()->getBorderStyle() !== 'none') $borders .= 'T';
        if ($borderStyles->getBottom()->getBorderStyle() !== 'none') $borders .= 'B';
        if ($borderStyles->getLeft()->getBorderStyle() !== 'none') $borders .= 'L';
        if ($borderStyles->getRight()->getBorderStyle() !== 'none') $borders .= 'R';
        
        $fill = $style->getFill()->getStartColor()->getRGB();
        
        echo "  {$col}{$r}: \"{$val}\" | rot={$rotation} | h={$hAlign} v={$vAlign} | {$wrap} | {$bold} sz={$size} | borders={$borders} | fill={$fill}\n";
    }
}

// Also dump rows 20-55 for data/footer area
echo "\n=== CELL VALUES (rows 20-55) ===\n";
for ($r = 20; $r <= min($highestRow, 55); $r++) {
    $hasData = false;
    $rowData = "--- Row {$r} ---\n";
    foreach (range('A', $highestCol) as $col) {
        $val = $sheet->getCell("{$col}{$r}")->getValue();
        if ($val === null || $val === '') continue;
        $hasData = true;
        
        $style = $sheet->getStyle("{$col}{$r}");
        $rotation = $style->getAlignment()->getTextRotation();
        $bold = $style->getFont()->getBold() ? 'BOLD' : '';
        $size = $style->getFont()->getSize();
        
        $rowData .= "  {$col}{$r}: \"{$val}\" | rot={$rotation} | {$bold} sz={$size}\n";
    }
    if ($hasData) echo $rowData;
}
