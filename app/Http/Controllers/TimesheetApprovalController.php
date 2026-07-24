<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Timesheet;
use App\Models\TimesheetApprovalLog;
use App\Models\User;
use App\Services\TimesheetEmailNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimesheetApprovalController extends Controller
{
    protected TimesheetEmailNotificationService $emailService;

    public function __construct(TimesheetEmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * List timesheets pending the current user's approval.
     */
    public function index()
    {
        $user = Auth::user();
        $pendingStatuses = $this->getPendingStatusesForUser($user);

        $query = Timesheet::with('user', 'user.department')
            ->whereIn('status', $pendingStatuses);

        // Filter by designated approver if not admin
        if ($user->role !== 'admin') {
            $query->whereHas('user', function ($q) use ($user, $pendingStatuses) {
                $q->where(function ($sub) use ($user, $pendingStatuses) {
                    if (in_array('pending_hod', $pendingStatuses)) {
                        $sub->orWhere('timesheet_hod_approver_id', $user->id);
                    }
                    if (in_array('pending_l1', $pendingStatuses)) {
                        $sub->orWhere('timesheet_approver_id', $user->id);
                    }
                });
            });
        }

        $timesheets = $query->orderByDesc('updated_at')->paginate(20);

        return view('approvals.timesheets.index', compact('timesheets'));
    }

    /**
     * List approved timesheets for the current user.
     */
    public function approved()
    {
        $user = Auth::user();

        $query = Timesheet::with('user')->where('status', 'approved');

        // Non-admin users only see approved timesheets where they are a designated approver
        if ($user->role !== 'admin') {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('timesheet_hod_approver_id', $user->id)
                   ->orWhere('timesheet_approver_id', $user->id);
            });
        }

        $timesheets = $query->orderByDesc('updated_at')->paginate(20);

        // Load final approval dates for the paginated timesheets
        $timesheetIds = $timesheets->pluck('id');
        $approvalLogs = TimesheetApprovalLog::whereIn('timesheet_id', $timesheetIds)
            ->where('action', 'approved')
            ->orderBy('id')
            ->get()
            ->groupBy('timesheet_id');

        $approvedAt = [];
        foreach ($approvalLogs as $timesheetId => $logs) {
            $approvedAt[$timesheetId] = $logs->last()->created_at;
        }

        return view('approvals.timesheets.approved', compact('timesheets', 'approvedAt'));
    }

    /**
     * Show a single timesheet for review.
     */
    public function show(Timesheet $timesheet)
    {
        $timesheet->load([
            'user.department',
            'user.timesheetHodApprover',
            'user.timesheetApprover',
            'dayMetadata',
            'adminHours',
            'projectRows.projectCode',
            'projectRows.hours',
            'approvalLogs.user',
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
            // Build display name: prefer category for special entries, otherwise use project code
            $displayCode = $row->projectCode ? $row->projectCode->code : '';
            $displayName = $row->projectCode ? $row->projectCode->name : '';
            if ($row->project_category) {
                $displayCode = $row->project_category;
                $displayName = $row->manual_project_code_name ?? '';
            }

            $projectRowsData[] = [
                'id' => $row->id,
                'project_code_id' => $row->project_code_id,
                'project_code' => $displayCode,
                'project_name' => $displayName,
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

        // Get approved OT form total hours for this user/month
        $approvedOtForm = \App\Models\OtForm::where('user_id', $timesheet->user_id)
            ->where('month', $timesheet->month)
            ->where('year', $timesheet->year)
            ->whereIn('status', ['pending_gm', 'approved'])
            ->first();
        $otApprovedByHr = $approvedOtForm ? floor($approvedOtForm->total_ot_hours * 4) / 4 : null;

        return response()->view('approvals.timesheets.show', compact(
            'timesheet', 'approvalStamps', 'daysInMonth', 'days', 'adminTypes', 'adminHours', 'projectRowsData', 'flatProjectRows', 'otApprovedByHr'
        ))->header('Cache-Control', 'no-cache, no-store, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Submit timesheet for approval with staff digital signature.
     */
    public function submit(Request $request, Timesheet $timesheet)
    {
        try {
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

            // If no HOD approver is designated, skip HOD and go directly to L1
            $initialStatus = $timesheet->user->timesheet_hod_approver_id ? 'pending_hod' : 'pending_l1';

            $timesheet->update([
                'status' => $initialStatus,
                'submitted_at' => now(),
                'staff_signature' => $request->signature,
                'staff_signed_at' => now(),
                'rejection_remarks' => null,
            ]);

            // Notify designated approver(s)
            $this->notifyTimesheetApprovers($timesheet, $initialStatus);

            // Log submission
            TimesheetApprovalLog::create([
                'timesheet_id' => $timesheet->id,
                'user_id' => Auth::id(),
                'level' => '0', // Staff submission (string to match enum)
                'action' => 'submitted',
            ]);

            return response()->json(['success' => true, 'status' => $timesheet->status]);
        } catch (\Exception $e) {
            \Log::error('Timesheet submission error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * HOD/Exec/SPV optional check - can approve or reject.
     * If rejected, cannot proceed to next approval.
     * If not checked, Asst Mgr/Mgr can still approve.
     * If the same person is also Asst Mgr/Mngr, they sign both boxes.
     */
    public function approveHOD(Request $request, Timesheet $timesheet)
    {
        try {
            if ($timesheet->status !== 'pending_hod') {
                return response()->json(['error' => 'Timesheet is not pending HOD check'], 400);
            }

            $user = Auth::user();
            $hodApproverId = $timesheet->user->timesheet_hod_approver_id;
            $l1ApproverId = $timesheet->user->timesheet_approver_id;

            // Only the designated HOD approver or admin can approve
            if ($user->role !== 'admin' && Auth::id() !== $hodApproverId) {
                return response()->json(['error' => 'You are not authorized to approve this timesheet'], 403);
            }

            $request->validate([
                'signature' => 'required|string',
            ]);

            // Check if the same user is also the designated L2 approver
            $isAlsoL1Approver = Auth::id() === $l1ApproverId;

            $timesheet->update([
                'hod_signature' => $request->signature,
                'hod_signed_at' => now(),
            ]);

            TimesheetApprovalLog::create([
                'timesheet_id' => $timesheet->id,
                'user_id' => Auth::id(),
                'level' => '2', // HOD check (level 2)
                'action' => 'approved',
            ]);

            // If the same person is also the L2 approver, sign both boxes and approve
            if ($isAlsoL1Approver) {
                $timesheet->update([
                    'status' => 'approved',
                    'l1_signature' => $request->signature,
                    'l1_signed_at' => now(),
                ]);

                TimesheetApprovalLog::create([
                    'timesheet_id' => $timesheet->id,
                    'user_id' => Auth::id(),
                    'level' => '1', // L1 approval
                    'action' => 'approved',
                ]);

                $this->emailService->sendApprovalNotification($timesheet);
            } else {
                $timesheet->update([
                    'status' => 'pending_l1',
                ]);

                // Notify L2 approver
                if ($l1ApproverId) {
                    Notification::create([
                        'user_id' => $l1ApproverId,
                        'title' => 'Timesheet Pending L2 Approval',
                        'message' => "A timesheet from {$timesheet->user->name} is pending your approval.",
                        'link' => route('approvals.timesheets.show', $timesheet),
                    ]);

                    $l1Approver = User::find($l1ApproverId);
                    if ($l1Approver) {
                        $this->emailService->sendSubmissionNotification($timesheet, $l1Approver);
                    }
                }
            }

            return response()->json(['success' => true, 'status' => $timesheet->status]);
        } catch (\Exception $e) {
            \Log::error('HOD approval error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

        $user = Auth::user();
        $hodApproverId = $timesheet->user->timesheet_hod_approver_id;

        if ($user->role !== 'admin' && Auth::id() !== $hodApproverId) {
            return response()->json(['error' => 'You are not authorized to reject this timesheet'], 403);
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
            'level' => '2', // HOD check (string to match enum)
            'action' => 'rejected',
            'remarks' => $request->remarks,
        ]);

        $this->emailService->sendRejectionNotification($timesheet, Auth::user(), $request->remarks);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * L1 (Asst Mgr) approve with digital signature.
     */
    public function approveL1(Request $request, Timesheet $timesheet)
    {
        try {
            if ($timesheet->status !== 'pending_l1') {
                return response()->json(['error' => 'Timesheet is not pending L1 approval'], 400);
            }

            // Authorization: user must be the designated L2 approver or admin
            $currentUser = Auth::user();
            $l1ApproverId = $timesheet->user->timesheet_approver_id;

            if ($currentUser->role !== 'admin' && Auth::id() !== $l1ApproverId) {
                return response()->json(['error' => 'You are not authorized to approve this timesheet'], 403);
            }

            $request->validate([
                'signature' => 'required|string',
            ]);

            $timesheet->update([
                'status' => 'approved',
                'l1_signature' => $request->signature,
                'l1_signed_at' => now(),
            ]);

            TimesheetApprovalLog::create([
                'timesheet_id' => $timesheet->id,
                'user_id' => Auth::id(),
                'level' => '1', // L1 approval (string to match enum)
                'action' => 'approved',
            ]);

            $this->emailService->sendApprovalNotification($timesheet);

            return response()->json(['success' => true, 'status' => $timesheet->status]);
        } catch (\Exception $e) {
            \Log::error('L1 approval error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * L1 reject with remarks.
     */
    public function rejectL1(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'pending_l1') {
            return response()->json(['error' => 'Timesheet is not pending L1 approval'], 400);
        }

        $user = Auth::user();
        $l1ApproverId = $timesheet->user->timesheet_approver_id;

        if ($user->role !== 'admin' && Auth::id() !== $l1ApproverId) {
            return response()->json(['error' => 'You are not authorized to reject this timesheet'], 403);
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
            'level' => '1', // L1 rejection (string to match enum)
            'action' => 'rejected',
            'remarks' => $request->remarks,
        ]);

        $this->emailService->sendRejectionNotification($timesheet, Auth::user(), $request->remarks);

        return response()->json(['success' => true, 'status' => $timesheet->status]);
    }

    /**
     * Staff can unsubmit their timesheet if no approval has been made yet.
     */
    public function unsubmit(Request $request, Timesheet $timesheet)
    {
        try {
            if ($timesheet->user_id !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            if ($timesheet->status === 'pending_hod') {
                // ok
            } elseif ($timesheet->status === 'pending_l1') {
                $hasHodApproval = $timesheet->approvalLogs()
                    ->where('level', '2')
                    ->where('action', 'approved')
                    ->exists();
                if ($hasHodApproval) {
                    return response()->json(['error' => 'Cannot unsubmit after HOD approval.'], 400);
                }
            } else {
                return response()->json(['error' => 'Timesheet cannot be unsubmitted in its current status.'], 400);
            }

            $timesheet->update(['status' => 'draft']);

            return response()->json([
                'success' => true,
                'status' => $timesheet->status,
                'message' => 'Timesheet unsubmitted. You can now edit it.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Timesheet unsubmit error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

/**
 * Get the statuses this user can act on based on designated approver fields.
 */
private function getPendingStatusesForUser($user): array
{
    if ($user->role === 'admin') {
        return ['pending_hod', 'pending_l1'];
    }

    $statuses = [];

    // Check if user is a designated approver for any staff
    $isHodApprover = User::where('timesheet_hod_approver_id', $user->id)->exists();
    $isL1Approver = User::where('timesheet_approver_id', $user->id)->exists();

    if ($isHodApprover) {
        $statuses[] = 'pending_hod';
    }

    if ($isL1Approver) {
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
        'name' => $timesheet->user->short_name ?? $timesheet->user->name ?? '',
        'role' => 'Staff',
    ];

    // Stamp 2: Checked by (TS1)
    $hodLog = $approvalLogs->where('level', '2')->where('action', 'approved')->first();
    $hodReject = $approvalLogs->where('level', '2')->where('action', 'rejected')->first();
    $hodStatus = 'empty';
    if ($hodLog) {
        $hodStatus = 'approved';
    } elseif ($hodReject) {
        $hodStatus = 'rejected';
    } elseif (in_array($timesheet->status, ['pending_hod', 'pending_l1'])) {
        $hodStatus = 'pending';
    }

    $hodUser = $hodLog ? $hodLog->user : null;
    if (!$hodUser && $hodStatus !== 'empty' && $timesheet->user->timesheet_hod_approver_id) {
        $hodUser = $timesheet->user->timesheetHodApprover;
    }
    $stamps[] = [
        'label' => 'Checked By',
        'code' => 'CHKD',
        'status' => $hodStatus,
        'date' => $timesheet->hod_signed_at
            ? $timesheet->hod_signed_at->format('m/d')
            : ($hodLog && $hodLog->created_at ? $hodLog->created_at->format('m/d') : ''),
        'name' => $hodUser ? ($hodUser->short_name ?? $hodUser->name) : '',
        'role' => $hodUser ? ($hodUser->designation ?? 'TS1') : 'TS1',
    ];

    // Stamp 3: Verified by (TS2)
    $l1Log = $approvalLogs->where('level', '1')->where('action', 'approved')->first();
    $l1Reject = $approvalLogs->where('level', '1')->where('action', 'rejected')->first();
    $l1Status = 'empty';
    if ($l1Log) {
        $l1Status = 'approved';
    } elseif ($l1Reject) {
        $l1Status = 'rejected';
    } elseif (in_array($timesheet->status, ['pending_l1'])) {
        $l1Status = 'pending';
    }

    $l1User = $l1Log ? $l1Log->user : null;
    if (!$l1User && $l1Status !== 'empty' && $timesheet->user->timesheet_approver_id) {
        $l1User = $timesheet->user->timesheetApprover;
    }
    $stamps[] = [
        'label' => 'Verified By',
        'code' => 'VRFD',
        'status' => $l1Status,
        'date' => $timesheet->l1_signed_at
            ? $timesheet->l1_signed_at->format('m/d')
            : ($l1Log && $l1Log->created_at ? $l1Log->created_at->format('m/d') : ''),
        'name' => $l1User ? ($l1User->short_name ?? $l1User->name) : '',
        'role' => $l1User ? ($l1User->designation ?? 'TS2') : 'TS2',
    ];

    return $stamps;
}

/**
 * Notify designated approvers when a timesheet is submitted.
 */
private function notifyTimesheetApprovers(Timesheet $timesheet, string $status): void
{
    $formUser = $timesheet->user;
    $recipientIds = [];

    if ($status === 'pending_hod' && $formUser->timesheet_hod_approver_id) {
        $recipientIds[] = $formUser->timesheet_hod_approver_id;
    }

    if ($status === 'pending_l1' && $formUser->timesheet_approver_id) {
        $recipientIds[] = $formUser->timesheet_approver_id;
    }

    foreach (array_unique($recipientIds) as $recipientId) {
        Notification::create([
            'user_id' => $recipientId,
            'title' => 'Timesheet Pending Approval',
            'message' => "A timesheet from {$formUser->name} is pending your approval.",
            'link' => route('approvals.timesheets.show', $timesheet),
        ]);

        $recipient = User::find($recipientId);
        if ($recipient) {
            $this->emailService->sendSubmissionNotification($timesheet, $recipient);
        }
    }
}
}
