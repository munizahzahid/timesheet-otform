<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Timesheet;
use Illuminate\Support\Facades\Auth;

class TimesheetObserver
{
    public function created(Timesheet $timesheet): void
    {
        $this->logAudit($timesheet, 'created', 'Created timesheet for ' . $timesheet->month . '/' . $timesheet->year);
    }

    public function updated(Timesheet $timesheet): void
    {
        $this->logAudit($timesheet, 'updated', 'Updated timesheet for ' . $timesheet->month . '/' . $timesheet->year);
    }

    public function deleted(Timesheet $timesheet): void
    {
        $this->logAudit($timesheet, 'deleted', 'Deleted timesheet for ' . $timesheet->month . '/' . $timesheet->year);
    }

    private function logAudit(Timesheet $timesheet, string $action, string $description): void
    {
        if (!Auth::check()) return;

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => Timesheet::class,
            'model_id' => $timesheet->id,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }
}
