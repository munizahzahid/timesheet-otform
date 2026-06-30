<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Services\ProjectProgressCalculator;

class RecalculateProjectProgress extends Command
{
    protected $signature = 'project:recalculate-progress';
    protected $description = 'Recalculate all project and phase progress based on task weights';

    public function handle(): void
    {
        $calculator = new ProjectProgressCalculator();
        foreach (Project::all() as $project) {
            $calculator->recalculateAll($project);
            $this->info("Recalculated progress for project: {$project->project_name}");
        }
        $this->info('Done.');
    }
}
