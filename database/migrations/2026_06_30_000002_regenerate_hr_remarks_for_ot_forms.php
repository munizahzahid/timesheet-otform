<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\OtForm;
use App\Models\OtFormEntry;
use App\Models\ProjectCode;

return new class extends Migration
{
    public function up(): void
    {
        $forms = OtForm::whereHas('entries', function ($q) {
            $q->whereNotNull('hr_corrections');
        })->with(['entries', 'hrEditor'])->get();

        foreach ($forms as $otForm) {
            $summaryLines = [];

            foreach ($otForm->entries as $entry) {
                $corrections = $entry->hr_corrections;
                if (empty($corrections)) continue;

                $dateLabel = $entry->entry_date->format('j/n');
                $changedFields = [];
                $fieldLabels = [
                    'planned_start_time' => 'Plan Start',
                    'planned_end_time' => 'Plan End',
                    'actual_start_time' => 'Actual Start',
                    'actual_end_time' => 'Actual End',
                    'project_code_id' => 'Project Code',
                    'project_category' => 'Project Category',
                    'manual_project_code_name' => 'Project Name',
                    'project_name' => 'Project Name',
                ];

                $normalizeTime = function ($value) {
                    return $value ? substr($value, 0, 5) : null;
                };

                $displayValue = function ($field, $value) use ($normalizeTime) {
                    if (in_array($field, ['planned_start_time', 'planned_end_time', 'actual_start_time', 'actual_end_time'])) {
                        return $normalizeTime($value) ?? '-';
                    }
                    if ($field === 'project_code_id') {
                        return $value ? ProjectCode::find($value)?->code : '-';
                    }
                    return $value ?? '-';
                };

                foreach ($fieldLabels as $field => $label) {
                    $oldValue = $corrections[$field] ?? null;
                    $newValue = $entry->$field ?? null;

                    if (in_array($field, ['planned_start_time', 'planned_end_time', 'actual_start_time', 'actual_end_time'])) {
                        if ($normalizeTime($oldValue) === $normalizeTime($newValue)) continue;
                    } elseif ($oldValue == $newValue) {
                        continue;
                    }

                    $changedFields[] = "- {$label}: {$displayValue($field, $oldValue)} → {$displayValue($field, $newValue)}";
                }

                if (!empty($changedFields)) {
                    $summaryLines[] = $dateLabel . ":\n" . implode("\n", $changedFields);
                }
            }

            if (!empty($summaryLines)) {
                $otForm->update([
                    'hr_remarks' => implode("\n\n", $summaryLines),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible
    }
};
