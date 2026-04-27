<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    protected $table = 'system_config';

    public $timestamps = false;

    protected $fillable = ['key', 'value', 'description', 'updated_at'];

    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
        ];
    }

    public static function getValue(string $key, $default = null): ?string
    {
        $config = static::where('key', $key)->first();
        return $config ? $config->value : $default;
    }

    public static function setValue(string $key, string $value): void
    {
        static::where('key', $key)->update([
            'value' => $value,
            'updated_at' => now(),
        ]);
    }
}
