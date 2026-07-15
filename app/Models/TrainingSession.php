<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'name',
        'venue',
        'training_date',
        'time_in',
        'time_out',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'time_in' => 'datetime:H:i',
            'time_out' => 'datetime:H:i',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class);
    }

    public function attendedBy(User $user): bool
    {
        return $this->attendances()->where('user_id', $user->id)->exists();
    }
}
