<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectTaskAttachmentController extends Controller
{
    public function show(Project $project, ProjectTask $task, ProjectTaskAttachment $attachment)
    {
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            abort(404);
        }

        $mimeType = $attachment->mime_type ?? Storage::disk('public')->mimeType($attachment->file_path);
        $isPdf = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION)) === 'pdf' || $mimeType === 'application/pdf';

        return response()->file(Storage::disk('public')->path($attachment->file_path), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $isPdf ? 'inline' : 'attachment; filename="' . $attachment->file_name . '"',
        ]);
    }

    public function store(Request $request, Project $project, ProjectTask $task)
    {
        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:10240'],
        ]);

        $file = $validated['attachment'];
        $path = $file->store('task-attachments/' . $task->id, 'public');

        ProjectTaskAttachment::create([
            'project_task_id' => $task->id,
            'user_id' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $url = request()->getQueryString()
            ? route('admin.project.projects.tasks.index', $project) . '?' . request()->getQueryString()
            : route('admin.project.projects.tasks.index', $project);

        return redirect($url)->with('success', 'Attachment uploaded.');
    }

    public function destroy(Project $project, ProjectTask $task, ProjectTaskAttachment $attachment)
    {
        if ($attachment->user_id !== auth()->id()) {
            abort(403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        $url = request()->getQueryString()
            ? route('admin.project.projects.tasks.index', $project) . '?' . request()->getQueryString()
            : route('admin.project.projects.tasks.index', $project);

        return redirect($url)->with('success', 'Attachment deleted.');
    }
}
