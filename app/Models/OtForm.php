<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OtForm extends Model
{
    protected $fillable = [
        'user_id', 'month', 'year', 'form_type', 'company_name',
        'section_line', 'status', 'plan_submitted_at',
        'actual_submitted_at', 'total_ot_hours',
    ];

    protected function casts(): array
    {
        return [
            'plan_submitted_at' => 'datetime',
            'actual_submitted_at' => 'datetime',
            'total_ot_hours' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(OtFormEntry::class);
    }

    public function approvalLogs()
    {
        return ApprovalLog::where('approvable_type', 'ot_form')
            ->where('approvable_id', $this->id)
            ->with('approver')
            ->get();
    }

    public function isExecutive(): bool
    {
        return $this->form_type === 'executive';
    }

    public function isNonExecutive(): bool
    {
        return $this->form_type === 'non_executive';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function isPrintable(): bool
    {
        return in_array($this->status, ['approved']);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending_manager' => 'Pending Manager/Asst Manager Approval',
            'pending_gm' => 'Pending General Manager Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function getFormTypeLabelAttribute(): string
    {
        return $this->form_type === 'executive' ? 'Executive (OCF)' : 'Non-Executive (BKLM)';
    }
}
