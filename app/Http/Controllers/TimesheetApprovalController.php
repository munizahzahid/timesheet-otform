<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Models\TimesheetApprovalLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimesheetApprovalController extends Controller
{
    /**
     * List timesheets pending the current user's approval.
     */
    public function index()
    {
        $user = Auth::user();
        $pendingStatuses = $this->getPendingStatusesForUser($user);

        $timesheets = Timesheet::with('user', 'user.department')
            ->whereIn('status', $pendingStatuses)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('approvals.timesheets.index', compact('timesheets'));
    }

    /**
     * Show a single timesheet for review.
     */
    public function show(Timesheet $timesheet)
    {
        $timesheet->load([
            'user.department',
            'dayMetadata',
            'adminHours',
            'projectRows.projectCode',
            'projectRows.hours',
        ]);

        // Get days data for the month using TimesheetCalculationService
        $calcService = new \App\Services\TimesheetCalculationService();
        $days = $calcService->generateDayMetadata($timesheet->month, $timesheet->year);
        $daysInMonth = count($days);

        // Merge DB metadata into generated days
        foreach ($timesheet->dayMetadata as $meta) {
            $d = (int) $meta->entry_date->day;
            if (isset($days[$d])) {
                $days[$d]['day_type'] = $meta->day_type;
                $days[$d]['available_hours'] = (float) $meta->available_hours;
                $days[$d]['time_in'] = $meta->time_in;
                $days[$d]['time_out'] = $meta->time_out;
                $days[$d]['late_hours'] = (float) $meta->late_hours;
                $days[$d]['ot_eligible_hours'] = (float) $meta->ot_eligible_hours;
                $days[$d]['attendance_hours'] = (float) $meta->attendance_hours;
            }
        }

        // Admin types - use the same as TimesheetCalculationService
        $adminTypes = \App\Services\TimesheetCalculationService::ADMIN_TYPES;

        // Build admin hours data
        $adminHours = [];
        foreach ($adminTypes as $type => $label) {
            $adminHours[$type] = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $adminHours[$type][$d] = 0;
            }
        }
        foreach ($timesheet->adminHours as $ah) {
            $day = (int) $ah->entry_date->day;
            if (isset($adminHours[$ah->admin_type])) {
                $adminHours[$ah->admin_type][$day] = (float) $ah->hours;
            }
        }

        // Build project rows data with sub-rows
        $projectRowsData = [];
        foreach ($timesheet->projectRows->sortBy('row_order') as $row) {
            $hoursData = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $hoursData[$d] = [
                    'normal_nc' => 0, 'normal_cobq' => 0,
                    'ot_nc' => 0, 'ot_cobq' => 0,
                ];
            }
            foreach ($row->hours as $h) {
                $day = (int) $h->entry_date->day;
                if (isset($hoursData[$day])) {
                    $hoursData[$day] = [
                        'normal_nc' => (float) $h->normal_nc_hours,
                        'normal_cobq' => (float) $h->normal_cobq_hours,
                        'ot_nc' => (float) $h->ot_nc_hours,
                        'ot_cobq' => (float) $h->ot_cobq_hours,
                    ];
                }
            }
            $projectRowsData[] = [
                'id' => $row->id,
                'project_code_id' => $row->project_code_id,
                'project_code' => $row->projectCode ? $row->projectCode->code : '',
                'project_name' => $row->projectCode ? $row->projectCode->name : '',
                'hours' => $hoursData,
            ];
        }

        // Build flat project rows for display (each project has 4 sub-rows)
        $flatProjectRows = [];
        $subRowTypes = [
            ['field' => 'normal_nc', 'label' => 'Normal NC'],
            ['field' => 'normal_cobq', 'label' => 'Normal COBQ'],
            ['field' => 'ot_nc', 'label' => 'OT NC'],
            ['field' => 'ot_cobq', 'label' => 'OT COBQ'],
        ];
        foreach ($projectRowsData as $pIdx => $project) {
            foreach ($subRowTypes as $sIdx => $subRow) {
                $flatProjectRows[] = [
                    'pIdx' => $pIdx,
                    'sIdx' => $sIdx,
                    'key' => "{$pIdx}-{$sIdx}",
                    'field' => $subRow['field'],
                    'label' => $subRow['label'],
                    'project_code' => $project['project_code'],
                    'project_name' => $project['project_name'],
                ];
            }
        }

        // Build approval stamps
        $approvalLogs = $timesheet->approvalLogs ? $timesheet->approvalLogs->sortBy('id') : collect();
        $approvalStamps = $this->buildApprovalStamps($timesheet, $approvalLogs);

        return view('approvals.timesheets.show', compact(
            'timesheet', 'approvalStamps', 'daysInMonth', 'days', 'adminTypes', 'adminHours', 'projectRowsData', 'flatProjectRows'
        ));
    }

    /**
     * Submit timesheet for approval with staff digital signature.
     */
    public function submit(Request $request, Timesheet $timesheet)
    {
        // Authorization: only staff can submit their own timesheet
        if ($timesheet->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Validation: can only submit draft timesheets
        if ($timesheet->status !== 'draft' && !str_starts_with($timesheet->status, 'rejected')) {
            return response()->json(['error' => 'Timesheet can only be submitted from draft or rejected status'], 400);
        }

        // Validation: day metadata must exist (Excel/PDF must be uploaded)
        if ($timesheet->dayMetadata()->count() === 0) {
            return response()->json(['error' => 'Please upload attendance file before submitting'], 400);
        }

        // Save staff digital signature
        $request->validate([
            'signature' => 'required|string', // text signature
        ]);

        $timesheet->update([
            'status' => 'pending_hod',
            'submitted_at' => now(),
            'staff_signature' => $request->signature,
            'staff_signed_at' => now(),
            'rejection_remarks' => null,
        ]);

        // Log submission
        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 0, // Staff submission
            'action' => 'submitted',
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * HOD/Exec/SPV optional check - can approve or reject.
     * If rejected, cannot proceed to next approval.
     * If not checked, Asst Mgr/Mgr can still approve.
     * If the same person is also Asst Mgr/Mngr, they sign both boxes.
     */
    public function approveHOD(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_hod') {
            return response()->json(['error' => 'Timesheet is not pending HOD check'], 400);
        }

        $request->validate([
            'signature' => 'required|string',
        ]);

        $user = Auth::user();

        // Check if this user is also the Asst Mgr/Mngr for the timesheet
        $isAlsoAsstMgr = $user->canApproveTimesheetL1();

        $timesheet->update([
            'hod_signature' => $request->signature,
            'hod_signed_at' => now(),
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 0.5, // HOD check
            'action' => 'approved',
        ]);

        // If the same person is also Asst Mgr/Mngr, sign both boxes and approve
        if ($isAlsoAsstMgr) {
            $timesheet->update([
                'status' => 'approved', // Final approval - no CEO/DGM level
                'l1_signature' => $request->signature,
                'l1_signed_at' => now(),
            ]);

            TimesheetApprovalLog::create([
                'timesheet_id' => $timesheet->id,
                'user_id' => Auth::id(),
                'level' => 1, // L1 approval
                'action' => 'approved',
            ]);
        } else {
            $timesheet->update([
                'status' => 'pending_l1',
            ]);
        }

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * HOD/Exec/SPV optional check - reject with remarks.
     * If rejected, cannot proceed to next approval.
     */
    public function rejectHOD(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_hod') {
            return response()->json(['error' => 'Timesheet is not pending HOD check'], 400);
        }

        $request->validate([
            'remarks' => 'required|string',
        ]);

        $timesheet->update([
            'status' => 'rejected_hod',
            'rejection_remarks' => $request->remarks,
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 0.5, // HOD check
            'action' => 'rejected',
            'remarks' => $request->remarks,
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * Asst Mgr/Mgr can skip HOD check and approve directly (final approval).
     */
    public function skipHOD(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_hod') {
            return response()->json(['error' => 'Timesheet is not pending HOD check'], 400);
        }

        $request->validate([
            'signature' => 'required|string',
        ]);

        $timesheet->update([
            'status' => 'approved', // Final approval - no CEO/DGM level
            'l1_signature' => $request->signature,
            'l1_signed_at' => now(),
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 1,
            'action' => 'approved',
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * L1 (Asst Mgr) approve with digital signature.
     */
    public function approveL1(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_l1') {
            return response()->json(['error' => 'Timesheet is not pending L1 approval'], 400);
        }

        // Authorization: use designated approver if set, otherwise use role-based routing
        $designatedApproverId = $timesheet->user->timesheet_approver_id;
        $currentUser = Auth::user();

        if ($designatedApproverId) {
            // Use designated approver
            if (Auth::id() !== $designatedApproverId) {
                return response()->json(['error' => 'You are not authorized to approve this timesheet'], 403);
            }
        } else {
            // Use role-based routing
            if (!$currentUser->canApproveTimesheetL1()) {
                return response()->json(['error' => 'You are not authorized to approve this timesheet'], 403);
            }
        }

        $request->validate([
            'signature' => 'required|string',
        ]);

        $timesheet->update([
            'status' => 'approved', // Final approval - no CEO/DGM level
            'l1_signature' => $request->signature,
            'l1_signed_at' => now(),
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 1,
            'action' => 'approved',
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * L1 reject with remarks.
     */
    public function rejectL1(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_l1') {
            return response()->json(['error' => 'Timesheet is not pending L1 approval'], 400);
        }

        $request->validate([
            'remarks' => 'required|string',
        ]);

        $timesheet->update([
            'status' => 'rejected_l1',
            'rejection_remarks' => $request->remarks,
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 1,
            'action' => 'rejected',
            'remarks' => $request->remarks,
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * L2 (Mgr/HOD) approve with digital signature.
     */
    public function approveL2(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_l2') {
            return response()->json(['error' => 'Timesheet is not pending L2 approval'], 400);
        }

        $request->validate([
            'signature' => 'required|string',
        ]);

        $timesheet->update([
            'status' => 'pending_l3',
            'l2_signature' => $request->signature,
            'l2_signed_at' => now(),
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 2,
            'action' => 'approved',
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * L2 reject with remarks.
     */
    public function rejectL2(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_l2') {
            return response()->json(['error' => 'Timesheet is not pending L2 approval'], 400);
        }

        $request->validate([
            'remarks' => 'required|string',
        ]);

        $timesheet->update([
            'status' => 'rejected_l2',
            'rejection_remarks' => $request->remarks,
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 2,
            'action' => 'rejected',
            'remarks' => $request->remarks,
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * L3 (DGM/CEO) approve with digital signature.
     */
    public function approveL3(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_l3') {
            return response()->json(['error' => 'Timesheet is not pending L3 approval'], 400);
        }

        $request->validate([
            'signature' => 'required|string',
        ]);

        $timesheet->update([
            'status' => 'approved',
            'l3_signature' => $request->signature,
            'l3_signed_at' => now(),
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 3,
            'action' => 'approved',
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * L3 reject with remarks.
     */
    public function rejectL3(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_l3') {
            return response()->json(['error' => 'Timesheet is not pending L3 approval'], 400);
        }

        $request->validate([
            'remarks' => 'required|string',
        ]);

        $timesheet->update([
            'status' => 'rejected_l3',
            'rejection_remarks' => $request->remarks,
        ]);

        TimesheetApprovalLog::create([
            'timesheet_id' => $timesheet->id,
            'user_id' => Auth::id(),
            'level' => 3,
            'action' => 'rejected',
            'remarks' => $request->remarks,
        ]);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

/**
 * Get the statuses this user can act on based on role.
 */
private function getPendingStatusesForUser($user): array
{
    if ($user->role === 'admin') {
        return ['pending_hod', 'pending_l1'];
    }

    $statuses = [];

    // HOD can approve pending_hod
    if ($user->canApproveTimesheetHOD()) {
        $statuses[] = 'pending_hod';
    }

    // Asst Mgr/Mgr can approve pending_l1 (final approval)
    if ($user->canApproveTimesheetL1()) {
        $statuses[] = 'pending_l1';
    }

    return $statuses;
}

/**
 * Build stamp data array for approval-stamps component.
 */
private function buildApprovalStamps(Timesheet $timesheet, $approvalLogs): array
{
    $stamps = [];

    // Stamp 1: Prepared by (Staff)
    $submitted = $timesheet->submitted_at || !in_array($timesheet->status, ['draft']);
    $stamps[] = [
        'label' => 'Prepared By',
        'code' => 'PRPD',
        'status' => $submitted && $timesheet->status !== 'draft' ? 'approved' : 'empty',
        'date' => $timesheet->submitted_at ? $timesheet->submitted_at->format('m/d') : '',
        'name' => $timesheet->user->name ?? '',
        'role' => 'Staff',
    ];

    // Stamp 2: Checked by (HOD)
    $hodLog = $approvalLogs->where('level', 0.5)->where('action', 'approved')->first();
    $hodReject = $approvalLogs->where('level', 0.5)->where('action', 'rejected')->first();
    $hodStatus = 'empty';
    if ($hodLog) {
        $hodStatus = 'approved';
    } elseif ($hodReject) {
        $hodStatus = 'rejected';
    } elseif (in_array($timesheet->status, ['pending_hod', 'pending_l1'])) {
        $hodStatus = 'pending';
    }

    $hodUser = $hodLog ? $hodLog->user : null;
    $stamps[] = [
        'label' => 'Checked By',
        'code' => 'CHKD',
        'status' => $hodStatus,
        'date' => $timesheet->hod_signed_at ? $timesheet->hod_signed_at->format('m/d') : '',
        'name' => $hodUser ? $hodUser->name : '',
        'role' => 'HOD/Exec/SPV',
    ];

    // Stamp 3: Verified by (Asst Mgr/Mngr)
    $l1Log = $approvalLogs->where('level', 1)->where('action', 'approved')->first();
    $l1Reject = $approvalLogs->where('level', 1)->where('action', 'rejected')->first();
    $l1Status = 'empty';
    if ($l1Log) {
        $l1Status = 'approved';
    } elseif ($l1Reject) {
        $l1Status = 'rejected';
    } elseif (in_array($timesheet->status, ['pending_l1'])) {
        $l1Status = 'pending';
    }

    $l1User = $l1Log ? $l1Log->user : null;
    $stamps[] = [
        'label' => 'Verified By',
        'code' => 'VRFD',
        'status' => $l1Status,
        'date' => $timesheet->l1_signed_at ? $timesheet->l1_signed_at->format('m/d') : '',
        'name' => $l1User ? $l1User->name : '',
        'role' => 'Asst Mgr/Mngr',
    ];

    return $stamps;
}
}
