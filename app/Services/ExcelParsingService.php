<?php

namespace App\Services;

use App\Models\PublicHoliday;
use App\Models\SystemConfig;
use App\Models\Timesheet;
use App\Models\TimesheetAdminHour;
use App\Models\TimesheetDayMetadata;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ExcelParsingService
{
    protected TimesheetCalculationService $calcService;

    public function __construct(TimesheetCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    /**
     * Parse the Infotech attendance Excel and update timesheet metadata + admin rows.
     *
     * @return array{processed: int, warnings: string[], employee_name: string|null, employee_code: string|null}
     */
    public function parseAndApply(string $filePath, Timesheet $timesheet): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Use formatData=false to get raw cell values (Excel serial dates, numeric times)
        $rawRows = $sheet->toArray(null, true, false, true);
        // Also get formatted rows for text scanning (employee info, headers)
        $fmtRows = $sheet->toArray(null, true, true, true);

        Log::info('Excel parsing started', [
            'timesheet_id' => $timesheet->id,
            'target_period' => "{$timesheet->year}-{$timesheet->month}",
            'total_rows' => count($rawRows),
        ]);

        $result = [
            'processed' => 0,
            'warnings'  => [],
            'employee_name' => null,
            'employee_code' => null,
        ];

        // --- 1. Extract employee info + period from header area ---
        $result = array_merge($result, $this->extractEmployeeInfo($fmtRows));

        // --- 1b. Detect Excel period and warn about mismatch ---
        $excelPeriod = $this->detectPeriod($fmtRows);
        if ($excelPeriod) {
            Log::info('Excel period detected', $excelPeriod);
            if ($excelPeriod['month'] !== $timesheet->month || $excelPeriod['year'] !== $timesheet->year) {
                $excelMonthName = \DateTime::createFromFormat('!m', $excelPeriod['month'])->format('F');
                $tsMonthName = \DateTime::createFromFormat('!m', $timesheet->month)->format('F');
                $result['warnings'][] = "Month mismatch: Excel file is for {$excelMonthName} {$excelPeriod['year']}, "
                    . "but this timesheet is for {$tsMonthName} {$timesheet->year}. "
                    . "Please upload the correct month's Excel file.";
            }
        }

        // --- 2. Find data rows (after header, before "Total") ---
        $dataRows = $this->extractDataRows($rawRows, $fmtRows, $timesheet, $result);

        Log::info('Excel data rows extracted', ['count' => count($dataRows)]);

        if (empty($dataRows)) {
            if (empty($result['warnings'])) {
                $result['warnings'][] = 'No attendance data rows found in the Excel file.';
            }
            return $result;
        }

        // --- 3. Load holidays for this month ---
        $holidays = PublicHoliday::where('year', $timesheet->year)
            ->whereMonth('holiday_date', $timesheet->month)
            ->pluck('name', 'holiday_date')
            ->mapWithKeys(fn($name, $date) => [Carbon::parse($date)->day => $name])
            ->toArray();

        // --- 4. Load system config for working hours ---
        $workStart = $this->parseTimeConfig(
            SystemConfig::getValue('working_start_time', '08:30'),
            8, 30
        );
        $otStart = $this->parseTimeConfig(
            SystemConfig::getValue('ot_start_time', '17:30'),
            17, 30
        );
        $lunchMinutes = (int) SystemConfig::getValue('lunch_break_minutes', '60');
        $defaultHoursMon = (float) SystemConfig::getValue('default_working_hours', '8');
        $defaultHoursFri = (float) SystemConfig::getValue('friday_working_hours', '7');

        // --- 5. Process each data row ---
        foreach ($dataRows as $row) {
            $day = $row['day'];
            $date = Carbon::create($timesheet->year, $timesheet->month, $day);
            $dow = $date->dayOfWeek; // 0=Sun, 6=Sat

            $timeIn = $row['time_in'];   // Carbon or null
            $timeOut = $row['time_out'];  // Carbon or null

            // Determine day_type
            if (isset($holidays[$day])) {
                $dayType = 'public_holiday';
                $availableHours = 0;
            } elseif ($dow === Carbon::SATURDAY || $dow === Carbon::SUNDAY) {
                $dayType = 'off_day';
                $availableHours = 0;
            } elseif ($timeIn === null && $timeOut === null && !in_array($dow, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                // No clock data on a weekday → MC/Leave
                $dayType = 'mc';
                $availableHours = 0;
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
                $otStartTime = (clone $date)->setTime($otStart['h'], $otStart['m']);
                // If timeOut is past midnight, it's next day
                if ($timeOut->lt($timeIn) && $timeIn) {
                    $timeOut = $timeOut->addDay();
                }
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
                $attendanceHours = round(($totalMin - $lunchMinutes) / 60, 1);
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

            // Auto-populate admin rows
            $entryDate = $date->format('Y-m-d');
            $isWorkingDay = ($dayType === 'working');

            // Row 1: MC/LEAVE — full day hours (8 Mon-Thu, 7 Fri) when no clock data on weekday
            if ($dayType === 'mc') {
                $mcHours = $dow === Carbon::FRIDAY ? $defaultHoursFri : $defaultHoursMon;
                TimesheetAdminHour::updateOrCreate(
                    ['timesheet_id' => $timesheet->id, 'admin_type' => 'mc_leave', 'entry_date' => $entryDate],
                    ['hours' => $mcHours]
                );
            } else {
                // Clear MC/Leave if day is not MC (e.g., re-upload after correction)
                TimesheetAdminHour::where('timesheet_id', $timesheet->id)
                    ->where('admin_type', 'mc_leave')
                    ->where('entry_date', $entryDate)
                    ->delete();
            }

            // Row 2: LATE — 0.5 increments based on time_in
            TimesheetAdminHour::updateOrCreate(
                ['timesheet_id' => $timesheet->id, 'admin_type' => 'late', 'entry_date' => $entryDate],
                ['hours' => $lateHours]
            );

            // Row 3: MORNING ASSY / ADMIN JOB — default 0.5 on working days
            if ($isWorkingDay) {
                TimesheetAdminHour::updateOrCreate(
                    ['timesheet_id' => $timesheet->id, 'admin_type' => 'morning_assy', 'entry_date' => $entryDate],
                    ['hours' => 0.5]
                );
            } else {
                TimesheetAdminHour::updateOrCreate(
                    ['timesheet_id' => $timesheet->id, 'admin_type' => 'morning_assy', 'entry_date' => $entryDate],
                    ['hours' => 0]
                );
            }

            // Row 4: 5S — default 0.5 on working days
            if ($isWorkingDay) {
                TimesheetAdminHour::updateOrCreate(
                    ['timesheet_id' => $timesheet->id, 'admin_type' => 'five_s', 'entry_date' => $entryDate],
                    ['hours' => 0.5]
                );
            } else {
                TimesheetAdminHour::updateOrCreate(
                    ['timesheet_id' => $timesheet->id, 'admin_type' => 'five_s', 'entry_date' => $entryDate],
                    ['hours' => 0]
                );
            }

            // Rows 5-8: Default blank (0) — staff fills manually
            foreach (['ceramah_event', 'iso', 'training', 'admin_category'] as $blankType) {
                // Only create if not already set (don't overwrite manual entries on re-upload)
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
     * Extract employee info from the header rows of the Excel.
     */
    protected function extractEmployeeInfo(array $rows): array
    {
        $info = ['employee_name' => null, 'employee_code' => null];

        foreach ($rows as $rowNum => $cells) {
            if ($rowNum > 15) break; // Only scan first 15 rows

            $rowText = implode(' ', array_filter(array_map('strval', $cells)));

            // Look for "Emp Code:" pattern
            if (preg_match('/Emp\s*Code\s*:\s*(\S+)/i', $rowText, $m)) {
                $info['employee_code'] = $m[1];
            }

            // Look for "Name:" pattern
            if (preg_match('/Name\s*:\s*([A-Z][A-Z\s\/\.\-]+)/i', $rowText, $m)) {
                $info['employee_name'] = trim($m[1]);
            }
        }

        return $info;
    }

    /**
     * Detect the period (month/year) from the header rows of the Excel.
     * Looks for "Period : DD-MM-YYYY To DD-MM-YYYY" pattern.
     */
    protected function detectPeriod(array $fmtRows): ?array
    {
        foreach ($fmtRows as $rowNum => $cells) {
            if ($rowNum > 10) break;

            $rowText = implode(' ', array_filter(array_map('strval', $cells)));

            // Pattern: "Period : 01-02-2026 To 28-02-2026"
            if (preg_match('/Period\s*:\s*\d{1,2}[-\/](\d{1,2})[-\/](\d{2,4})/i', $rowText, $m)) {
                $month = (int) $m[1];
                $year = (int) $m[2];
                if ($year < 100) $year += 2000;
                return ['month' => $month, 'year' => $year];
            }
        }
        return null;
    }

    /**
     * Extract attendance data rows from the spreadsheet.
     * Uses raw rows for date/time parsing, formatted rows for text detection.
     * Returns array of ['day' => int, 'time_in' => Carbon|null, 'time_out' => Carbon|null]
     */
    protected function extractDataRows(array $rawRows, array $fmtRows, Timesheet $timesheet, array &$result): array
    {
        $dataRows = [];
        $targetMonth = $timesheet->month;
        $targetYear = $timesheet->year;
        $foundHeader = false;
        $skippedDates = 0;

        foreach ($rawRows as $rowNum => $rawCells) {
            $fmtCells = $fmtRows[$rowNum] ?? $rawCells;
            $fmtA = trim((string) ($fmtCells['A'] ?? ''));
            $rawA = $rawCells['A'] ?? null;

            // Detect header row (contains "Date" in column A)
            if (!$foundHeader) {
                if (stripos($fmtA, 'Date') !== false) {
                    $foundHeader = true;
                    Log::info("Excel: header row found at row {$rowNum}");
                }
                continue;
            }

            // Skip the employee info row (contains "Emp" or "Code")
            if (stripos($fmtA, 'Emp') !== false || stripos($fmtA, 'Code') !== false) {
                continue;
            }

            // Stop at "Total" row
            if (stripos($fmtA, 'Total') !== false) {
                break;
            }

            // Skip empty rows
            if ($rawA === null || $rawA === '') {
                continue;
            }

            // Parse the date from column A (raw value)
            $parsedDate = $this->parseExcelDate($rawA, $fmtA, $targetYear);

            if (!$parsedDate) {
                Log::debug("Excel row {$rowNum}: could not parse date", ['rawA' => $rawA, 'fmtA' => $fmtA]);
                continue;
            }

            // Verify the date is in the target month
            if ($parsedDate->month !== $targetMonth || $parsedDate->year !== $targetYear) {
                $skippedDates++;
                continue;
            }

            $day = $parsedDate->day;
            $baseDate = Carbon::create($targetYear, $targetMonth, $day);

            // Column layout: A=Date, B=Day, C=Clk1, D=Clk2, E=Clk3, F=Clk4, G=Time In, H=Time Out
            $timeIn = $this->parseClockTime($rawCells['G'] ?? null, $fmtCells['G'] ?? null, $baseDate);
            $timeOut = $this->parseClockTime($rawCells['H'] ?? null, $fmtCells['H'] ?? null, $baseDate);

            // Fallback: try Clk1 (C) for time in, Clk2 (D) for time out
            if (!$timeIn) {
                $timeIn = $this->parseClockTime($rawCells['C'] ?? null, $fmtCells['C'] ?? null, $baseDate);
            }
            if (!$timeOut) {
                $timeOut = $this->parseClockTime($rawCells['D'] ?? null, $fmtCells['D'] ?? null, $baseDate);
            }

            $dataRows[] = [
                'day'      => $day,
                'time_in'  => $timeIn,
                'time_out' => $timeOut,
            ];
        }

        if ($skippedDates > 0) {
            $result['warnings'][] = "{$skippedDates} rows skipped because their dates don't match this timesheet's month/year.";
        }

        return $dataRows;
    }

    /**
     * Parse Excel date value from raw and formatted cell values.
     */
    protected function parseExcelDate($rawValue, string $fmtValue, int $targetYear): ?Carbon
    {
        // 1. Handle Excel serial date number (raw numeric, e.g. 46054 = 2026-02-01)
        if (is_numeric($rawValue) && (float) $rawValue > 25000) {
            try {
                $dateTime = ExcelDate::excelToDateTimeObject((float) $rawValue);
                return Carbon::instance($dateTime)->startOfDay();
            } catch (\Throwable $e) {
                // fall through
            }
        }

        // 2. Parse from formatted string: DD-MM-YY, DD-MM-YYYY, DD/MM/YY, DD/MM/YYYY
        $str = trim((string) ($fmtValue ?: $rawValue));

        if (preg_match('/(\d{1,2})[-\/](\d{1,2})[-\/](\d{2,4})/', $str, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($year < 100) $year += 2000;
            // Sanity check
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return Carbon::create($year, $month, $day)->startOfDay();
            }
        }

        return null;
    }

    /**
     * Parse a clock time from raw and formatted cell values.
     * Handles: Excel time fractions (0.xxx), H.MM format (5.05), HH:MM strings.
     */
    protected function parseClockTime($rawValue, $fmtValue, Carbon $baseDate): ?Carbon
    {
        // Skip nulls and zeros
        if ($rawValue === null || $rawValue === '' || $rawValue === 0 || $rawValue === 0.0) {
            // Also check if formatted value has meaningful time
            if ($fmtValue === null || $fmtValue === '' || $fmtValue === '0' || $fmtValue === '0.00') {
                return null;
            }
        }

        // 1. Raw numeric: Excel time fraction (0 < val < 1 means time-only)
        //    or datetime (val > 25000 means date+time)
        if (is_numeric($rawValue)) {
            $numVal = (float) $rawValue;

            // Pure time fraction: 0.2118 = 5:05 AM, 0.8889 = 21:20
            if ($numVal > 0 && $numVal < 1) {
                $totalMinutes = round($numVal * 24 * 60);
                $hours = (int) floor($totalMinutes / 60);
                $minutes = (int) ($totalMinutes % 60);
                return (clone $baseDate)->setTime($hours, $minutes);
            }

            // DateTime serial: date portion + time fraction
            if ($numVal > 25000) {
                try {
                    $dateTime = ExcelDate::excelToDateTimeObject($numVal);
                    $carbon = Carbon::instance($dateTime);
                    return (clone $baseDate)->setTime($carbon->hour, $carbon->minute);
                } catch (\Throwable $e) {
                    // fall through
                }
            }
        }

        // 2. Try formatted string: "H:MM", "HH:MM", "H:MM:SS"
        $strVal = trim((string) ($fmtValue ?: $rawValue));
        if (preg_match('/^(\d{1,2}):(\d{2})/', $strVal, $m)) {
            $hours = (int) $m[1];
            $minutes = (int) $m[2];
            if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59) {
                return (clone $baseDate)->setTime($hours, $minutes);
            }
        }

        // 3. H.MM format (displayed as decimal, e.g., 5.05 = 5:05, 21.32 = 21:32)
        if (is_numeric($strVal)) {
            $floatVal = (float) $strVal;
            if ($floatVal >= 1 && $floatVal <= 23.59) {
                $hours = (int) floor($floatVal);
                $decimalPart = $floatVal - $hours;
                $minutes = (int) round($decimalPart * 100);
                if ($minutes >= 60) {
                    // Fallback: treat as decimal hours
                    $minutes = (int) round($decimalPart * 60);
                }
                if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59) {
                    return (clone $baseDate)->setTime($hours, $minutes);
                }
            }
        }

        return null;
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
}
