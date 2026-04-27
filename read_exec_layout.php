<?php
require __DIR__ . '/vendor/autoload.php';

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
$ss = $reader->load('D:/Users/User/Desktop/INTERN 2026/MATERIALS/OT FORM EXEC.xlsx');
$sheet = $ss->getActiveSheet();

$maxRow = 40;
$cols = array_merge(range('A', 'Z'), ['AA', 'AB', 'AC', 'AD']);

echo "Sheet: " . $sheet->getTitle() . PHP_EOL;

echo PHP_EOL . "=== COLUMN WIDTHS ===" . PHP_EOL;
foreach ($cols as $c) {
    $w = $sheet->getColumnDimension($c)->getWidth();
    if ($w > 0) echo "$c = " . round($w, 2) . PHP_EOL;
}

echo PHP_EOL . "=== ROW HEIGHTS ===" . PHP_EOL;
for ($r = 1; $r <= $maxRow; $r++) {
    $h = $sheet->getRowDimension($r)->getRowHeight();
    echo "Row $r = " . ($h > 0 ? round($h, 2) : 'auto') . PHP_EOL;
}

echo PHP_EOL . "=== CELL VALUES ===" . PHP_EOL;
for ($r = 1; $r <= $maxRow; $r++) {
    foreach ($cols as $c) {
        $v = $sheet->getCell($c . $r)->getValue();
        if ($v !== null && $v !== '') {
            echo "$c$r = " . (is_object($v) ? '[RichText]' : str_replace("\n", "\\n", $v)) . PHP_EOL;
        }
    }
}

echo PHP_EOL . "=== MERGED CELLS ===" . PHP_EOL;
foreach ($sheet->getMergeCells() as $range) {
    echo $range . PHP_EOL;
}

echo PHP_EOL . "=== FONT/STYLE DETAILS ===" . PHP_EOL;
for ($r = 1; $r <= $maxRow; $r++) {
    foreach ($cols as $c) {
        $v = $sheet->getCell($c . $r)->getValue();
        if ($v !== null && $v !== '') {
            $style = $sheet->getStyle($c . $r);
            $font = $style->getFont();
            $align = $style->getAlignment();
            $borders = $style->getBorders();
            $info = [];
            if ($font->getBold()) $info[] = 'bold';
            if ($font->getSize() != 11) $info[] = 'size=' . $font->getSize();
            if ($font->getUnderline() !== 'none') $info[] = 'underline';
            if ($align->getTextRotation() != 0) $info[] = 'rot=' . $align->getTextRotation();
            if ($align->getHorizontal()) $info[] = 'h=' . $align->getHorizontal();
            if ($align->getVertical()) $info[] = 'v=' . $align->getVertical();
            if ($align->getWrapText()) $info[] = 'wrap';
            $fill = $style->getFill();
            if ($fill->getFillType() !== 'none' && $fill->getStartColor()->getARGB() !== 'FF000000') {
                $info[] = 'bg=' . $fill->getStartColor()->getARGB();
            }
            // borders
            $bStyles = [];
            foreach (['top', 'bottom', 'left', 'right'] as $side) {
                $b = $borders->{'get' . ucfirst($side)}();
                if ($b->getBorderStyle() !== 'none') {
                    $bStyles[] = $side . '=' . $b->getBorderStyle();
                }
            }
            if (!empty($bStyles)) $info[] = 'borders(' . implode(',', $bStyles) . ')';
            if (!empty($info)) {
                echo "$c$r: " . implode(', ', $info) . PHP_EOL;
            }
        }
    }
}

echo PHP_EOL . "=== BORDER GRID (rows 3-40) ===" . PHP_EOL;
for ($r = 3; $r <= 40; $r++) {
    $rowBorders = [];
    foreach ($cols as $c) {
        $borders = $sheet->getStyle($c . $r)->getBorders();
        $bStyles = [];
        foreach (['top', 'bottom', 'left', 'right'] as $side) {
            $b = $borders->{'get' . ucfirst($side)}();
            if ($b->getBorderStyle() !== 'none') {
                $bStyles[] = substr($side, 0, 1) . '=' . $b->getBorderStyle();
            }
        }
        if (!empty($bStyles)) {
            $rowBorders[] = "$c: " . implode(',', $bStyles);
        }
    }
    if (!empty($rowBorders)) {
        echo "Row $r: " . implode(' | ', $rowBorders) . PHP_EOL;
    }
}

echo PHP_EOL . "=== RICHTEXT DETAILS ===" . PHP_EOL;
foreach ([39, 40] as $r) {
    foreach ($cols as $c) {
        $v = $sheet->getCell($c . $r)->getValue();
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            echo "$c$r RichText elements:" . PHP_EOL;
            foreach ($v->getRichTextElements() as $elem) {
                $text = $elem->getText();
                if ($elem instanceof \PhpOffice\PhpSpreadsheet\RichText\Run) {
                    $f = $elem->getFont();
                    $info = [];
                    if ($f->getBold()) $info[] = 'bold';
                    if ($f->getUnderline() !== 'none') $info[] = 'underline';
                    if ($f->getSize()) $info[] = 'size=' . $f->getSize();
                    echo "  Run: '$text' [" . implode(', ', $info) . "]" . PHP_EOL;
                } else {
                    echo "  Text: '$text'" . PHP_EOL;
                }
            }
        }
    }
}

// Check what's in rows 9-11 (company checkboxes area)
echo PHP_EOL . "=== ROWS 8-11 CELL VALUES ===" . PHP_EOL;
for ($r = 8; $r <= 11; $r++) {
    foreach ($cols as $c) {
        $v = $sheet->getCell($c . $r)->getValue();
        if ($v !== null && $v !== '') {
            echo "$c$r = " . (is_object($v) ? '[RichText]' : str_replace("\n", "\\n", $v)) . PHP_EOL;
        }
    }
}

// Check approval/signature area rows 37-40
echo PHP_EOL . "=== ROWS 37-40 FULL STYLE ===" . PHP_EOL;
for ($r = 37; $r <= 40; $r++) {
    foreach ($cols as $c) {
        $style = $sheet->getStyle($c . $r);
        $borders = $style->getBorders();
        $bStyles = [];
        foreach (['top', 'bottom', 'left', 'right'] as $side) {
            $b = $borders->{'get' . ucfirst($side)}();
            if ($b->getBorderStyle() !== 'none') {
                $bStyles[] = substr($side, 0, 1) . '=' . $b->getBorderStyle();
            }
        }
        if (!empty($bStyles)) {
            echo "$c$r: " . implode(',', $bStyles) . PHP_EOL;
        }
    }
}
