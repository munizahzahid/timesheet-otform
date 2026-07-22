<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectCodeSearchController extends Controller
{
    /**
     * Search project codes by code or name.
     * Phase 3: now returns pm_projects IDs because timesheet_project_rows
     * and ot_form_entries FK columns reference pm_projects.id.
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');

        $builder = Project::where('is_active', true);

        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $q->where('project_code', 'like', "%{$query}%")
                  ->orWhere('project_name', 'like', "%{$query}%");
            });
        }

        $results = $builder->orderBy('project_code')
            ->limit(100)
            ->get(['id', 'project_code', 'project_name'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->project_code,
                'name' => $p->project_name,
            ]);

        return response()->json($results);
    }
}
