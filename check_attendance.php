<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get user input
echo "Enter user_id: ";
$userId = trim(fgets(STDIN));
echo "Enter month (1-12): ";
$month = trim(fgets(STDIN));
echo "Enter year: ";
$year = trim(fgets(STDIN));

$recs = \App\Models\AttendanceRecord::where('user_id', $userId)
    ->where('month', $month)
    ->where('year', $year)
    ->orderBy('entry_date')
    ->get(['entry_date', 'time_in', 'time_out', 'hours_worked', 'is_ot', 'ot_hours', 'ot_type', 'day_type']);

echo "\n=== ATTENDANCE RECORDS ===\n";
echo "Date         | Time In | Time Out | Hours | Day Type | is_ot | ot_hours | ot_type\n";
echo "-------------|---------|----------|-------|----------|-------|----------|--------\n";

foreach ($recs as $r) {
    $dow = \Carbon\Carbon::parse($r->entry_date)->dayOfWeek;
    $isWeekend = ($dow === \Carbon\Carbon::SATURDAY || $dow === \Carbon\Carbon::SUNDAY) ? 'YES' : 'NO';
    echo sprintf(
        "%s | %s | %s | %5.1f | %-9s | %s | %8.1f | %s %s\n",
        $r->entry_date,
        $r->time_in ?? '--:--',
        $r->time_out ?? '--:--',
        $r->hours_worked,
        $r->day_type,
        $r->is_ot ? 'Y' : 'N',
        $r->ot_hours,
        $r->ot_type ?? '-',
        $isWeekend === 'YES' ? '[WEEKEND]' : ''
    );
}

echo "\n=== WEEKEND DAYS WITHOUT OT ===\n";
$weekendNoOt = $recs->filter(function ($r) {
    $dow = \Carbon\Carbon::parse($r->entry_date)->dayOfWeek;
    $isWeekend = ($dow === \Carbon\Carbon::SATURDAY || $dow === \Carbon\Carbon::SUNDAY);
    return $isWeekend && !$r->is_ot;
});

foreach ($weekendNoOt as $r) {
    echo $r->entry_date . " | time_in=" . ($r->time_in ?? 'null') . " | time_out=" . ($r->time_out ?? 'null') . " | hours=" . $r->hours_worked . "\n";
}
