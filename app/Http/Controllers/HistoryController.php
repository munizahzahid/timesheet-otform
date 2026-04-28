<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\OtForm;
use App\Models\Timesheet;
use App\Models\TimesheetApprovalLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HistoryController extends Controller
{
    /**
     * Show history page with two tabs:
     * 1. My Submissions — user's own timesheets & OT forms (non-draft)
     * 2. My Approval Actions — items this user has approved/rejected (approvers only)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = $request->get('tab', 'submissions');

        // ---------- Tab 1: My Submissions ----------
        $typeFilter = $request->get('type', 'all');
        $statusFilter = $request->get('status', 'all');

        // Timesheets submitted by this user (non-draft)
        $timesheets = collect();
        if ($typeFilter === 'all' || $typeFilter === 'timesheet') {
            $tsQuery = Timesheet::where('user_id', $user->id)
                ->where('status', '!=', 'draft');

            if ($statusFilter !== 'all') {
                if ($statusFilter === 'pending') {
                    $tsQuery->where('status', 'like', 'pending%');
                } elseif ($statusFilter === 'rejected') {
                    $tsQuery->where('status', 'like', 'rejected%');
                } else {
                    $tsQuery->where('status', $statusFilter);
                }
            }

            $timesheets = $tsQuery->orderByDesc('updated_at')->get()->map(function ($ts) {
                return [
                    'type' => 'Timesheet',
                    'type_badge' => 'bg-blue-100 text-blue-800',
                    'description' => \DateTime::createFromFormat('!m', $ts->month)->format('F') . ' ' . $ts->year,
                    'status' => $ts->status,
                    'status_label' => $this->timesheetStatusLabel($ts->status),
                    'status_badge' => $this->statusBadgeClass($ts->status),
                    'submitted_at' => $ts->submitted_at,
                    'updated_at' => $ts->updated_at,
                    'view_url' => route('timesheets.edit', $ts),
                    'sort_date' => $ts->updated_at,
                ];
            });
        }

        // OT forms submitted by this user (non-draft)
        $otForms = collect();
        if ($typeFilter === 'all' || $typeFilter === 'ot_form') {
            $otQuery = OtForm::where('user_id', $user->id)
                ->where('status', '!=', 'draft');

            if ($statusFilter !== 'all') {
                if ($statusFilter === 'pending') {
                    $otQuery->whereIn('status', ['pending_manager', 'pending_gm']);
                } elseif ($statusFilter === 'rejected') {
                    $otQuery->where('status', 'rejected');
                } else {
                    $otQuery->where('status', $statusFilter);
                }
            }

            $otForms = $otQuery->orderByDesc('updated_at')->get()->map(function ($ot) {
                $formLabel = $ot->form_type === 'executive' ? 'Executive' : 'Non-Executive';
                return [
                    'type' => 'OT Form',
                    'type_badge' => 'bg-purple-100 text-purple-800',
                    'description' => $formLabel . ' — ' . \DateTime::createFromFormat('!m', $ot->month)->format('F') . ' ' . $ot->year,
                    'status' => $ot->status,
                    'status_label' => $ot->status_label,
                    'status_badge' => $this->statusBadgeClass($ot->status),
                    'submitted_at' => $ot->plan_submitted_at,
                    'updated_at' => $ot->updated_at,
                    'view_url' => route('ot-forms.edit', $ot),
                    'sort_date' => $ot->updated_at,
                ];
            });
        }

        $submissions = $timesheets->concat($otForms)
            ->sortByDesc('sort_date')
            ->values();

        // ---------- Tab 2: My Approval Actions ----------
        $isApprover = $user->canApproveTimesheetHOD()
            || $user->canApproveTimesheetL1()
            || $user->canApproveOTFormLevel1()
            || $user->canApproveOTFormLevel2()
            || $user->role === 'admin';

        $actionFilter = $request->get('action', 'all');
        $approvalTypeFilter = $request->get('approval_type', 'all');

        $approvalActions = collect();
        if ($isApprover) {
            // Timesheet approval logs by this user
            if ($approvalTypeFilter === 'all' || $approvalTypeFilter === 'timesheet') {
                $tsLogQuery = TimesheetApprovalLog::with(['timesheet.user'])
                    ->where('user_id', $user->id)
                    ->whereIn('action', ['approved', 'rejected']);

                if ($actionFilter !== 'all') {
                    $tsLogQuery->where('action', $actionFilter);
                }

                $tsLogs = $tsLogQuery->orderByDesc('created_at')->get()->map(function ($log) {
                    $ts = $log->timesheet;
                    $staffName = $ts && $ts->user ? $ts->user->name : 'Unknown';
                    $period = $ts ? \DateTime::createFromFormat('!m', $ts->month)->format('F') . ' ' . $ts->year : '';
                    return [
                        'type' => 'Timesheet',
                        'type_badge' => 'bg-blue-100 text-blue-800',
                        'staff_name' => $staffName,
                        'description' => $period,
                        'action' => $log->action,
                        'action_badge' => $log->action === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800',
                        'remarks' => $log->remarks,
                        'acted_at' => $log->created_at,
                        'view_url' => $ts ? route('approvals.timesheets.show', $ts) : '#',
                        'sort_date' => $log->created_at,
                    ];
                });

                $approvalActions = $approvalActions->concat($tsLogs);
            }

            // OT form approval logs by this user
            if ($approvalTypeFilter === 'all' || $approvalTypeFilter === 'ot_form') {
                $otLogQuery = ApprovalLog::with('approver')
                    ->where('approver_id', $user->id)
                    ->where('approvable_type', 'ot_form')
                    ->whereIn('action', ['approved', 'rejected']);

                if ($actionFilter !== 'all') {
                    $otLogQuery->where('action', $actionFilter);
                }

                $otLogs = $otLogQuery->orderByDesc('acted_at')->get()->map(function ($log) {
                    $otForm = OtForm::with('user')->find($log->approvable_id);
                    $staffName = $otForm && $otForm->user ? $otForm->user->name : 'Unknown';
                    $formLabel = $otForm ? ($otForm->form_type === 'executive' ? 'Executive' : 'Non-Executive') : '';
                    $period = $otForm ? \DateTime::createFromFormat('!m', $otForm->month)->format('F') . ' ' . $otForm->year : '';
                    return [
                        'type' => 'OT Form',
                        'type_badge' => 'bg-purple-100 text-purple-800',
                        'staff_name' => $staffName,
                        'description' => $formLabel . ($period ? ' — ' . $period : ''),
                        'action' => $log->action,
                        'action_badge' => $log->action === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800',
                        'remarks' => $log->remarks,
                        'acted_at' => $log->acted_at,
                        'view_url' => $otForm ? route('approvals.ot-forms.show', $otForm) : '#',
                        'sort_date' => $log->acted_at,
                    ];
                });

                $approvalActions = $approvalActions->concat($otLogs);
            }

            $approvalActions = $approvalActions->sortByDesc('sort_date')->values();
        }

        return view('history.index', compact(
            'tab', 'submissions', 'approvalActions', 'isApprover',
            'typeFilter', 'statusFilter', 'actionFilter', 'approvalTypeFilter'
        ));
    }

    private function timesheetStatusLabel(string $status): string
    {
        return match ($status) {
            'pending_hod' => 'Pending HOD',
            'pending_l1' => 'Pending Asst Mgr',
            'pending_l2' => 'Pending Manager',
            'pending_l3' => 'Pending CEO/DGM',
            'rejected_hod' => 'Rejected by HOD',
            'rejected_l1' => 'Rejected by Asst Mgr',
            'rejected_l2' => 'Rejected by Manager',
            'rejected_l3' => 'Rejected by CEO/DGM',
            'approved' => 'Approved',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function statusBadgeClass(string $status): string
    {
        if (str_starts_with($status, 'pending')) {
            return 'bg-yellow-100 text-yellow-800';
        }
        if (str_starts_with($status, 'rejected')) {
            return 'bg-red-100 text-red-800';
        }
        if ($status === 'approved') {
            return 'bg-green-100 text-green-800';
        }
        return 'bg-gray-100 text-gray-800';
    }
}
