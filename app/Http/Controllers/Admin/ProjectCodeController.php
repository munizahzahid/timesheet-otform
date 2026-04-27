<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectCode;
use Illuminate\Http\Request;

class ProjectCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = ProjectCode::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
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

        $projectCodes = $query->orderByDesc('year')->orderBy('code')->paginate(25)->withQueryString();

        $years = ProjectCode::selectRaw('DISTINCT year')
            ->whereNotNull('year')
            ->orderByDesc('year')
            ->pluck('year');

        return view('admin.project-codes.index', compact('projectCodes', 'years'));
    }
}
