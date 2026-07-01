<?php

namespace App\View\Composers;

use App\Models\OtForm;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApprovalCountsComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();

        $view->with([
            'pendingOtApprovalCount' => $this->pendingOtApprovalCount($user),
            'pendingTimesheetApprovalCount' => $this->pendingTimesheetApprovalCount($user),
            'pendingTimesheetCount' => $this->pendingTimesheetCount($user),
            'pendingOtFormCount' => $this->pendingOtFormCount($user),
            'hrNewStatusCount' => $this->hrNewStatusCount($user),
            'newTimesheetCount' => $this->newTimesheetCount($user),
            'newOtFormCount' => $this->newOtFormCount($user),
        ]);
    }

    private function pendingOtApprovalCount(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $statuses = $this->pendingOtStatusesForUser($user);

        if (empty($statuses)) {
            return 0;
        }

        $query = OtForm::whereIn('status', $statuses);

        if (in_array('pending_manager', $statuses) && !in_array($user->role, ['admin', 'hr'])) {
            $query->where(function ($q) use ($user, $statuses) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('status', 'pending_manager')
                        ->whereHas('user', function ($uq) use ($user) {
                            $uq->where('reports_to', $user->id);
                        });
                });

                $otherStatuses = array_diff($statuses, ['pending_manager']);
                if (!empty($otherStatuses)) {
                    $q->orWhereIn('status', $otherStatuses);
                }
            });
        }

        return $query->count();
    }

    private function pendingTimesheetApprovalCount(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $statuses = $this->pendingTimesheetStatusesForUser($user);

        if (empty($statuses)) {
            return 0;
        }

        $query = Timesheet::whereIn('status', $statuses);

        if ($user->role !== 'admin') {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('reports_to', $user->id);
            });
        }

        return $query->count();
    }

    private function pendingTimesheetCount(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        return Timesheet::where('user_id', $user->id)
            ->whereNotIn('status', ['draft', 'approved'])
            ->count();
    }

    private function pendingOtFormCount(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        return OtForm::where('user_id', $user->id)
            ->whereNotIn('status', ['draft', 'approved'])
            ->count();
    }

    private function hrNewStatusCount(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        return $this->newTimesheetCount($user) + $this->newOtFormCount($user);
    }

    private function newTimesheetCount(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $lastSeen = session('timesheets_last_seen');

        $query = Timesheet::where('user_id', $user->id)
            ->whereNotIn('status', ['draft', 'approved']);

        if ($lastSeen) {
            $query->where('updated_at', '>', $lastSeen);
        }

        return $query->count();
    }

    private function newOtFormCount(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $lastSeen = session('ot_forms_last_seen');

        $query = OtForm::where('user_id', $user->id)
            ->whereNotIn('status', ['draft', 'approved']);

        if ($lastSeen) {
            $query->where('updated_at', '>', $lastSeen);
        }

        return $query->count();
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

        $isSupervisor = User::where('reports_to', $user->id)->exists();

        if ($isSupervisor && $user->canApproveOTFormLevel1()) {
            $statuses[] = 'pending_manager';
        }

        $isDesignatedFinalApprover = User::where('ot_exec_final_approver_id', $user->id)
            ->orWhere('ot_non_exec_final_approver_id', $user->id)
            ->exists();

        if ($user->canApproveOTFormLevel2() || $isDesignatedFinalApprover) {
            $statuses[] = 'pending_gm';
        }

        return $statuses;
    }

    private function pendingTimesheetStatusesForUser(User $user): array
    {
        if ($user->role === 'admin') {
            return ['pending_hod', 'pending_l1'];
        }

        $statuses = [];

        $isSupervisor = User::where('reports_to', $user->id)->exists();

        if ($isSupervisor) {
            if ($user->canApproveTimesheetL1()) {
                $statuses[] = 'pending_l1';
            }

            if ($user->canApproveTimesheetHOD()) {
                $statuses[] = 'pending_hod';
            }
        }

        return $statuses;
    }
}
