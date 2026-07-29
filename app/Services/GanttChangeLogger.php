<?php

namespace App\Services;

use App\Models\GanttChangeLog;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectPhase;
use App\Models\User;

class GanttChangeLogger
{
    public static function log(
        Project|int $project,
        User|int $user,
        string $actionType,
        ?ProjectTask $task = null,
        ?ProjectPhase $phase = null,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $description = null
    ): GanttChangeLog {
        $projectId = $project instanceof Project ? $project->id : $project;
        $userId = $user instanceof User ? $user->id : $user;

        return GanttChangeLog::create([
            'project_id' => $projectId,
            'user_id' => $userId,
            'task_id' => $task?->id,
            'phase_id' => $phase?->id,
            'action_type' => $actionType,
            'field_name' => $fieldName,
            'old_value' => self::normalizeValue($oldValue),
            'new_value' => self::normalizeValue($newValue),
            'description' => $description ?? self::buildDescription($actionType, $task, $phase, $fieldName, $oldValue, $newValue),
        ]);
    }

    private static function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_object($value) && method_exists($value, 'format')) {
            return $value->format('Y-m-d');
        }
        return json_encode($value) ?: null;
    }

    private static function buildDescription(
        string $actionType,
        ?ProjectTask $task,
        ?ProjectPhase $phase,
        ?string $fieldName,
        mixed $oldValue,
        mixed $newValue
    ): string {
        $subject = $task?->task_name ?? ($phase?->phase_name ?? 'Project');
        $oldStr = self::normalizeValue($oldValue) ?? '—';
        $newStr = self::normalizeValue($newValue) ?? '—';

        return match ($actionType) {
            'bar_drag' => "{$subject}: {$fieldName} changed from {$oldStr} to {$newStr}",
            'progress_update' => "{$subject}: progress changed from {$oldStr}% to {$newStr}%",
            'task_create' => "Task '{$subject}' created",
            'task_update' => "Task '{$subject}' updated",
            'task_delete' => "Task '{$subject}' deleted",
            'phase_create' => "Phase '{$subject}' created",
            'phase_update' => "Phase '{$subject}' updated",
            'phase_delete' => "Phase '{$subject}' deleted",
            default => "{$subject}: {$actionType}",
        };
    }
}
