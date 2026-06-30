<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProjectTask;

class InspectTaskWeights extends Command
{
    protected $signature = 'project:inspect-task-weights';
    protected $description = 'Inspect task weights for debugging';

    public function handle(): void
    {
        $tasks = ProjectTask::with('phase')->get();
        foreach ($tasks as $task) {
            $this->info("Task #{$task->id}: {$task->task_name}, weight={$task->weight}, phase_id={$task->phase_id}, phase=" . ($task->phase?->phase_name ?? 'Standalone'));
        }
        if ($tasks->isEmpty()) {
            $this->warn('No tasks found.');
        }
    }
}
