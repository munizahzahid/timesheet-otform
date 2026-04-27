<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    protected $fillable = [
        'user_id', 'month', 'year', 'status', 'current_level', 'submitted_at',
        'staff_signature', 'l1_signature', 'l2_signature', 'l3_signature',
        'staff_signed_at', 'l1_signed_at', 'l2_signed_at', 'l3_signed_at',
        'rejection_remarks',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'staff_signed_at' => 'datetime',
            'l1_signed_at' => 'datetime',
            'l2_signed_at' => 'datetime',
            'l3_signed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dayMetadata(): HasMany
    {
        return $this->hasMany(TimesheetDayMetadata::class);
    }

    public function adminHours(): HasMany
    {
        return $this->hasMany(TimesheetAdminHour::class);
    }

    public function projectRows(): HasMany
    {
        return $this->hasMany(TimesheetProjectRow::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(TimesheetApprovalLog::class);
    }
}
