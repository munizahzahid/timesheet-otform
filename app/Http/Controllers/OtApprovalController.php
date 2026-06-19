<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\Notification;
use App\Models\OtForm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtApprovalController extends Controller
{
    /**
     * List OT forms pending the current user's approval/review.
     */
    public function index()
    {
        $user = Auth::user();
        $pendingStatuses = $this->getPendingStatusesForUser($user);

        if (empty($pendingStatuses)) {
            $otForms = OtForm::where('id', 0)->paginate(20);
            return view('approvals.ot-forms.index', compact('otForms'));
        }

        $query = OtForm::with('user')
            ->whereIn('status', $pendingStatuses);

        // Filter by reports_to for pending_manager (level 1) — not for admin/hr
        if (in_array('pending_manager', $pendingStatuses) && !in_array($user->role, ['admin', 'hr'])) {
            $query->where(function ($q) use ($user, $pendingStatuses) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('status', 'pending_manager')
                        ->whereHas('user', function ($uq) use ($user) {
                            $uq->where('reports_to', $user->id);
                        });
                });
                // Also include other statuses the user can see
                $otherStatuses = array_diff($pendingStatuses, ['pending_manager']);
                if (!empty($otherStatuses)) {
                    $q->orWhereIn('status', $otherStatuses);
                }
            });
        }

        $otForms = $query->orderByDesc('updated_at')->paginate(20);

        return view('approvals.ot-forms.index', compact('otForms'));
    }

    /**
     * Show a single OT form for review.
     */
    public function show(OtForm $otForm)
    {
        $otForm->load('entries.projectCode', 'user.department');

        // Build approval stamps
        $approvalLogs = $otForm->approvalLogs()->get();
        $approvalStamps = $this->buildApprovalStamps($otForm, $approvalLogs);

        return view('approvals.ot-forms.show', compact('otForm', 'approvalStamps'));
    }

    /**
     * Approve OT form (Manager/HOD or CEO).
     */
    public function approve(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        // Check if user is authorized to approve based on designated approvers
        $canApprove = $this->canUserApproveByDesignation($otForm, $user);
        if (!$canApprove) {
            return response()->json(['error' => 'You are not authorized to approve this OT form.'], 403);
        }

        $nextStatus = $this->getNextStatus($otForm->status, $user);
        if (!$nextStatus) {
            return response()->json(['error' => 'This OT form is not pending your approval.'], 403);
        }

        $request->validate(['signature' => 'required|string']);

        $level = match ($otForm->status) {
            'pending_manager' => 2,  // Manager/HOD level
            'pending_gm' => 1,       // GM/CEO level
            default => 1,
        };

        $otForm->update(['status' => $nextStatus]);

        try {
            ApprovalLog::create([
                'approvable_type' => 'ot_form',
                'approvable_id' => $otForm->id,
                'approver_id' => $user->id,
                'phase' => 'ot_pre',
                'level' => $level,
                'action' => 'approved',
                'acted_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create approval log: ' . $e->getMessage());
        }

        // Send notifications based on new status
        if ($nextStatus === 'pending_hr') {
            $monthName = \DateTime::createFromFormat('!m', $otForm->month)->format('F');
            $this->notifyHRUsers($otForm, 'OT Form Pending HR Review', "{$otForm->user->name}'s OT Form ({$otForm->form_type_label}) for {$monthName} {$otForm->year} is pending your review.");
        }

        $message = match ($nextStatus) {
            'pending_hr' => 'OT form approved by Manager/HOD, forwarded to HR for review.',
            'approved' => 'OT form approved by CEO.',
            default => 'OT form approved.',
        };

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => $message,
        ]);
    }

    /**
     * HR forwards OT form to CEO.
     */
    public function hrForward(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        if (!$user->canReviewOTForm()) {
            return response()->json(['error' => 'You are not authorized to review this OT form.'], 403);
        }

        if ($otForm->status !== 'pending_hr') {
            return response()->json(['error' => 'This OT form is not pending HR review.'], 400);
        }

        $otForm->update(['status' => 'pending_gm']);

        ApprovalLog::create([
            'approvable_type' => 'ot_form',
            'approvable_id' => $otForm->id,
            'approver_id' => $user->id,
            'phase' => 'ot_pre',
            'level' => 0,
            'action' => 'hr_forwarded',
            'acted_at' => now(),
        ]);

        // Notify CEO / final approver
        $this->notifyCEOUsers($otForm, 'OT Form Pending CEO Approval', "{$otForm->user->name}'s OT Form has been forwarded by HR for your approval.");

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => 'OT form forwarded to CEO for approval.',
        ]);
    }

    /**
     * HR returns OT form for correction.
     */
    public function hrReturn(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        if (!$user->canReviewOTForm()) {
            return response()->json(['error' => 'You are not authorized to review this OT form.'], 403);
        }

        if ($otForm->status !== 'pending_hr') {
            return response()->json(['error' => 'This OT form is not pending HR review.'], 400);
        }

        $request->validate(['remarks' => 'required|string']);

        $otForm->update(['status' => 'returned_hr']);

        ApprovalLog::create([
            'approvable_type' => 'ot_form',
            'approvable_id' => $otForm->id,
            'approver_id' => $user->id,
            'phase' => 'ot_pre',
            'level' => 0,
            'action' => 'hr_returned',
            'remarks' => $request->remarks,
            'acted_at' => now(),
        ]);

        // Notify employee
        Notification::create([
            'user_id' => $otForm->user_id,
            'title' => 'OT Form Returned for Correction',
            'message' => "Your OT Form has been returned by HR for correction. Reason: {$request->remarks}",
            'link' => route('ot-forms.edit', $otForm),
        ]);

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => 'OT form returned to employee for correction.',
        ]);
    }

    /**
     * Reject OT form.
     */
    public function reject(Request $request, OtForm $otForm)
    {
        $user = Auth::user();

        if (!in_array($otForm->status, ['pending_manager', 'pending_gm'])) {
            return response()->json(['error' => 'This OT form is not pending approval.'], 403);
        }

        $canApprove = $this->canUserApprove($otForm, $user);
        if (!$canApprove) {
            return response()->json(['error' => 'You are not authorized to reject this OT form.'], 403);
        }

        $request->validate(['remarks' => 'required|string']);

        $level = $otForm->status === 'pending_gm' ? 2 : 1;

        $otForm->update(['status' => 'rejected']);

        ApprovalLog::create([
            'approvable_type' => 'ot_form',
            'approvable_id' => $otForm->id,
            'approver_id' => $user->id,
            'phase' => 'ot_pre',
            'level' => $level,
            'action' => 'rejected',
            'remarks' => $request->remarks,
            'acted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => 'OT form rejected.',
        ]);
    }

    /**
     * Determine if user can approve based on reports_to relationship.
     */
    private function canUserApproveByDesignation(OtForm $otForm, $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $formUser = $otForm->user;
        $staffReportsTo = $formUser->reports_to;

        // Level 1 (pending_manager): user must be the staff's reports_to
        if ($otForm->status === 'pending_manager') {
            if ($staffReportsTo && Auth::id() === $staffReportsTo) {
                return $user->canApproveOTFormLevel1();
            }
            return false;
        }

        // Level 2 (pending_gm): CEO or designated final approver
        if ($otForm->status === 'pending_gm') {
            // Check if user is designated final approver
            if ($otForm->form_type === 'executive' && $formUser->ot_exec_final_approver_id) {
                return Auth::id() === $formUser->ot_exec_final_approver_id;
            }
            if ($otForm->form_type === 'non_executive' && $formUser->ot_non_exec_final_approver_id) {
                return Auth::id() === $formUser->ot_non_exec_final_approver_id;
            }
            // Fallback to role-based routing for CEO
            return $user->canApproveOTFormLevel2();
        }

        return false;
    }

    /**
     * Determine if user can approve based on designation hierarchy (legacy/fallback).
     */
    private function canUserApprove(OtForm $otForm, $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $designationLower = strtolower($user->designation ?? '');

        // GM can approve pending_gm
        if ($otForm->status === 'pending_gm') {
            $isGM = str_contains($designationLower, 'general manager') ||
                    str_contains($designationLower, 'gm') ||
                    str_contains($designationLower, 'ceo');
            return $isGM;
        }

        // Manager/Asst Manager can approve pending_manager
        if ($otForm->status === 'pending_manager') {
            $isManager = str_contains($designationLower, 'manager') ||
                         str_contains($designationLower, 'asst');
            return $isManager;
        }

        return false;
    }

    /**
     * Get next status based on current status and user designation.
     */
    private function getNextStatus(string $currentStatus, $user): ?string
    {
        $designationLower = strtolower($user->designation ?? '');

        if ($currentStatus === 'pending_manager') {
            // Manager/Asst Manager approves → send to HR review (not directly to CEO)
            $isManager = str_contains($designationLower, 'manager') || str_contains($designationLower, 'asst');
            if ($isManager) {
                return 'pending_hr';
            }
        }

        if ($currentStatus === 'pending_gm') {
            // CEO approves → final approval
            $isGM = str_contains($designationLower, 'general manager') ||
                    str_contains($designationLower, 'gm') ||
                    str_contains($designationLower, 'ceo');
            if ($isGM) {
                return 'approved';
            }
        }

        return null;
    }

    /**
     * Get the statuses this user can act on based on reports_to relationship.
     */
    private function getPendingStatusesForUser($user): array
    {
        if ($user->role === 'admin') {
            return ['pending_manager', 'pending_hr', 'pending_gm'];
        }

        $statuses = [];

        // HR can review pending_hr forms
        if ($user->canReviewOTForm()) {
            $statuses[] = 'pending_hr';
        }

        // Check if user is a supervisor (reports_to) for any staff
        $isSupervisor = User::where('reports_to', $user->id)->exists();

        if ($isSupervisor) {
            // Manager/HOD can approve pending_manager (level 1)
            if ($user->canApproveOTFormLevel1()) {
                $statuses[] = 'pending_manager';
            }
        }

        // CEO or designated final approver can approve pending_gm (level 2)
        $isDesignatedFinalApprover = User::where('ot_exec_final_approver_id', $user->id)
            ->orWhere('ot_non_exec_final_approver_id', $user->id)
            ->exists();
        if ($user->canApproveOTFormLevel2() || $isDesignatedFinalApprover) {
            $statuses[] = 'pending_gm';
        }

        return $statuses;
    }

    /**
     * Build stamp data array for approval-stamps component.
     * OT Forms: "Claimed by" (staff) + "Approved by" (HOD/Manager).
     */
    private function buildApprovalStamps(OtForm $otForm, $approvalLogs): array
    {
        $stamps = [];
        $submitted = $otForm->plan_submitted_at || !in_array($otForm->status, ['draft']);

        // Stamp 1: Staff who submitted
        $stamps[] = [
            'label'  => $otForm->isNonExecutive() ? 'Disediakan Oleh' : 'Claimed by',
            'code'   => 'CLMD',
            'status' => $submitted && $otForm->status !== 'draft' ? 'approved' : 'empty',
            'date'   => $otForm->plan_submitted_at ? $otForm->plan_submitted_at->format('m/d') : '',
            'name'   => $otForm->user->name ?? '',
            'role'   => $otForm->user->designation ?? 'Staff',
        ];

        // Find approval logs
        $managerLog = $approvalLogs->where('level', 2)->where('action', 'approved')->first();
        $gmLog = $approvalLogs->where('level', 1)->where('action', 'approved')->first();

        // Manager/HOD stamp
        $managerStatus = 'empty';
        if ($managerLog) {
            $managerStatus = 'approved';
        } elseif (in_array($otForm->status, ['pending_manager'])) {
            $managerStatus = 'pending';
        } elseif (in_array($otForm->status, ['pending_hr', 'pending_gm', 'approved', 'returned_hr'])) {
            $managerStatus = 'approved';
        }

        $managerUser = $managerLog ? $managerLog->approver : null;
        if (!$managerUser && $managerStatus === 'approved') {
            if ($otForm->isExecutive() && $otForm->user->ot_exec_approver_id) {
                $managerUser = User::find($otForm->user->ot_exec_approver_id);
            } elseif (!$otForm->isExecutive() && $otForm->user->ot_non_exec_approver_id) {
                $managerUser = User::find($otForm->user->ot_non_exec_approver_id);
            }
        }
        $stamps[] = [
            'label'  => $otForm->isNonExecutive() ? 'Disokong Oleh' : 'Approved by',
            'code'   => 'APRV',
            'status' => $managerStatus,
            'date'   => $managerLog && $managerLog->acted_at ? $managerLog->acted_at->format('m/d') : '',
            'name'   => $managerUser ? $managerUser->name : '',
            'role'   => $managerUser ? ($managerUser->designation ?? 'MGR / HOD') : 'MGR / HOD',
        ];

        // Non-executive: add 3rd stamp for DGM/CEO
        if ($otForm->isNonExecutive()) {
            $gmStatus = 'empty';
            if ($gmLog) {
                $gmStatus = 'approved';
            } elseif (in_array($otForm->status, ['pending_gm'])) {
                $gmStatus = 'pending';
            } elseif ($otForm->status === 'approved') {
                $gmStatus = 'approved';
            }

            $gmUser = $gmLog ? $gmLog->approver : null;
            if (!$gmUser && $gmStatus === 'approved') {
                if ($otForm->user->ot_non_exec_final_approver_id) {
                    $gmUser = User::find($otForm->user->ot_non_exec_final_approver_id);
                }
            }
            $stamps[] = [
                'label'  => 'Diluluskan Oleh',
                'code'   => 'APRV',
                'status' => $gmStatus,
                'date'   => $gmLog && $gmLog->acted_at ? $gmLog->acted_at->format('m/d') : '',
                'name'   => $gmUser ? $gmUser->name : '',
                'role'   => $gmUser ? ($gmUser->designation ?? 'DGM / CEO') : 'DGM / CEO',
            ];
        }

        return $stamps;
    }

    /**
     * Notify all HR users about an OT form event.
     */
    private function notifyHRUsers(OtForm $otForm, string $title, string $message): void
    {
        $hrUsers = User::where('role', 'hr')->where('is_active', true)->get();
        foreach ($hrUsers as $hrUser) {
            Notification::create([
                'user_id' => $hrUser->id,
                'title' => $title,
                'message' => $message,
                'link' => route('approvals.ot-forms.show', $otForm),
            ]);
        }
    }

    /**
     * Notify CEO / final approver users about an OT form event.
     */
    private function notifyCEOUsers(OtForm $otForm, string $title, string $message): void
    {
        $formUser = $otForm->user;

        // Try designated final approver first
        $finalApproverId = $otForm->form_type === 'executive'
            ? $formUser->ot_exec_final_approver_id
            : $formUser->ot_non_exec_final_approver_id;

        if ($finalApproverId) {
            Notification::create([
                'user_id' => $finalApproverId,
                'title' => $title,
                'message' => $message,
                'link' => route('approvals.ot-forms.show', $otForm),
            ]);
            return;
        }

        // Fallback: notify all CEO-role users
        $ceoUsers = User::where('role', 'ceo')->where('is_active', true)->get();
        foreach ($ceoUsers as $ceoUser) {
            Notification::create([
                'user_id' => $ceoUser->id,
                'title' => $title,
                'message' => $message,
                'link' => route('approvals.ot-forms.show', $otForm),
            ]);
        }
    }
}
