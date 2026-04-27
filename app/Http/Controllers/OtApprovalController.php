<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\OtForm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtApprovalController extends Controller
{
    /**
     * List OT forms pending the current user's approval.
     */
    public function index()
    {
        $user = Auth::user();
        $pendingStatuses = $this->getPendingStatusesForUser($user);

        $otForms = OtForm::with('user')
            ->whereIn('status', $pendingStatuses)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('approvals.ot-forms.index', compact('otForms'));
    }

    /**
     * Show a single OT form for review.
     */
    public function show(OtForm $otForm)
    {
        $otForm->load('entries.projectCode', 'user.department');

        // Build approval stamps
        $approvalLogs = $otForm->approvalLogs();
        $approvalStamps = $this->buildApprovalStamps($otForm, $approvalLogs);

        return view('approvals.ot-forms.show', compact('otForm', 'approvalStamps'));
    }

    /**
     * Approve OT form.
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

        $level = $nextStatus === 'pending_gm' ? 2 : 1;

        $otForm->update(['status' => $nextStatus]);

        try {
            ApprovalLog::create([
                'approvable_type' => 'ot_form',
                'approvable_id' => $otForm->id,
                'approver_id' => $user->id,
                'phase' => 'approval',
                'level' => $level,
                'action' => 'approved',
                'acted_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the approval
            \Log::error('Failed to create approval log: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'status' => $otForm->status,
            'message' => $otForm->status === 'approved' ? 'OT form approved.' : 'OT form approved, forwarded to GM.',
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

        $otForm->update(['status' => 'rejected']);

        $level = $otForm->status === 'pending_gm' ? 2 : 1;

        ApprovalLog::create([
            'approvable_type' => 'ot_form',
            'approvable_id' => $otForm->id,
            'approver_id' => $user->id,
            'phase' => 'approval',
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
     * Determine if user can approve based on designated approvers or role-based routing.
     */
    private function canUserApproveByDesignation(OtForm $otForm, $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $formUser = $otForm->user;

        // For executive OT forms
        if ($otForm->form_type === 'executive') {
            $hodApproverId = $formUser->ot_exec_approver_id;
            $finalApproverId = $formUser->ot_exec_final_approver_id;

            // Check if user is the designated HOD approver (level 1)
            if ($otForm->status === 'pending_manager') {
                if ($hodApproverId) {
                    return Auth::id() === $hodApproverId;
                }
                // Fallback to role-based routing
                return $user->canApproveOTFormLevel1();
            }

            // Check if user is the designated final approver (level 2 - DGM/CEO)
            if ($otForm->status === 'pending_gm') {
                if ($finalApproverId) {
                    return Auth::id() === $finalApproverId;
                }
                // Fallback to role-based routing
                return $user->canApproveOTFormLevel2();
            }

            return false;
        }

        // For non-executive OT forms
        if ($otForm->form_type === 'non_executive') {
            $mgrApproverId = $formUser->ot_non_exec_approver_id;
            $finalApproverId = $formUser->ot_non_exec_final_approver_id;

            // Check if user is the designated Mgr/HOD approver (level 1)
            if ($otForm->status === 'pending_manager') {
                if ($mgrApproverId) {
                    return Auth::id() === $mgrApproverId;
                }
                // Fallback to role-based routing
                return $user->canApproveOTFormLevel1();
            }

            // Check if user is the designated final approver (level 2 - DGM/CEO)
            if ($otForm->status === 'pending_gm') {
                if ($finalApproverId) {
                    return Auth::id() === $finalApproverId;
                }
                // Fallback to role-based routing
                return $user->canApproveOTFormLevel2();
            }

            return false;
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
            // Manager/Asst Manager approves → send to GM
            $isManager = str_contains($designationLower, 'manager') || str_contains($designationLower, 'asst');
            if ($isManager) {
                return 'pending_gm';
            }
        }

        if ($currentStatus === 'pending_gm') {
            // GM approves → final approval
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
     * Get the statuses this user can act on based on role or designated approver assignments.
     */
    private function getPendingStatusesForUser($user): array
    {
        if ($user->role === 'admin') {
            return ['pending_manager', 'pending_gm'];
        }

        // Check if user is a designated approver for any pending OT forms
        $isDesignatedHodApprover = User::where('ot_exec_approver_id', $user->id)->exists();
        $isDesignatedMgrApprover = User::where('ot_non_exec_approver_id', $user->id)->exists();
        $isDesignatedFinalApprover = User::where('ot_exec_final_approver_id', $user->id)
            ->orWhere('ot_non_exec_final_approver_id', $user->id)
            ->exists();

        // CEO or designated final approver can approve pending_gm
        if ($user->canApproveOTFormLevel2() || $isDesignatedFinalApprover) {
            return ['pending_gm'];
        }

        // Manager/HOD or designated HOD/Mgr approver can approve pending_manager
        if ($user->canApproveOTFormLevel1() || $isDesignatedHodApprover || $isDesignatedMgrApprover) {
            return ['pending_manager'];
        }

        return [];
    }

    /**
     * Build stamp data array for approval-stamps component.
     * OT Forms: "Claimed by" (staff) + "Approved by" (HOD/Manager).
     */
    private function buildApprovalStamps(OtForm $otForm, $approvalLogs): array
    {
        $latestApproval = $approvalLogs->where('action', 'approved')->sortByDesc('level')->first();
        $latestReject = $approvalLogs->where('action', 'rejected')->sortByDesc('level')->first();
        $hodLog = $latestApproval ?? $latestReject;

        $stamps = [];

        // Stamp 1: Claimed by (Staff who submitted)
        $submitted = $otForm->plan_submitted_at || !in_array($otForm->status, ['draft']);
        $stamps[] = [
            'label'  => 'Claimed by',
            'code'   => 'CLMD',
            'status' => $submitted && $otForm->status !== 'draft' ? 'approved' : 'empty',
            'date'   => $otForm->plan_submitted_at ? $otForm->plan_submitted_at->format('m/d') : '',
            'name'   => $otForm->user->name ?? '',
            'role'   => 'Staff',
        ];

        // Stamp 2: Approved by (HOD / Manager who approved)
        $hodStatus = 'empty';
        if ($hodLog && $hodLog->action === 'approved') {
            $hodStatus = 'approved';
        } elseif ($hodLog && $hodLog->action === 'rejected') {
            $hodStatus = 'rejected';
        } elseif (in_array($otForm->status, ['pending_manager', 'pending_gm'])) {
            $hodStatus = 'pending';
        } elseif ($otForm->status === 'approved') {
            $hodStatus = 'approved';
        }

        $hodUser = $hodLog ? $hodLog->approver : null;
        $stamps[] = [
            'label'  => 'Approved by',
            'code'   => 'APRV',
            'status' => $hodStatus,
            'date'   => $hodLog && $hodLog->acted_at ? $hodLog->acted_at->format('m/d') : '',
            'name'   => $hodUser ? $hodUser->name : '',
            'role'   => $hodUser ? ($hodUser->designation ?? 'HOD') : 'HOD',
        ];

        return $stamps;
    }
}
