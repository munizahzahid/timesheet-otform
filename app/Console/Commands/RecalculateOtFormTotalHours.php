<?php

namespace App\Console\Commands;

use App\Models\OtForm;
use Illuminate\Console\Command;

class RecalculateOtFormTotalHours extends Command
{
    protected $signature = 'ot:recalculate-total-hours
                            {--ot-form-id= : Recalculate a single OT form by ID}
                            {--status=approved : Filter forms by status}
                            {--dry-run : Show changes without applying them}';

    protected $description = 'Recalculate ot_forms.total_ot_hours from current ot_form_entries without touching entries or HR corrections.';

    public function handle(): int
    {
        $query = OtForm::query();

        if ($this->option('ot-form-id')) {
            $query->where('id', $this->option('ot-form-id'));
        } elseif ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $forms = $query->with('entries')->get();

        if ($forms->isEmpty()) {
            $this->warn('No OT forms found matching the criteria.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $updated = 0;

        foreach ($forms as $otForm) {
            $calculated = 0;

            foreach ($otForm->entries as $entry) {
                $calculated += floor((float) $entry->actual_total_hours * 4) / 4;
            }

            $calculated = floor($calculated * 4) / 4;

            $stored = (float) $otForm->total_ot_hours;

            if (abs($calculated - $stored) < 0.001) {
                $this->info("OT Form {$otForm->id}: total_ot_hours already correct ({$stored}).");
                continue;
            }

            $this->line("OT Form {$otForm->id}: {$stored} -> {$calculated}");

            if (! $dryRun) {
                $otForm->update(['total_ot_hours' => $calculated]);
                $updated++;
            }
        }

        if ($dryRun) {
            $this->warn('Dry-run mode: no changes applied.');
        } else {
            $this->info("Updated {$updated} OT form(s).");
        }

        return self::SUCCESS;
    }
}
