<?php

namespace App\Http\Controllers;

use App\Models\ProjectCode;
use Illuminate\Http\Request;

class ProjectCodeSearchController extends Controller
{
    /**
     * Search project codes by code or name.
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');

        $builder = ProjectCode::where('is_active', true);

        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            });
        }

        $results = $builder->orderBy('code')
            ->limit(100)
            ->get(['id', 'code', 'name']);

        return response()->json($results);
    }
}
