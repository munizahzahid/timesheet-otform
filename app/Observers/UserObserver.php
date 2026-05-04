<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    public function created(User $user): void
    {
        $this->logAudit($user, 'created', 'Created user: ' . $user->name);
    }

    public function updated(User $user): void
    {
        $this->logAudit($user, 'updated', 'Updated user: ' . $user->name);
    }

    public function deleted(User $user): void
    {
        $this->logAudit($user, 'deleted', 'Deleted user: ' . $user->name);
    }

    private function logAudit(User $user, string $action, string $description): void
    {
        if (!Auth::check()) return;

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }
}
