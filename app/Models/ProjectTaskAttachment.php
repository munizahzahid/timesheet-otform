<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskAttachment extends Model
{
    protected $fillable = [
        'project_task_id',
        'user_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
