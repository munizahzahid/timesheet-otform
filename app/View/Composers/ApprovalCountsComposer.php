<?php

namespace App\View\Composers;

use App\Services\DashboardHRSummaryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApprovalCountsComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();
        $service = new DashboardHRSummaryService();

        $view->with([
            'pendingOtApprovalCount' => $service->pendingOtApprovalCount($user),
            'pendingTimesheetApprovalCount' => $service->pendingTimesheetApprovalCount($user),
            'pendingTimesheetCount' => $service->pendingTimesheetCount($user),
            'pendingOtFormCount' => $service->pendingOtFormCount($user),
            'hrNewStatusCount' => $service->newTimesheetCount($user) + $service->newOtFormCount($user),
            'newTimesheetCount' => $service->newTimesheetCount($user),
            'newOtFormCount' => $service->newOtFormCount($user),
        ]);
    }
}
