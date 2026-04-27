<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesknetSyncLog extends Model
{
    protected $table = 'desknet_sync_log';

    public $timestamps = false;

    protected $fillable = [
        'sync_type', 'trigger_type', 'triggered_by', 'status',
        'records_created', 'records_updated', 'records_deactivated',
        'error_message', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
