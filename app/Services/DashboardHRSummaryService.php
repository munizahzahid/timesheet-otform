<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\AuditLog;
use App\Models\OtForm;
use App\Models\Timesheet;
use App\Models\TimesheetApprovalLog;
use App\Models\User;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Session;

class DashboardHRSummaryService
{
    public function getSummary(User $user): array
    {
        return [
            'recentActions' => $this->recentActions($user),
            'recentUpdates' => $this->recentUpdates($user),
            'pendingTimesheetApprovalCount' => $this->pendingTimesheetApprovalCount($user),
            'pendingOtApprovalCount' => $this->pendingOtApprovalCount($user),
            'canApproveTimesheets' => $this->canApproveTimesheets($user),
            'canApproveOtForms' => $this->canApproveOtForms($user),
        ];
    }

    public function recentActions(User $user, int $limit = 5): SupportCollection
    {
        return AuditLog::with('user')
            ->where('user_id', $user->id)
            ->whereIn('model_type', [Timesheet::class, OtForm::class])
            ->latest()
            ->take($limit)
            ->get();
    }

    public function recentUpdates(User $user, int $limit = 5): SupportCollection
    {
        $timesheetIds = $user->timesheets()->pluck('id');
        $otFormIds = $user->otForms()->pluck('id');

        $timesheetLogs = collect();
        $otFormLogs = collect();

        if ($timesheetIds->isNotEmpty()) {
            $timesheetLogs = TimesheetApprovalLog::with(['timesheet', 'user'])
                ->whereIn('timesheet_id', $timesheetIds)
                ->latest()
                ->take($limit)
                ->get()
                ->map(fn ($log) => [
                    'type' => 'timesheet',
                    'model' => $log->timesheet,
                    'action' => $log->action,
                    'actor' => $log->user,
                    'time' => $log->created_at,
                ]);
        }

        if ($otFormIds->isNotEmpty()) {
            $otFormLogs = ApprovalLog::with('approver')
                ->where('approvable_type', 'ot_form')
                ->whereIn('approvable_id', $otFormIds)
                ->latest('acted_at')
                ->take($limit)
                ->get()
                ->map(fn ($log) => [
                    'type' => 'ot_form',
                    'model' => OtForm::find($log->approvable_id),
                    'action' => $log->action,
                    'actor' => $log->approver,
                    'time' => $log->acted_at,
                ]);
        }

        return $timesheetLogs
            ->merge($otFormLogs)
            ->sortByDesc('time')
            ->take($limit)
            ->values();
    }

    public function pendingTimesheetApprovalCount(User $user): int
    {
        $statuses = $this->pendingTimesheetStatusesForUser($user);

        if (empty($statuses)) {
            return 0;
        }

        $query = Timesheet::whereIn('status', $statuses);

        if ($user->role !== 'admin') {
            $query->whereHas('user', function ($q) use ($user, $statuses) {
                $q->where(function ($sub) use ($user, $statuses) {
                    if (in_array('pending_hod', $statuses)) {
                        $sub->orWhere('timesheet_hod_approver_id', $user->id);
                    }
                    if (in_array('pending_l1', $statuses)) {
                        $sub->orWhere('timesheet_approver_id', $user->id);
                    }
                });
            });
        }

        return $query->count();
    }

    public function pendingOtApprovalCount(User $user): int
    {
        $statuses = $this->pendingOtStatusesForUser($user);

        if (empty($statuses)) {
            return 0;
        }

        $query = OtForm::whereIn('status', $statuses);

        if (!in_array($user->role, ['admin', 'hr'])) {
            $query->where(function ($q) use ($user, $statuses) {
                if (in_array('pending_manager', $statuses)) {
                    $q->whereHas('user', function ($uq) use ($user) {
                        $uq->where('ot_approver_id', $user->id);
                    });
                }
                if (in_array('pending_gm', $statuses)) {
                    $q->orWhereHas('user', function ($uq) use ($user) {
                        $uq->where('ot_final_approver_id', $user->id);
                    });
                }
                if (in_array('pending_hr', $statuses)) {
                    $q->orWhere('status', 'pending_hr');
                }
            });
        }

        return $query->count();
    }

    public function pendingTimesheetCount(User $user): int
    {
        return Timesheet::where('user_id', $user->id)
            ->whereNotIn('status', ['draft', 'approved'])
            ->count();
    }

    public function pendingOtFormCount(User $user): int
    {
        return OtForm::where('user_id', $user->id)
            ->whereNotIn('status', ['draft', 'approved'])
            ->count();
    }

    public function newTimesheetCount(User $user): int
    {
        $lastSeen = Session::get('timesheets_last_seen');

        $query = Timesheet::where('user_id', $user->id)
            ->whereNotIn('status', ['draft', 'approved']);

        if ($lastSeen) {
            $query->where('updated_at', '>', $lastSeen);
        }

        return $query->count();
    }

    public function newOtFormCount(User $user): int
    {
        $lastSeen = Session::get('ot_forms_last_seen');

        $query = OtForm::where('user_id', $user->id)
            ->whereNotIn('status', ['draft', 'approved']);

        if ($lastSeen) {
            $query->where('updated_at', '>', $lastSeen);
        }

        return $query->count();
    }

    public function canApproveTimesheets(User $user): bool
    {
        return !empty($this->pendingTimesheetStatusesForUser($user));
    }

    public function canApproveOtForms(User $user): bool
    {
        return !empty($this->pendingOtStatusesForUser($user));
    }

    private function pendingTimesheetStatusesForUser(User $user): array
    {
        if ($user->role === 'admin') {
            return ['pending_hod', 'pending_l1'];
        }

        $statuses = [];
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

    private function pendingOtStatusesForUser(User $user): array
    {
        if ($user->role === 'admin') {
            return ['pending_manager', 'pending_hr', 'pending_gm'];
        }

        $statuses = [];

        if ($user->canReviewOTForm()) {
            $statuses[] = 'pending_hr';
        }

        $isOtApprover = User::where('ot_approver_id', $user->id)->exists();
        $isOtFinalApprover = User::where('ot_final_approver_id', $user->id)->exists();

        if ($isOtApprover) {
            $statuses[] = 'pending_manager';
        }

        if ($isOtFinalApprover) {
            $statuses[] = 'pending_gm';
        }

        return $statuses;
    }
}
