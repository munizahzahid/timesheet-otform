<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\OtFormEntry;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        $fixed = 0;

        OtFormEntry::where('actual_total_hours', '>', 0)
            ->chunkById(100, function ($entries) use (&$fixed) {
                foreach ($entries as $entry) {
                    $actualTotal = (float) $entry->actual_total_hours;
                    $dow = $entry->entry_date ? $entry->entry_date->dayOfWeek : null;
                    $isWeekend = in_array($dow, [0, 6]);
                    $isPH = $entry->is_public_holiday;

                    $needsUpdate = false;
                    $update = [];

                    if ($isPH) {
                        // Public holiday: all hours go to ot_ph_hours
                        if ((float) $entry->ot_ph_hours !== $actualTotal ||
                            (float) $entry->ot_normal_day_hours !== 0.0 ||
                            (float) $entry->ot_rest_day_hours !== 0.0 ||
                            (float) $entry->ot_rest_day_excess_hours !== 0.0 ||
                            (int) $entry->ot_rest_day_count !== 0) {
                            $update['ot_ph_hours'] = $actualTotal;
                            $update['ot_normal_day_hours'] = 0;
                            $update['ot_rest_day_hours'] = 0;
                            $update['ot_rest_day_excess_hours'] = 0;
                            $update['ot_rest_day_count'] = 0;
                            $update['ot_type'] = 'public_holiday';
                            $needsUpdate = true;
                        }
                    } elseif ($isWeekend) {
                        // Rest day: split into ot_rest_day_hours (max 8.0) + ot_rest_day_excess_hours
                        $expectedOt2 = min($actualTotal, 8.0);
                        $expectedOt3 = max(0, $actualTotal - 8.0);
                        $expectedOt5 = 1;

                        if ((float) $entry->ot_rest_day_hours !== $expectedOt2 ||
                            (float) $entry->ot_rest_day_excess_hours !== $expectedOt3 ||
                            (int) $entry->ot_rest_day_count !== $expectedOt5 ||
                            (float) $entry->ot_normal_day_hours !== 0.0 ||
                            (float) $entry->ot_ph_hours !== 0.0) {
                            $update['ot_rest_day_hours'] = $expectedOt2;
                            $update['ot_rest_day_excess_hours'] = $expectedOt3;
                            $update['ot_rest_day_count'] = $expectedOt5;
                            $update['ot_normal_day_hours'] = 0;
                            $update['ot_ph_hours'] = 0;
                            $update['ot_type'] = 'rest_day';
                            $needsUpdate = true;
                        }
                    } else {
                        // Normal day: all hours go to ot_normal_day_hours
                        if ((float) $entry->ot_normal_day_hours !== $actualTotal ||
                            (float) $entry->ot_rest_day_hours !== 0.0 ||
                            (float) $entry->ot_rest_day_excess_hours !== 0.0 ||
                            (float) $entry->ot_ph_hours !== 0.0 ||
                            (int) $entry->ot_rest_day_count !== 0) {
                            $update['ot_normal_day_hours'] = $actualTotal;
                            $update['ot_rest_day_hours'] = 0;
                            $update['ot_rest_day_excess_hours'] = 0;
                            $update['ot_ph_hours'] = 0;
                            $update['ot_rest_day_count'] = 0;
                            $update['ot_type'] = 'normal_day';
                            $needsUpdate = true;
                        }
                    }

                    if ($needsUpdate) {
                        $entry->update($update);
                        $fixed++;
                    }
                }
            });

        // Also recalculate total_ot_hours on all OtForms
        DB::statement("UPDATE ot_forms SET total_ot_hours = (SELECT COALESCE(SUM(actual_total_hours), 0) FROM ot_form_entries WHERE ot_form_entries.ot_form_id = ot_forms.id)");
    }

    public function down(): void
    {
        // No rollback - this is a data fix
    }
};
