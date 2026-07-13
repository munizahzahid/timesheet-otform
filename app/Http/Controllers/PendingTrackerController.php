<?php

namespace App\Http\Controllers;

use App\Models\OtForm;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PendingTrackerController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'hr'])) {
            abort(403, 'Only admin and HR can access the pending tracker.');
        }

        $tab = $request->input('tab', 'timesheets');
        $statusFilter = $request->input('status');
        $staffFilter = $request->input('staff');

        if ($tab === 'ot-forms') {
            $pendingStatuses = ['pending_manager', 'pending_hr', 'pending_gm'];
            $query = OtForm::with([
                'user',
                'user.otApprover',
                'user.otFinalApprover',
                'user.otExecApprover',
                'user.otExecFinalApprover',
                'user.otNonExecApprover',
                'user.otNonExecFinalApprover',
            ])->whereIn('status', $pendingStatuses);

            if ($statusFilter && in_array($statusFilter, $pendingStatuses, true)) {
                $query->where('status', $statusFilter);
            }

            if ($staffFilter) {
                $query->whereHas('user', function ($q) use ($staffFilter) {
                    $q->where('name', 'like', "%{$staffFilter}%")
                      ->orWhere('staff_no', 'like', "%{$staffFilter}%");
                });
            }

            $items = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();
        } else {
            $pendingStatuses = ['pending_hod', 'pending_l1'];
            $query = Timesheet::with([
                'user',
                'user.timesheetHodApprover',
                'user.timesheetApprover',
            ])->whereIn('status', $pendingStatuses);

            if ($statusFilter && in_array($statusFilter, $pendingStatuses, true)) {
                $query->where('status', $statusFilter);
            }

            if ($staffFilter) {
                $query->whereHas('user', function ($q) use ($staffFilter) {
                    $q->where('name', 'like', "%{$staffFilter}%")
                      ->orWhere('staff_no', 'like', "%{$staffFilter}%");
                });
            }

            $items = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();
        }

        $statuses = $tab === 'ot-forms'
            ? [
                'pending_manager' => 'Pending Manager/Asst Manager Approval',
                'pending_hr' => 'Pending HR Review',
                'pending_gm' => 'Pending GM/CEO Approval',
              ]
            : [
                'pending_hod' => 'Pending HOD Approval',
                'pending_l1' => 'Pending L1 Approval',
              ];

        return view('approvals.pending-tracker.index', compact(
            'items',
            'tab',
            'statusFilter',
            'staffFilter',
            'statuses'
        ));
    }
}
