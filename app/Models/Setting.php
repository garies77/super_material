<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function all_(): array
    {
        return Cache::remember('settings.all', 3600, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::all_()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
