<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\OtForm;
use App\Models\OtFormEntry;
use App\Models\Timesheet;
use App\Models\TimesheetProjectHour;
use App\Models\TimesheetProjectRow;
use Illuminate\Support\Facades\Log;

class OtAutoFillService
{
    /**
     * Auto-fill OT form entries from attendance records.
     *
     * @return array{filled: int, skipped: int, warnings: string[], message: string}
     */
    public function autoFill(OtForm $otForm): array
    {
        $userId = $otForm->user_id;
        $month = $otForm->month;
        $year = $otForm->year;
        $warnings = [];

        // Get attendance records with OT for this user/month
        $attendanceRecords = AttendanceRecord::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('is_ot', true)
            ->where('ot_hours', '>', 0)
            ->get()
            ->keyBy(fn($r) => $r->entry_date->format('Y-m-d'));

        if ($attendanceRecords->isEmpty()) {
            return [
                'filled' => 0,
                'skipped' => 0,
                'warnings' => [],
                'message' => 'No OT-eligible attendance records found for this month. Please upload the attendance PDF in the timesheet first.',
            ];
        }

        // Get project code mapping from timesheet for each OT day
        $projectCodeMap = $this->getProjectCodeMapFromTimesheet($userId, $month, $year);

        Log::info('OT auto-fill started', [
            'ot_form_id' => $otForm->id,
            'user_id' => $userId,
            'month' => $month,
            'year' => $year,
            'ot_records_found' => $attendanceRecords->count(),
            'project_codes_mapped' => count($projectCodeMap),
        ]);

        $entries = $otForm->entries()->get()->keyBy(fn($e) => $e->entry_date->format('Y-m-d'));

        $filled = 0;
        $skipped = 0;

        foreach ($attendanceRecords as $dateStr => $attendance) {
            $entry = $entries->get($dateStr);
            if (!$entry) {
                $skipped++;
                continue;
            }

            // Fill actual time slot from attendance record
            $updateData = [
                'actual_start_time' => $attendance->ot_start_time,
                'actual_end_time' => $attendance->ot_end_time,
                'actual_total_hours' => $attendance->ot_hours,
            ];

            // Auto-fill project code from timesheet
            if (isset($projectCodeMap[$dateStr])) {
                $proj = $projectCodeMap[$dateStr];
                $updateData['project_code_id'] = $proj['project_code_id'];
                $updateData['project_name'] = $proj['project_name'];

                // Validate: compare OT hours from attendance vs timesheet OT hours
                $timesheetOtHours = $proj['ot_hours'];
                if ($timesheetOtHours > 0 && abs($timesheetOtHours - $attendance->ot_hours) > 0.01) {
                    $warnings[] = "Day {$attendance->entry_date->day}: Timesheet OT hours ({$timesheetOtHours}) differs from calculated OT ({$attendance->ot_hours}).";
                }
            } else {
                $warnings[] = "Day {$attendance->entry_date->day}: No project code found in timesheet for this OT day.";
            }

            // Set OT type breakdown hours
            if ($attendance->ot_type === 'normal_day') {
                $updateData['ot_normal_day_hours'] = $attendance->ot_hours;
                $updateData['ot_rest_day_hours'] = 0;
                $updateData['ot_ph_hours'] = 0;
            } elseif ($attendance->ot_type === 'rest_day') {
                $updateData['ot_normal_day_hours'] = 0;
                $updateData['ot_rest_day_hours'] = $attendance->ot_hours;
                $updateData['ot_ph_hours'] = 0;
            } elseif ($attendance->ot_type === 'public_holiday') {
                $updateData['ot_normal_day_hours'] = 0;
                $updateData['ot_rest_day_hours'] = 0;
                $updateData['ot_ph_hours'] = $attendance->ot_hours;
            }

            $entry->update($updateData);
            $filled++;
        }

        // Update total OT hours on the form
        $totalOtHours = $otForm->entries()->sum('actual_total_hours');
        $otForm->update(['total_ot_hours' => $totalOtHours]);

        Log::info('OT auto-fill completed', [
            'ot_form_id' => $otForm->id,
            'filled' => $filled,
            'skipped' => $skipped,
            'total_ot_hours' => $totalOtHours,
            'warnings' => $warnings,
        ]);

        $message = "Auto-filled {$filled} OT entries. Total OT: {$totalOtHours} hours.";
        if (!empty($warnings)) {
            $message .= "\n\nWarnings:\n" . implode("\n", $warnings);
        }

        return [
            'filled' => $filled,
            'skipped' => $skipped,
            'warnings' => $warnings,
            'message' => $message,
        ];
    }

    /**
     * Build a map of date => project code info from the user's timesheet.
     * For each day, find the project row that has OT hours (ot_nc or ot_cobq).
     * Falls back to the project row with the most total hours on that day.
     *
     * @return array<string, array{project_code_id: int, project_name: string, ot_hours: float}>
     */
    protected function getProjectCodeMapFromTimesheet(int $userId, int $month, int $year): array
    {
        $timesheet = Timesheet::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$timesheet) {
            return [];
        }

        $projectRows = TimesheetProjectRow::where('timesheet_id', $timesheet->id)
            ->with(['hours', 'projectCode'])
            ->get();

        $map = [];

        foreach ($projectRows as $row) {
            foreach ($row->hours as $hour) {
                $dateStr = $hour->entry_date->format('Y-m-d');
                $otHours = (float) $hour->ot_nc_hours + (float) $hour->ot_cobq_hours;
                $totalHours = (float) $hour->normal_nc_hours + (float) $hour->normal_cobq_hours + $otHours;

                if ($totalHours <= 0) {
                    continue;
                }

                $existing = $map[$dateStr] ?? null;

                // Prefer the row with OT hours; if tie, prefer higher total hours
                if (!$existing
                    || ($otHours > 0 && ($existing['ot_hours'] ?? 0) <= 0)
                    || ($otHours > 0 && $otHours > ($existing['ot_hours'] ?? 0))
                ) {
                    $map[$dateStr] = [
                        'project_code_id' => $row->project_code_id,
                        'project_name' => $row->projectCode ? $row->projectCode->name : ($row->project_name ?? ''),
                        'ot_hours' => $otHours,
                    ];
                }
            }
        }

        return $map;
    }
}
