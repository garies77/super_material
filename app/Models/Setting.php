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

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key);
        if ($val === null) {
            return $default;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function imageUrl(string $key, ?string $default = null): ?string
    {
        $val = static::get($key);
        if (! $val) {
            return $default;
        }

        if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) {
            return $val;
        }

        return asset('storage/'.ltrim($val, '/'));
    }

    public static function put(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value)) {
            if (count($value) === 1 && isset($value[0]) && is_string($value[0])) {
                $value = $value[0];
            } else {
                $value = json_encode($value);
            }
        } elseif ($value !== null) {
            $value = (string) $value;
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}

