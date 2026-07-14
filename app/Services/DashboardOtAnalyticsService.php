<?php

namespace App\Services;

use App\Models\OtForm;
use App\Models\OtFormEntry;
use App\Models\ProjectCode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardOtAnalyticsService
{
    /**
     * Total actual OT hours grouped by project for a specific month/year.
     * Includes both project_code_id and manual_project_code_name entries.
     */
    public function getOtHoursByProject(int $month, int $year): Collection
    {
        return OtFormEntry::query()
            ->join('ot_forms', 'ot_form_entries.ot_form_id', '=', 'ot_forms.id')
            ->where('ot_forms.status', 'approved')
            ->where('ot_forms.month', $month)
            ->where('ot_forms.year', $year)
            ->where('ot_form_entries.actual_total_hours', '>', 0)
            ->select(
                DB::raw("COALESCE(project_codes.code, ot_form_entries.manual_project_code_name, ot_form_entries.project_name, 'Unknown Project') as project_label"),
                DB::raw('SUM(ot_form_entries.actual_total_hours) as total_hours')
            )
            ->leftJoin('project_codes', 'ot_form_entries.project_code_id', '=', 'project_codes.id')
            ->groupBy('project_label')
            ->orderByDesc('total_hours')
            ->get()
            ->map(fn ($row) => (object) [
                'label' => $row->project_label,
                'hours' => (float) $row->total_hours,
            ]);
    }

    /**
     * Total actual OT hours grouped by staff for a specific month/year.
     */
    public function getOtHoursByStaff(int $month, int $year): Collection
    {
        return OtFormEntry::query()
            ->join('ot_forms', 'ot_form_entries.ot_form_id', '=', 'ot_forms.id')
            ->join('users', 'ot_forms.user_id', '=', 'users.id')
            ->where('ot_forms.status', 'approved')
            ->where('ot_forms.month', $month)
            ->where('ot_forms.year', $year)
            ->where('ot_form_entries.actual_total_hours', '>', 0)
            ->select(
                DB::raw("COALESCE(users.name, 'Unknown Staff') as staff_name"),
                DB::raw('SUM(ot_form_entries.actual_total_hours) as total_hours')
            )
            ->groupBy('staff_name')
            ->orderByDesc('total_hours')
            ->get()
            ->map(fn ($row) => (object) [
                'label' => $row->staff_name,
                'hours' => (float) $row->total_hours,
            ]);
    }

    /**
     * Total actual OT hours grouped by month across all time.
     */
    public function getOtHoursByMonth(): Collection
    {
        return OtFormEntry::query()
            ->join('ot_forms', 'ot_form_entries.ot_form_id', '=', 'ot_forms.id')
            ->where('ot_forms.status', 'approved')
            ->where('ot_form_entries.actual_total_hours', '>', 0)
            ->select(
                DB::raw("CONCAT(ot_forms.year, '-', LPAD(ot_forms.month, 2, '0')) as month_label"),
                DB::raw('SUM(ot_form_entries.actual_total_hours) as total_hours')
            )
            ->groupBy('month_label')
            ->orderBy('month_label')
            ->get()
            ->map(fn ($row) => (object) [
                'label' => $row->month_label,
                'hours' => (float) $row->total_hours,
            ]);
    }

    /**
     * List of all distinct month/year combinations with approved OT form data.
     * Used to populate the month filter dropdown.
     */
    public function getAvailableMonths(): Collection
    {
        return OtForm::query()
            ->where('status', 'approved')
            ->select('year', 'month')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($row) => (object) [
                'value' => "{$row->year}-" . str_pad($row->month, 2, '0', STR_PAD_LEFT),
                'label' => date('F Y', mktime(0, 0, 0, $row->month, 1, $row->year)),
                'year' => (int) $row->year,
                'month' => (int) $row->month,
            ]);
    }
}
