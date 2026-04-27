<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'desknet_id', 'staff_no', 'name', 'email', 'password',
        'role', 'department_id', 'reports_to', 'designation',
        'is_active', 'last_synced_at',
        'timesheet_approver_id', 'ot_exec_approver_id', 'ot_exec_final_approver_id',
        'ot_non_exec_approver_id', 'ot_non_exec_final_approver_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reports_to');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'reports_to');
    }

    public function timesheetApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'timesheet_approver_id');
    }

    public function otExecApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ot_exec_approver_id');
    }

    public function otExecFinalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ot_exec_final_approver_id');
    }

    public function otNonExecApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ot_non_exec_approver_id');
    }

    public function otNonExecFinalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ot_non_exec_final_approver_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function otForms(): HasMany
    {
        return $this->hasMany(OtForm::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManagerOrAbove(): bool
    {
        return in_array($this->role, ['manager_hod', 'ceo']);
    }

    public function canApproveTimesheetL1(): bool
    {
        return in_array($this->role, ['assistant_manager', 'manager_hod', 'ceo', 'admin']);
    }

    public function canApproveTimesheetHOD(): bool
    {
        return in_array($this->role, ['manager_hod', 'ceo', 'admin']);
    }

    public function canApproveOTFormLevel1(): bool
    {
        return in_array($this->role, ['manager_hod', 'ceo', 'admin']);
    }

    public function canApproveOTFormLevel2(): bool
    {
        return in_array($this->role, ['ceo', 'admin']);
    }
}
