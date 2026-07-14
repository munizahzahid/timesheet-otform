<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Fix June 2026 approved OT forms where public holiday hours were misrouted
     * into Normal Day (exec) or OT1 (non-exec) instead of PH columns.
     *
     * Hardcoded dates: 1 June and 17 June 2026 (from actual attendance data).
     */
    public function up(): void
    {
        // June 2026 known public holidays (from actual attendance data)
        $junePHDates = ['2026-06-01', '2026-06-17'];

        $entries = DB::table('ot_form_entries')
            ->join('ot_forms', 'ot_form_entries.ot_form_id', '=', 'ot_forms.id')
            ->where('ot_forms.month', 6)
            ->where('ot_forms.year', 2026)
            ->whereIn(DB::raw("DATE_FORMAT(ot_form_entries.entry_date, '%Y-%m-%d')"), $junePHDates)
            ->where('ot_form_entries.actual_total_hours', '>', 0)
            ->select('ot_form_entries.*')
            ->get();

        $fixed = 0;

        foreach ($entries as $entry) {
            $actualTotal = (float) $entry->actual_total_hours;

            // Only fix if hours are currently NOT in PH column (misrouted)
            $isMisrouted =
                (float) $entry->ot_ph_hours !== $actualTotal ||
                (float) $entry->ot_normal_day_hours > 0 ||
                (float) $entry->ot_rest_day_hours > 0 ||
                (float) $entry->ot_rest_day_excess_hours > 0;

            if (!$isMisrouted) {
                continue;
            }

            DB::table('ot_form_entries')
                ->where('id', $entry->id)
                ->update([
                    'is_public_holiday' => true,
                    'ot_type' => 'public_holiday',
                    'ot_normal_day_hours' => 0,
                    'ot_rest_day_hours' => 0,
                    'ot_rest_day_excess_hours' => 0,
                    'ot_rest_day_count' => 0,
                    'ot_ph_hours' => $actualTotal,
                    'updated_at' => now(),
                ]);

            $fixed++;
        }

        // Recalculate total_ot_hours for all June 2026 forms
        DB::statement("
            UPDATE ot_forms
            SET total_ot_hours = (
                SELECT COALESCE(SUM(actual_total_hours), 0)
                FROM ot_form_entries
                WHERE ot_form_entries.ot_form_id = ot_forms.id
            )
            WHERE ot_forms.month = 6 AND ot_forms.year = 2026
        ");
    }

    public function down(): void
    {
        // No rollback - this is a data correction
    }
};
