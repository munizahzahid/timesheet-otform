<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = 28;
$month = 3;
$year = 2026;

$recs = \App\Models\AttendanceRecord::where('user_id', $userId)
    ->where('month', $month)
    ->where('year', $year)
    ->orderBy('entry_date')
    ->get(['entry_date', 'time_in', 'time_out', 'hours_worked', 'day_type', 'is_ot', 'ot_hours', 'ot_type']);

echo "=== ATTENDANCE RECORDS (user_id=$userId, month=$month, year=$year) ===\n";
echo "Date       | Day  | Time In  | Time Out | Hours | Day Type  | is_ot | ot_hours | ot_type\n";
echo "-----------|------|----------|----------|-------|-----------|-------|----------|--------\n";

foreach ($recs as $r) {
    $dow = \Carbon\Carbon::parse($r->entry_date)->dayOfWeek;
    $dayName = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$dow];
    echo sprintf(
        "%s | %-4s | %-8s | %-8s | %5.1f | %-9s | %s | %8.1f | %s\n",
        $r->entry_date,
        $dayName,
        $r->time_in ?? 'NULL',
        $r->time_out ?? 'NULL',
        $r->hours_worked,
        $r->day_type,
        $r->is_ot ? 'Y' : 'N',
        $r->ot_hours,
        $r->ot_type ?? '-'
    );
}

echo "\n=== is_ot=Y BUT ot_hours=0 (SKIPPED BY AUTOFILL) ===\n";
$skipped = $recs->filter(fn($r) => $r->is_ot && $r->ot_hours <= 0);
foreach ($skipped as $r) {
    echo $r->entry_date . " | time_in=" . ($r->time_in ?? 'NULL') . " | time_out=" . ($r->time_out ?? 'NULL') . " | hours_worked=" . $r->hours_worked . "\n";
}
