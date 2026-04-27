<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\SystemConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculateAttendanceOt extends Command
{
    protected $signature = 'attendance:recalc-ot {user_id} {month} {year}';
    protected $description = 'Recalculate OT hours for existing attendance records (fix weekend lunch deduction bug)';

    public function handle()
    {
        $userId = (int) $this->argument('user_id');
        $month = (int) $this->argument('month');
        $year = (int) $this->argument('year');

        $this->info("Recalculating OT for user_id={$userId}, month={$month}, year={$year}");

        $records = AttendanceRecord::where('user_id', $userId)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        if ($records->isEmpty()) {
            $this->error("No attendance records found for this user/month/year.");
            return;
        }

        $lunchMinutes = (int) SystemConfig::getValue('lunch_break_minutes', '60');
        $updated = 0;

        foreach ($records as $rec) {
            if (!$rec->time_in || !$rec->time_out) {
                continue;
            }

            $date = Carbon::parse($rec->entry_date);
            $timeIn = Carbon::parse($rec->time_in);
            $timeOut = Carbon::parse($rec->time_out);

            // Recalculate attendance hours without lunch on weekends
            $effectiveOut = clone $timeOut;
            if ($effectiveOut->lt($timeIn)) {
                $effectiveOut = $effectiveOut->addDay();
            }
            $totalMin = $timeIn->diffInMinutes($effectiveOut);

            $dow = $date->dayOfWeek;
            $isWeekend = ($dow === Carbon::SATURDAY || $dow === Carbon::SUNDAY);
            $isPH = ($rec->day_type === 'public_holiday');

            $lunchToDeduct = ($isWeekend || $isPH) ? 0 : $lunchMinutes;
            $attendanceHours = round(($totalMin - $lunchToDeduct) / 60, 1);
            if ($attendanceHours < 0) $attendanceHours = 0;

            // Recalculate OT
            $isOt = false;
            $otHours = 0;
            $otStartTime = null;
            $otEndTime = null;
            $otType = null;

            if ($isWeekend) {
                $isOt = true;
                $otType = 'rest_day';
                $otStartTime = $rec->time_in;
                $otEndTime = $rec->time_out;
                $otHours = $attendanceHours > 0 ? $attendanceHours : 0;
            } elseif ($isPH) {
                $isOt = true;
                $otType = 'public_holiday';
                $otStartTime = $rec->time_in;
                $otEndTime = $rec->time_out;
                $otHours = $attendanceHours > 0 ? $attendanceHours : 0;
            } else {
                // Workday: OT after 17:30, min 1 hour
                $timeOutHour = (int) $timeOut->format('H');
                $timeOutMinute = (int) $timeOut->format('i');

                if ($timeOutHour > 17 || ($timeOutHour === 17 && $timeOutMinute >= 30)) {
                    $otMinutes = ($timeOutHour - 17) * 60 + ($timeOutMinute - 30);

                    if ($otMinutes >= 60) {
                        $isOt = true;
                        $otType = 'normal_day';
                        $otStartTime = '17:30:00';
                        $otEndTime = $rec->time_out;
                        $remainingMinutes = $otMinutes - 60;
                        $otHours = 1.0 + (floor($remainingMinutes / 30) * 0.5);
                    }
                }
            }

            $rec->update([
                'hours_worked' => $attendanceHours,
                'is_ot' => $isOt,
                'ot_hours' => $otHours,
                'ot_start_time' => $otStartTime,
                'ot_end_time' => $otEndTime,
                'ot_type' => $otType,
            ]);

            $this->line("Updated {$rec->entry_date}: is_ot=" . ($isOt ? 'Y' : 'N') . ", ot_hours={$otHours}");
            $updated++;
        }

        $this->info("Done. Updated {$updated} records.");
    }
}
