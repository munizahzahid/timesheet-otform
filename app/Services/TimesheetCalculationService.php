<?php

namespace App\Services;

use App\Models\PublicHoliday;
use Carbon\Carbon;

class TimesheetCalculationService
{
    public const ADMIN_TYPES = [
        'mc_leave'       => 'MC / LEAVE',
        'late'           => 'LATE',
        'morning_assy'   => 'MORNING ASSY / ADMIN JOB',
        'five_s'         => '5S',
        'ceramah_event'  => 'CERAMAH AGAMA / EVENT / ADP',
        'iso'            => 'ISO',
        'training'       => 'TRAINING / SEMINAR / VISIT',
        'admin_category' => 'RFQ / MKT / PUR / R&D / A.S.S / TDR',
    ];

    /**
     * Generate day metadata for a given month/year.
     * Returns array indexed by day number (1-31).
     */
    public function generateDayMetadata(int $month, int $year): array
    {
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $holidays = PublicHoliday::where('year', $year)
            ->whereMonth('holiday_date', $month)
            ->pluck('name', 'holiday_date')
            ->mapWithKeys(fn($name, $date) => [Carbon::parse($date)->day => $name])
            ->toArray();

        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d);
            $dow = $date->dayOfWeek; // 0=Sun, 6=Sat

            if (isset($holidays[$d])) {
                $dayType = 'public_holiday';
                $availableHours = 0;
            } elseif ($dow === Carbon::SATURDAY || $dow === Carbon::SUNDAY) {
                $dayType = 'off_day';
                $availableHours = 0;
            } elseif ($dow === Carbon::FRIDAY) {
                $dayType = 'working';
                $availableHours = 7;
            } else {
                $dayType = 'working';
                $availableHours = 8;
            }

            $days[$d] = [
                'day' => $d,
                'date' => $date->format('Y-m-d'),
                'day_of_week' => strtoupper(substr($date->format('D'), 0, 3)),
                'day_type' => $dayType,
                'available_hours' => $availableHours,
                'holiday_name' => $holidays[$d] ?? null,
            ];
        }

        return $days;
    }

    /**
     * Round late minutes to 30-min ceiling blocks.
     * e.g., 10 min → 0.5 hrs, 35 min → 1.0 hrs
     */
    public function roundLateHours(float $minutes): float
    {
        if ($minutes <= 0) return 0;
        return ceil($minutes / 30) * 0.5;
    }

    /**
     * Floor OT to whole hours for timesheet purposes.
     * e.g., 1hr 45min → 1.0 hrs
     */
    public function floorOtHours(float $hours): float
    {
        if ($hours <= 0) return 0;
        return floor($hours);
    }
}
