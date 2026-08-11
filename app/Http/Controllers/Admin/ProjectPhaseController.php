<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ProjectTask;
use App\Services\GanttChangeLogger;
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

        $phase = ProjectPhase::create($validated);

        GanttChangeLogger::log(
            $project,
            auth()->user(),
            'phase_create',
            null,
            $phase,
            null,
            null,
            null,
            "Phase '{$phase->phase_name}' created"
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'phase' => $phase]);
        }

        $redirect = $request->input('redirect');
        if ($redirect) {
            return redirect($redirect)->with('success', 'Task created successfully.');
        }

        return redirect()->route('admin.project.projects.phases.index', $project)
            ->with('success', 'Task created successfully.');
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
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'phase_name' => 'required|string|max:255',
            'phase_order' => 'required|integer|min:1',
            'start_date_plan' => 'nullable|date',
            'end_date_plan' => 'nullable|date',
            'start_date_actual' => 'nullable|date',
            'end_date_actual' => 'nullable|date',
            'start_date_revise' => 'nullable|date',
            'end_date_revise' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()->toArray()], 422);
            }
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $validated = $validator->validated();

        $oldValues = $phase->getOriginal();
        $phase->update($validated);

        $fieldsToLog = [
            'phase_name' => 'Phase name',
            'phase_order' => 'Phase order',
            'start_date_plan' => 'Plan start',
            'end_date_plan' => 'Plan end',
            'start_date_revise' => 'Revise start',
            'end_date_revise' => 'Revise end',
            'start_date_actual' => 'Actual start',
            'end_date_actual' => 'Actual end',
        ];
        foreach ($fieldsToLog as $field => $label) {
            if (array_key_exists($field, $validated) && $oldValues[$field] != $validated[$field]) {
                GanttChangeLogger::log(
                    $project,
                    auth()->user(),
                    'phase_update',
                    null,
                    $phase,
                    $label,
                    $oldValues[$field],
                    $validated[$field]
                );
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'phase' => $phase]);
        }

        $redirect = $request->input('redirect');
        if ($redirect) {
            return redirect($redirect)->with('success', 'Task updated successfully.');
        }

        return redirect()->route('admin.project.projects.phases.index', $project)
            ->with('success', 'Task updated successfully.');
    }

    /**
     * Delete a phase and its tasks
     */
    public function destroy(Request $request, Project $project, ProjectPhase $phase)
    {
        $phaseName = $phase->phase_name;
        foreach ($phase->tasks as $task) {
            ProjectTask::where('predecessor_task_id', $task->id)->update(['predecessor_task_id' => null]);
            $task->delete();
        }

        $phase->delete();

        GanttChangeLogger::log(
            $project,
            auth()->user(),
            'phase_delete',
            null,
            null,
            null,
            null,
            null,
            "Phase '{$phaseName}' and its tasks deleted"
        );

        (new \App\Services\ProjectProgressCalculator())->recalculateProjectProgress($project);

        $query = ['tab' => $request->input('tab', 'schedule')];
        if ($request->input('view')) {
            $query['view'] = $request->input('view');
        }
        return redirect()->to(route('admin.project.projects.show', $project) . '?' . http_build_query($query))
            ->with('success', 'Task deleted successfully.');
    }
}
