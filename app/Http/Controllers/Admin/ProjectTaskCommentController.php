<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskComment;
use Illuminate\Http\Request;

class ProjectTaskCommentController extends Controller
{
    public function store(Request $request, Project $project, ProjectTask $task)
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        ProjectTaskComment::create([
            'project_task_id' => $task->id,
            'user_id' => auth()->id(),
            'comment' => $validated['comment'],
        ]);

        $url = request()->getQueryString()
            ? route('admin.project.projects.tasks.index', $project) . '?' . request()->getQueryString()
            : route('admin.project.projects.tasks.index', $project);

        return redirect($url)->with('success', 'Comment added.');
    }

    public function destroy(Project $project, ProjectTask $task, ProjectTaskComment $comment)
    {
        if ($comment->user_id !== auth()->id()) {
            abort(403);
        }

        $comment->delete();

        $url = request()->getQueryString()
            ? route('admin.project.projects.tasks.index', $project) . '?' . request()->getQueryString()
            : route('admin.project.projects.tasks.index', $project);

        return redirect($url)->with('success', 'Comment deleted.');
    }
}
