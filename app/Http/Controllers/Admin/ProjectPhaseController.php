<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ProjectTask;
use Illuminate\Http\Request;

class ProjectPhaseController extends Controller
{
    /**
     * List phases for a project
     */
    public function index(Project $project)
    {
        $phases = $project->phases()->orderBy('phase_order')->get();
        return view('admin.project.phases.index', compact('project', 'phases'));
    }

    /**
     * Show create phase form
     */
    public function create(Project $project)
    {
        return view('admin.project.phases.create', compact('project'));
    }

    /**
     * Store a new phase
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'phase_name' => 'required|string|max:255',
            'phase_order' => 'required|integer|min:1',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
        ]);

        $validated['project_id'] = $project->id;
        $validated['progress_plan'] = 0;
        $validated['progress_actual'] = 0;

        ProjectPhase::create($validated);

        return redirect()->route('admin.project.projects.phases.index', $project)
            ->with('success', 'Phase created successfully.');
    }

    /**
     * Show phase details
     */
    public function show(Project $project, ProjectPhase $phase)
    {
        $phase->load(['tasks', 'tasks.assignedTo']);
        return view('admin.project.phases.show', compact('project', 'phase'));
    }

    /**
     * Show edit phase form
     */
    public function edit(Project $project, ProjectPhase $phase)
    {
        return view('admin.project.phases.edit', compact('project', 'phase'));
    }

    /**
     * Update phase
     */
    public function update(Request $request, Project $project, ProjectPhase $phase)
    {
        $validated = $request->validate([
            'phase_name' => 'required|string|max:255',
            'phase_order' => 'required|integer|min:1',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
        ]);

        $phase->update($validated);

        return redirect()->route('admin.project.projects.phases.index', $project)
            ->with('success', 'Phase updated successfully.');
    }

    /**
     * Delete a phase and its tasks
     */
    public function destroy(Project $project, ProjectPhase $phase)
    {
        foreach ($phase->tasks as $task) {
            ProjectTask::where('predecessor_task_id', $task->id)->update(['predecessor_task_id' => null]);
            $task->delete();
        }

        $phase->delete();

        (new \App\Services\ProjectProgressCalculator())->recalculateProjectProgress($project);

        return redirect()->route('admin.project.projects.show', $project)
            ->with('success', 'Phase deleted successfully.');
    }
}
