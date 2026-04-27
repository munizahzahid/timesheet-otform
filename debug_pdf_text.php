<?php
require __DIR__ . '/vendor/autoload.php';

// Find the most recent PDF in the temp directory, or pass path as argument
$path = $argv[1] ?? null;

if (!$path) {
    // Try to find uploaded PDF from recent uploads
    echo "Usage: php debug_pdf_text.php <path_to_pdf>\n";
    echo "Trying to find PDF in storage...\n";
    
    // Search common temp locations
    $candidates = glob('D:/XAMPP/tmp/*.tmp');
    if (!empty($candidates)) {
        // Get most recent
        usort($candidates, fn($a, $b) => filemtime($b) - filemtime($a));
        $path = $candidates[0];
        echo "Using most recent temp file: {$path}\n";
    }
}

if (!$path || !file_exists($path)) {
    echo "No PDF file found. Please provide a path.\n";
    exit(1);
}

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile($path);

echo "=== FULL TEXT ===\n";
echo $pdf->getText();
echo "\n=== END ===\n";

echo "\n=== PAGE BY PAGE ===\n";
foreach ($pdf->getPages() as $i => $page) {
    echo "--- PAGE " . ($i + 1) . " ---\n";
    echo $page->getText();
    echo "\n";
}
