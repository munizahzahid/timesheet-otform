<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\PublicHoliday;
use App\Models\SystemConfig;
use App\Models\Timesheet;
use App\Models\TimesheetAdminHour;
use App\Models\TimesheetDayMetadata;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

class PdfParsingService
{
    protected TimesheetCalculationService $calcService;

    public function __construct(TimesheetCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    /**
     * Parse the Infotech attendance PDF and update timesheet metadata + admin rows.
     *
     * @return array{processed: int, warnings: string[], employee_name: string|null, employee_code: string|null}
     */
    public function parseAndApply(string $filePath, Timesheet $timesheet): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);

        Log::info('PDF parsing started', [
            'timesheet_id' => $timesheet->id,
            'target_period' => "{$timesheet->year}-{$timesheet->month}",
        ]);

        // Process all pages and collect lines
        $allLines = [];
        $pages = $pdf->getPages();
        foreach ($pages as $pageIdx => $page) {
            $pageText = $page->getText();
            $pageLines = preg_split('/\r?\n/', $pageText);
            Log::debug("PDF Page " . ($pageIdx + 1) . " lines: " . count($pageLines));
            foreach ($pageLines as $line) {
                $allLines[] = $line;
            }
        }

        // Debug: write raw lines to project root for analysis (commented out)
        // $debugPath = base_path('pdf_debug_text.txt');
        // file_put_contents($debugPath, implode("\n", $allLines));
        // Log::debug('PDF raw text written to: ' . $debugPath);

        $result = [
            'processed' => 0,
            'warnings'  => [],
            'employee_name' => null,
            'employee_code' => null,
        ];

        // --- 1. Extract employee info ---
        $result = array_merge($result, $this->extractEmployeeInfo($allLines));

        // --- 2. Detect period and warn on mismatch ---
        $pdfPeriod = $this->detectPeriod($allLines);
        if ($pdfPeriod) {
            Log::info('PDF period detected', $pdfPeriod);
            if ($pdfPeriod['month'] !== $timesheet->month || $pdfPeriod['year'] !== $timesheet->year) {
                $pdfMonthName = \DateTime::createFromFormat('!m', $pdfPeriod['month'])->format('F');
                $tsMonthName = \DateTime::createFromFormat('!m', $timesheet->month)->format('F');
                $result['warnings'][] = "Month mismatch: PDF file is for {$pdfMonthName} {$pdfPeriod['year']}, "
                    . "but this timesheet is for {$tsMonthName} {$timesheet->year}. "
                    . "Please upload the correct month's PDF file.";
            }
        }

        // --- 3. Extract data rows ---
        $dataRows = $this->extractDataRows($allLines, $timesheet, $result);

        Log::info('PDF data rows extracted', ['count' => count($dataRows)]);

        if (empty($dataRows)) {
            if (empty($result['warnings'])) {
                $result['warnings'][] = 'No attendance data rows found in the PDF file.';
            }
            return $result;
        }

        // --- 4. Load holidays ---
        $holidays = PublicHoliday::where('year', $timesheet->year)
            ->whereMonth('holiday_date', $timesheet->month)
            ->pluck('name', 'holiday_date')
            ->mapWithKeys(fn($name, $date) => [Carbon::parse($date)->day => $name])
            ->toArray();

        // --- 5. Load system config ---
        $workStart = $this->parseTimeConfig(
            SystemConfig::getValue('working_start_time', '08:30'),
            8, 30
        );
        $lunchMinutes = (int) SystemConfig::getValue('lunch_break_minutes', '60');
        $defaultHoursMon = (float) SystemConfig::getValue('default_working_hours', '8');
        $defaultHoursFri = (float) SystemConfig::getValue('friday_working_hours', '7');

        // --- 6. Process each data row ---
        foreach ($dataRows as $row) {
            $day = $row['day'];
            $date = Carbon::create($timesheet->year, $timesheet->month, $day);
            $dow = $date->dayOfWeek;

            $timeIn = $row['time_in'];
            $timeOut = $row['time_out'];
            $reason = $row['reason'] ?? '';

            // Determine day_type
            // Treat 0:00 times as null (no actual clock data)
            $hasValidClockData = false;
            if ($timeIn && $timeIn->format('H:i') !== '00:00' && $timeIn->format('H:i') !== '00:01') {
                $hasValidClockData = true;
            }
            if ($timeOut && $timeOut->format('H:i') !== '00:00' && $timeOut->format('H:i') !== '00:01') {
                $hasValidClockData = true;
            }

            if ($reason === 'PH' || isset($holidays[$day])) {
                $dayType = 'public_holiday';
                $availableHours = 0;
            } elseif ($dow === Carbon::SATURDAY || $dow === Carbon::SUNDAY) {
                $dayType = 'off_day';
                $availableHours = 0;
            } elseif ($reason === 'CAL' || (!$hasValidClockData && !in_array($dow, [Carbon::SATURDAY, Carbon::SUNDAY]))) {
                // No clock data on weekday = MC/Leave
                $dayType = 'mc';
                $availableHours = $dow === Carbon::FRIDAY ? $defaultHoursFri : $defaultHoursMon;
            } elseif ($dow === Carbon::FRIDAY) {
                $dayType = 'working';
                $availableHours = $defaultHoursFri;
            } else {
                $dayType = 'working';
                $availableHours = $defaultHoursMon;
            }

            // Calculate late hours
            $lateHours = 0;
            if ($timeIn && $dayType === 'working') {
                $workStartTime = (clone $date)->setTime($workStart['h'], $workStart['m']);
                if ($timeIn->gt($workStartTime)) {
                    $lateMin = $timeIn->diffInMinutes($workStartTime);
                    $lateHours = $this->calcService->roundLateHours($lateMin);
                }
            }

            // Calculate OT eligible hours
            $otEligible = 0;
            if ($timeOut) {
                $otStartTime = (clone $date)->setTime(17, 30);
                if ($timeOut->gt($otStartTime)) {
                    $otMin = $timeOut->diffInMinutes($otStartTime);
                    $otEligible = $this->calcService->floorOtHours($otMin / 60);
                }
            }

            // Calculate attendance hours
            $attendanceHours = 0;
            if ($timeIn && $timeOut) {
                $effectiveOut = clone $timeOut;
                if ($effectiveOut->lt($timeIn)) {
                    $effectiveOut = $effectiveOut->addDay();
                }
                $totalMin = $timeIn->diffInMinutes($effectiveOut);
                // Don't subtract lunch break on weekends/off_days
                $lunchToDeduct = ($dayType === 'off_day' || $dayType === 'public_holiday') ? 0 : $lunchMinutes;
                $attendanceHours = round(($totalMin - $lunchToDeduct) / 60, 1);
                if ($attendanceHours < 0) $attendanceHours = 0;
            }

            // Upsert day metadata
            TimesheetDayMetadata::updateOrCreate(
                [
                    'timesheet_id' => $timesheet->id,
                    'entry_date'   => $date->format('Y-m-d'),
                ],
                [
                    'day_type'          => $dayType,
                    'available_hours'   => $availableHours,
                    'time_in'           => $timeIn ? $timeIn->format('H:i:s') : null,
                    'time_out'          => $timeOut ? $timeOut->format('H:i:s') : null,
                    'late_hours'        => $lateHours,
                    'ot_eligible_hours' => $otEligible,
                    'attendance_hours'  => $attendanceHours,
                ]
            );

            // Save attendance record with OT calculation for OT form auto-fill
            $this->saveAttendanceRecord(
                $timesheet->user_id, $date, $dayType,
                $timeIn, $timeOut, $attendanceHours, $reason
            );

            // Auto-populate admin rows (same logic as ExcelParsingService)
            $entryDate = $date->format('Y-m-d');
            $isWorkingDay = ($dayType === 'working');

            // Row 1: MC/LEAVE
            if ($dayType === 'mc') {
                $mcHours = $dow === Carbon::FRIDAY ? $defaultHoursFri : $defaultHoursMon;
                TimesheetAdminHour::updateOrCreate(
                    ['timesheet_id' => $timesheet->id, 'admin_type' => 'mc_leave', 'entry_date' => $entryDate],
                    ['hours' => $mcHours]
                );
            } else {
                TimesheetAdminHour::where('timesheet_id', $timesheet->id)
                    ->where('admin_type', 'mc_leave')
                    ->where('entry_date', $entryDate)
                    ->delete();
            }

            // Row 2: LATE
            TimesheetAdminHour::updateOrCreate(
                ['timesheet_id' => $timesheet->id, 'admin_type' => 'late', 'entry_date' => $entryDate],
                ['hours' => $lateHours]
            );

            // Row 3: MORNING ASSY — default 0.5 on working days
            TimesheetAdminHour::updateOrCreate(
                ['timesheet_id' => $timesheet->id, 'admin_type' => 'morning_assy', 'entry_date' => $entryDate],
                ['hours' => $isWorkingDay ? 0.5 : 0]
            );

            // Row 4: 5S — default 0.5 on working days
            TimesheetAdminHour::updateOrCreate(
                ['timesheet_id' => $timesheet->id, 'admin_type' => 'five_s', 'entry_date' => $entryDate],
                ['hours' => $isWorkingDay ? 0.5 : 0]
            );

            // Rows 5-8: blank (staff fills manually) — only create if not already set
            foreach (['ceramah_event', 'iso', 'training', 'admin_category'] as $blankType) {
                TimesheetAdminHour::firstOrCreate(
                    ['timesheet_id' => $timesheet->id, 'admin_type' => $blankType, 'entry_date' => $entryDate],
                    ['hours' => 0]
                );
            }

            $result['processed']++;
        }

        return $result;
    }

    /**
     * Extract employee info from PDF header lines.
     */
    protected function extractEmployeeInfo(array $lines): array
    {
        $info = ['employee_name' => null, 'employee_code' => null];

        foreach ($lines as $idx => $line) {
            if ($idx > 20) break;

            if (preg_match('/Emp\s*Code\s*:\s*(\S+)/i', $line, $m)) {
                $info['employee_code'] = $m[1];
            }
            if (preg_match('/Name\s*:\s*([A-Z][A-Z\s\/\.\-]+)/i', $line, $m)) {
                $info['employee_name'] = trim($m[1]);
            }
        }

        return $info;
    }

    /**
     * Detect period from PDF header.
     * Looks for "Period : DD-MM-YYYY To DD-MM-YYYY" pattern.
     */
    protected function detectPeriod(array $lines): ?array
    {
        foreach ($lines as $idx => $line) {
            if ($idx > 15) break;

            if (preg_match('/Period\s*:\s*\d{1,2}[-\/](\d{1,2})[-\/](\d{2,4})/i', $line, $m)) {
                $month = (int) $m[1];
                $year = (int) $m[2];
                if ($year < 100) $year += 2000;
                return ['month' => $month, 'year' => $year];
            }
        }
        return null;
    }

    /**
     * Extract attendance data rows from PDF text lines.
     * Returns array of ['day' => int, 'time_in' => Carbon|null, 'time_out' => Carbon|null, 'reason' => string]
     */
    protected function extractDataRows(array $lines, Timesheet $timesheet, array &$result): array
    {
        $dataRows = [];
        $targetMonth = $timesheet->month;
        $targetYear = $timesheet->year;
        $skippedDates = 0;

        // Skip header lines and find data rows
        // Data rows contain dates like 01-03-2026
        foreach ($lines as $lineIdx => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Skip header lines and employee info
            if (stripos($line, 'Emp Code') !== false || 
                stripos($line, 'Name :') !== false ||
                stripos($line, 'Designation') !== false ||
                stripos($line, 'TALENT SYNERGY') !== false ||
                stripos($line, 'Period') !== false ||
                stripos($line, 'Individual Attendance') !== false ||
                stripos($line, 'Date Day') !== false ||
                stripos($line, 'Grand Total') !== false ||
                stripos($line, 'Total :') !== false ||
                stripos($line, 'Cont...') !== false) {
                continue;
            }

            // Look for date pattern DD-MM-YYYY anywhere in the line
            if (!preg_match('/(\d{2})-(\d{2})-(\d{4})/', $line, $dateMatch)) {
                continue;
            }

            $day = (int) $dateMatch[1];
            $month = (int) $dateMatch[2];
            $year = (int) $dateMatch[3];

            if ($month !== $targetMonth || $year !== $targetYear) {
                $skippedDates++;
                continue;
            }

            $baseDate = Carbon::create($targetYear, $targetMonth, $day);

            // Extract time values from the line (format: H.MM like 7.46, 16.49)
            // Only take first TWO time values as time_in and time_out
            $timeValues = [];
            preg_match_all('/(\d{1,2})\.(\d{2})/', $line, $timeMatches, PREG_SET_ORDER);

            // Debug: log all matched times
            $allMatchedTimes = [];
            foreach ($timeMatches as $tm) {
                $h = (int) $tm[1];
                $m = (int) $tm[2];
                if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
                    $allMatchedTimes[] = sprintf('%02d:%02d', $h, $m);
                }
            }
            Log::debug('Time matches found', [
                'day' => $day,
                'all_matches' => $allMatchedTimes,
            ]);

            // Take only first 2 valid time matches
            foreach ($timeMatches as $tm) {
                if (count($timeValues) >= 2) break;
                $h = (int) $tm[1];
                $m = (int) $tm[2];
                if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
                    $timeValues[] = (clone $baseDate)->setTime($h, $m);
                }
            }

            // Extract reason code (PH, CAL, RES, ABS)
            // These codes appear near the date like PHFri20-03-2026, CALTue24-03-2026
            // Search specifically for these codes before the date pattern
            $reason = '';
            // Look for reason code followed by day abbreviation (Mon, Tue, Wed, Thu, Fri, Sat, Sun)
            if (preg_match('/(PH|CAL|RES|ABS)(Mon|Tue|Wed|Thu|Fri|Sat|Sun)/i', $line, $rm)) {
                $reason = strtoupper($rm[1]);
            }

            // In the concatenated format, the first two time values are typically In and Out
            // The format appears to be: InTime OutTime NORMA2 ... Day Date ...
            $timeIn = null;
            $timeOut = null;

            if (count($timeValues) >= 2) {
                $timeIn = $timeValues[0];
                $timeOut = $timeValues[1];
            } elseif (count($timeValues) === 1) {
                $timeIn = $timeValues[0];
            }

            // Skip if no time data and not a special day
            if (empty($timeValues) && empty($reason)) {
                continue;
            }

            Log::debug('PDF row parsed', [
                'day' => $day,
                'times_found' => count($timeValues),
                'in' => $timeIn ? $timeIn->format('H:i') : null,
                'out' => $timeOut ? $timeOut->format('H:i') : null,
                'reason' => $reason,
                'line_snippet' => substr($line, 0, 60),
            ]);

            $dataRows[] = [
                'day'      => $day,
                'time_in'  => $timeIn,
                'time_out' => $timeOut,
                'reason'   => $reason,
            ];
        }

        if ($skippedDates > 0) {
            $result['warnings'][] = "{$skippedDates} rows skipped because their dates don't match this timesheet's month/year.";
        }

        return $dataRows;
    }

    /**
     * Parse a time config string like "08:30" into hours and minutes.
     */
    protected function parseTimeConfig(?string $value, int $defaultH, int $defaultM): array
    {
        if ($value && preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) {
            return ['h' => (int) $m[1], 'm' => (int) $m[2]];
        }
        return ['h' => $defaultH, 'm' => $defaultM];
    }

    /**
     * Save attendance record with OT calculation.
     *
     * OT Rules:
     * - Sat/Sun with time in/out → OT (full hours, type: rest_day)
     * - PH with time in/out → OT (full hours, type: public_holiday)
     * - Workday with time out after 17:30 → OT (min 1 hour, then 0.5 increments, type: normal_day)
     */
    protected function saveAttendanceRecord(
        int $userId,
        Carbon $date,
        string $dayType,
        ?Carbon $timeIn,
        ?Carbon $timeOut,
        float $attendanceHours,
        string $reason
    ): void {
        $isOt = false;
        $otHours = 0;
        $otStartTime = null;
        $otEndTime = null;
        $otType = null;

        $hasValidClock = $timeIn && $timeOut
            && $timeIn->format('H:i') !== '00:00'
            && $timeOut->format('H:i') !== '00:00';

        if ($hasValidClock) {
            $dow = $date->dayOfWeek;

            if ($dow === Carbon::SATURDAY || $dow === Carbon::SUNDAY) {
                // Rest day: all hours are OT
                $isOt = true;
                $otType = 'rest_day';
                $otStartTime = $timeIn->format('H:i:s');
                $otEndTime = $timeOut->format('H:i:s');
                $otHours = $attendanceHours > 0 ? $attendanceHours : 0;
            } elseif ($reason === 'PH' || $dayType === 'public_holiday') {
                // Public holiday: all hours are OT
                $isOt = true;
                $otType = 'public_holiday';
                $otStartTime = $timeIn->format('H:i:s');
                $otEndTime = $timeOut->format('H:i:s');
                $otHours = $attendanceHours > 0 ? $attendanceHours : 0;
            } else {
                // Workday: OT after 17:30, minimum 1 hour, then 0.5 increments
                // Compare only the time portion
                $timeOutHour = (int) $timeOut->format('H');
                $timeOutMinute = (int) $timeOut->format('i');

                if ($timeOutHour > 17 || ($timeOutHour === 17 && $timeOutMinute >= 30)) {
                    // Calculate minutes after 17:30
                    $otMinutes = ($timeOutHour - 17) * 60 + ($timeOutMinute - 30);

                    if ($otMinutes >= 60) {
                        // First full hour, then 0.5 increments
                        $isOt = true;
                        $otType = 'normal_day';
                        $otStartTime = '17:30:00';
                        $otEndTime = $timeOut->format('H:i:s');

                        // Calculate: 1 hour base + 0.5 increments for remainder
                        $remainingMinutes = $otMinutes - 60;
                        $otHours = 1.0 + (floor($remainingMinutes / 30) * 0.5);
                    }
                }
            }
        }

        Log::debug('OT calculation', [
            'date' => $date->format('Y-m-d'),
            'time_out' => $timeOut ? $timeOut->format('H:i:s') : null,
            'is_ot' => $isOt,
            'ot_hours' => $otHours,
            'ot_type' => $otType,
        ]);

        AttendanceRecord::updateOrCreate(
            [
                'user_id' => $userId,
                'entry_date' => $date->format('Y-m-d'),
            ],
            [
                'time_in' => $timeIn ? $timeIn->format('H:i:s') : null,
                'time_out' => $timeOut ? $timeOut->format('H:i:s') : null,
                'hours_worked' => $attendanceHours,
                'reason' => $reason ?: null,
                'day_type' => $dayType,
                'is_ot' => $isOt,
                'ot_hours' => $otHours,
                'ot_start_time' => $otStartTime,
                'ot_end_time' => $otEndTime,
                'ot_type' => $otType,
                'month' => $date->month,
                'year' => $date->year,
            ]
        );
    }
}
