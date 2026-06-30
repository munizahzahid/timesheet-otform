<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Services\ProjectProgressCalculator;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->unsignedInteger('weight')->default(0)->after('progress_revise');
        });

        // Distribute weight equally among tasks in each phase/standalone group
        $this->distributeWeights();

        // Recalculate progress for all projects
        $calculator = new ProjectProgressCalculator();
        foreach (Project::all() as $project) {
            $calculator->recalculateAll($project);
        }
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }

    private function distributeWeights(): void
    {
        $tasks = DB::table('project_tasks')->orderBy('id')->get();

        $grouped = $tasks->groupBy(function ($task) {
            return $task->phase_id ?? 'standalone';
        });

        foreach ($grouped as $group => $items) {
            $count = $items->count();
            if ($count === 0) {
                continue;
            }

            $weight = (int) floor(100 / $count);
            $remainder = 100 - ($weight * $count);

            foreach ($items as $index => $item) {
                $assignedWeight = $weight + ($index < $remainder ? 1 : 0);
                DB::table('project_tasks')->where('id', $item->id)->update(['weight' => $assignedWeight]);
            }
        }
    }
};
