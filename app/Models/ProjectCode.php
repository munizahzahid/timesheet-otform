<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCode extends Model
{
    protected $fillable = [
        'desknet_id', 'code', 'name', 'client', 'year', 'po_no',
        'project_manager', 'start_date', 'delivery_date',
        'project_value', 'project_schedule_status',
        'is_active', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'delivery_date' => 'date',
            'project_value' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }
}
