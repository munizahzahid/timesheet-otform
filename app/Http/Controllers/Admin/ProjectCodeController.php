<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('project_code', 'like', "%{$search}%")
                  ->orWhere('project_name', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%")
                  ->orWhere('po_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('year')) {
            $query->where('year', $request->input('year'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $projectCodes = $query->orderByDesc('year')->orderBy('project_code')->paginate(25)->withQueryString();

        $years = Project::selectRaw('DISTINCT year')
            ->whereNotNull('year')
            ->orderByDesc('year')
            ->pluck('year');

        return view('admin.project-codes.index', compact('projectCodes', 'years'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:pm_projects,project_code',
            'name' => 'required|string|max:255',
            'client' => 'nullable|string|max:255',
            'year' => 'nullable|integer',
        ]);

        $pmData = [
            'project_code' => $validated['code'],
            'project_name' => $validated['name'],
            'client' => $validated['client'] ?? null,
            'year' => $validated['year'] ?? null,
            'is_active' => true,
            'status' => 'active',
            'overall_plan_progress' => 0,
            'overall_actual_progress' => 0,
        ];

        Project::create($pmData);

        return redirect()->route('admin.project-codes.index')
            ->with('success', 'Project code created successfully.');
    }
}
