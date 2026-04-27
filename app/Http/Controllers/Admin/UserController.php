<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('department', 'supervisor', 'timesheetApprover', 'otExecApprover', 'otExecFinalApprover', 'otNonExecApprover', 'otNonExecFinalApprover');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('staff_no', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->input('department'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'departments'));
    }

    public function edit(User $user)
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $supervisors = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->whereIn('role', ['assistant_manager', 'manager_hod', 'ceo'])
            ->orderBy('name')
            ->get();
        $approvers = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return view('admin.users.edit', compact('user', 'departments', 'supervisors', 'approvers'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:staff,admin,assistant_manager,manager_hod,ceo',
            'reports_to' => 'nullable|exists:users,id',
            'is_active' => 'required|boolean',
            'timesheet_approver_id' => 'nullable|exists:users,id',
            'ot_exec_approver_id' => 'nullable|exists:users,id',
            'ot_exec_final_approver_id' => 'nullable|exists:users,id',
            'ot_non_exec_approver_id' => 'nullable|exists:users,id',
            'ot_non_exec_final_approver_id' => 'nullable|exists:users,id',
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' updated successfully.");
    }
}
