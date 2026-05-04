<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\OtForm;
use Illuminate\Support\Facades\Auth;

class OtFormObserver
{
    public function created(OtForm $otForm): void
    {
        $this->logAudit($otForm, 'created', 'Created OT form for ' . $otForm->month . '/' . $otForm->year);
    }

    public function updated(OtForm $otForm): void
    {
        $this->logAudit($otForm, 'updated', 'Updated OT form for ' . $otForm->month . '/' . $otForm->year);
    }

    public function deleted(OtForm $otForm): void
    {
        $this->logAudit($otForm, 'deleted', 'Deleted OT form for ' . $otForm->month . '/' . $otForm->year);
    }

    private function logAudit(OtForm $otForm, string $action, string $description): void
    {
        if (!Auth::check()) return;

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => OtForm::class,
            'model_id' => $otForm->id,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }
}
