<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Fix executive OT forms where rest day hours were incorrectly split.
     * Executive forms should have ALL hours in ot_rest_day_hours (no split).
     */
    public function up(): void
    {
        $entries = DB::table('ot_form_entries')
            ->join('ot_forms', 'ot_form_entries.ot_form_id', '=', 'ot_forms.id')
            ->where('ot_forms.form_type', 'executive')
            ->where('ot_form_entries.actual_total_hours', '>', 0)
            ->where(function ($q) {
                $q->where('ot_form_entries.ot_rest_day_excess_hours', '>', 0)
                  ->orWhere(DB::raw('ot_form_entries.ot_rest_day_hours'), '<', DB::raw('ot_form_entries.actual_total_hours'));
            })
            ->whereRaw("DAYOFWEEK(ot_form_entries.entry_date) IN (1, 7)") // Sunday=1, Saturday=7
            ->where('ot_form_entries.is_public_holiday', false)
            ->select('ot_form_entries.id', 'ot_form_entries.actual_total_hours', 'ot_form_entries.ot_rest_day_hours', 'ot_form_entries.ot_rest_day_excess_hours')
            ->get();

        $fixed = 0;
        foreach ($entries as $entry) {
            $actualTotal = (float) $entry->actual_total_hours;
            $currentOt2 = (float) $entry->ot_rest_day_hours;
            $currentOt3 = (float) $entry->ot_rest_day_excess_hours;

            // Only fix if there's a split (ot3 > 0 or ot2 < actualTotal)
            if ($currentOt3 > 0 || $currentOt2 < $actualTotal) {
                DB::table('ot_form_entries')
                    ->where('id', $entry->id)
                    ->update([
                        'ot_rest_day_hours' => $actualTotal,
                        'ot_rest_day_excess_hours' => 0,
                        'updated_at' => now(),
                    ]);
                $fixed++;
            }
        }
    }

    public function down(): void
    {
        // No rollback
    }
};
