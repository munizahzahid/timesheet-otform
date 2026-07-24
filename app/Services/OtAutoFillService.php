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

        // Get all attendance records for this user/month (from PDF Infotech)
        $allAttendanceRecords = AttendanceRecord::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->get()
            ->keyBy(fn($r) => $r->entry_date->format('Y-m-d'));

        if ($allAttendanceRecords->isEmpty()) {
            return [
                'filled' => 0,
                'skipped' => 0,
                'warnings' => [],
                'message' => 'No attendance records found for this month. Please upload the PDF Attendance from Infotech in your Timesheet first.',
            ];
        }

        // Get project code mapping from timesheet (days with ot_nc or ot_cobq hours)
        $projectCodeMap = $this->getProjectCodeMapFromTimesheet($userId, $month, $year);

        Log::info('OT auto-fill started', [
            'ot_form_id' => $otForm->id,
            'user_id' => $userId,
            'month' => $month,
            'year' => $year,
            'attendance_records' => $allAttendanceRecords->count(),
            'project_codes_mapped' => count($projectCodeMap),
        ]);

        // Iterate over ALL OT form entries
        $entries = $otForm->entries()->get();

        $filled = 0;
        $projectOnlyFilled = 0;
        $skipped = 0;
        $phDates = [];

        foreach ($entries as $entry) {
            $dateStr = $entry->entry_date->format('Y-m-d');
            $attendance = $allAttendanceRecords->get($dateStr);
            $hasTimesheetOt = isset($projectCodeMap[$dateStr]) && ($projectCodeMap[$dateStr]['ot_hours'] ?? 0) > 0;

            // Skip only when there is absolutely no source of data for this date
            if (!$attendance && !$hasTimesheetOt) {
                $skipped++;
                continue;
            }

            $updateData = [];

            // Public Holiday is authoritative from the PDF attendance record
            if ($attendance) {
                $isPH = $attendance->day_type === 'public_holiday';
                $updateData['is_public_holiday'] = $isPH;
                if ($isPH) {
                    $phDates[] = $entry->entry_date->format('j M');
                }
            }

            // Determine whether attendance has valid clock in/out OT data
            $attendanceHasOt = $attendance && $attendance->is_ot && $attendance->ot_hours > 0;

            if ($attendanceHasOt) {
                // PDF has OT clock data: fill actual hours + project code from timesheet
                $updateData['actual_start_time'] = $attendance->ot_start_time;
                $updateData['actual_end_time'] = $attendance->ot_end_time;
                $updateData['actual_total_hours'] = floor($attendance->ot_hours * 4) / 4;

                // Auto-fill project code from timesheet
                if ($hasTimesheetOt) {
                    $proj = $projectCodeMap[$dateStr];
                    $updateData['project_code_id'] = $proj['project_code_id'];
                    $updateData['project_name'] = $proj['project_name'];
                    $updateData['project_category'] = $proj['project_category'] ?? null;
                    $updateData['manual_project_code_name'] = $proj['manual_project_code_name'] ?? null;
                }

                // Set OT type breakdown hours (floor to 0.25 increments)
                $updateData['ot_type'] = $attendance->ot_type;
                $flooredHours = floor($attendance->ot_hours * 4) / 4;
                if ($attendance->ot_type === 'normal_day') {
                    $updateData['ot_normal_day_hours'] = $flooredHours;
                    $updateData['ot_rest_day_hours'] = 0;
                    $updateData['ot_rest_day_excess_hours'] = 0;
                    $updateData['ot_rest_day_count'] = 0;
                    $updateData['ot_ph_hours'] = 0;
                } elseif ($attendance->ot_type === 'rest_day') {
                    $updateData['ot_normal_day_hours'] = 0;
                    $updateData['ot_rest_day_hours'] = floor(min($attendance->ot_hours, 8.0) * 4) / 4;
                    $updateData['ot_rest_day_excess_hours'] = floor(max(0, $attendance->ot_hours - 8.0) * 4) / 4;
                    $updateData['ot_rest_day_count'] = 1;
                    $updateData['ot_ph_hours'] = 0;
                } elseif ($attendance->ot_type === 'public_holiday') {
                    $updateData['ot_normal_day_hours'] = 0;
                    $updateData['ot_rest_day_hours'] = 0;
                    $updateData['ot_rest_day_excess_hours'] = 0;
                    $updateData['ot_rest_day_count'] = 0;
                    $updateData['ot_ph_hours'] = $flooredHours;
                }
                $filled++;
            } elseif ($hasTimesheetOt) {
                // PDF has no OT clock data (or no attendance record for this exact date),
                // but timesheet has OT hours — fill project code/name only
                $proj = $projectCodeMap[$dateStr];
                $updateData['project_code_id'] = $proj['project_code_id'];
                $updateData['project_name'] = $proj['project_name'];
                $updateData['project_category'] = $proj['project_category'] ?? null;
                $updateData['manual_project_code_name'] = $proj['manual_project_code_name'] ?? null;
                $projectOnlyFilled++;
            }

            $entry->update($updateData);
        }

        // Update total OT hours on the form (floor to 0.25 increments)
        $totalOtHours = floor($otForm->entries()->sum('actual_total_hours') * 4) / 4;
        $otForm->update(['total_ot_hours' => $totalOtHours]);

        Log::info('OT auto-fill completed', [
            'ot_form_id' => $otForm->id,
            'filled' => $filled,
            'project_only_filled' => $projectOnlyFilled,
            'skipped' => $skipped,
            'total_ot_hours' => $totalOtHours,
            'ph_dates' => $phDates,
            'warnings' => $warnings,
        ]);

        // Build informative message
        $parts = [];
        if ($filled > 0) {
            $parts[] = "Auto-filled {$filled} entries with OT hours from attendance.";
        }
        if ($projectOnlyFilled > 0) {
            $parts[] = "Filled project code/name for {$projectOnlyFilled} entries from timesheet (PDF had no OT clock data / no attendance record for those dates).";
        }
        if ($filled === 0 && $projectOnlyFilled === 0) {
            $parts[] = "No OT-eligible entries found in attendance and no OT hours in timesheet.";
        }

        if (!empty($phDates)) {
            $parts[] = "Public Holidays updated: " . implode(', ', $phDates) . ".";
        } else {
            $parts[] = "No Public Holidays found in attendance.";
        }

        $message = implode("\n", $parts);
        if (!empty($warnings)) {
            $message .= "\n\nWarnings:\n" . implode("\n", $warnings);
        }

        return [
            'filled' => $filled,
            'project_only_filled' => $projectOnlyFilled,
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
                    // Build project name: use project code name, or category + manual name
                    $projectName = $row->projectCode
                        ? $row->projectCode->name
                        : ($row->project_name ?? '');

                    if (!$projectName && $row->project_category) {
                        $projectName = $row->project_category
                            . ($row->manual_project_code_name ? ' - ' . $row->manual_project_code_name : '');
                    }

                    $map[$dateStr] = [
                        'project_code_id' => $row->project_code_id,
                        'project_name' => $projectName,
                        'project_category' => $row->project_category,
                        'manual_project_code_name' => $row->manual_project_code_name,
                        'ot_hours' => $otHours,
                    ];
                }
            }
        }

        return $map;
    }
}
