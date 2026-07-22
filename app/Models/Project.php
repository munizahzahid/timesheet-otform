<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    protected $table = 'pm_projects';

    protected $fillable = [
        'old_project_code_id',
        'desknet_id',
        'project_code',
        'project_name',
        'description',
        'status',
        'is_active',
        'start_date_plan',
        'end_date_plan',
        'start_date_actual',
        'end_date_actual',
        'start_date_revise',
        'end_date_revise',
        'overall_plan_progress',
        'overall_actual_progress',
        'created_by',
        'project_manager',
        'project_manager_staff_id',
        'project_manager_department',
        'deskman_1',
        'deskman_1_staff_id',
        'deskman_1_department',
        'deskman_2',
        'deskman_2_staff_id',
        'deskman_2_department',
        'po_no',
        'client',
        'attn',
        'full_address',
        'tin',
        'identification_no',
        'contact_no',
        'email',
        'exemption_cert_no',
        'term_1',
        'term_2',
        'term_3',
        'term_4',
        'term_5',
        'project_value',
        'purchasing_budget_100',
        'purchasing_budget_95',
        'year',
        'attachment_po_customer',
        'other_attachments',
        'project_schedule_status',
        'last_synced_at',
        'date_time_added',
        'added_by',
        'date_time_updated',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date_plan' => 'date',
            'end_date_plan' => 'date',
            'start_date_actual' => 'date',
            'end_date_actual' => 'date',
            'start_date_revise' => 'date',
            'end_date_revise' => 'date',
            'project_value' => 'decimal:2',
            'purchasing_budget_100' => 'decimal:2',
            'purchasing_budget_95' => 'decimal:2',
            'attachment_po_customer' => 'array',
            'other_attachments' => 'array',
            'last_synced_at' => 'datetime',
            'date_time_added' => 'datetime',
            'date_time_updated' => 'datetime',
        ];
    }

    public function getCodeAttribute(): ?string
    {
        return $this->project_code;
    }

    public function getNameAttribute(): ?string
    {
        return $this->project_name;
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('phase_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectProgressLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
