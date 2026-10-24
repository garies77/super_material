<?php

namespace App\Http\Resources\Api;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Dasar resource API v1: waktu ISO 8601 zona situs, relasi opsional lewat include (PRD 8.12.4).
 */
abstract class ApiResource extends JsonResource
{
    public const TIMEZONE = 'Asia/Jakarta';

    public static function iso(?DateTimeInterface $date): ?string
    {
        if (! $date) {
            return null;
        }

        return Carbon::instance($date)->setTimezone(self::TIMEZONE)->toIso8601String();
    }

    /** Relasi yang diminta lewat include=a,b dan diizinkan controller. */
    protected function included(Request $request, string $relation): bool
    {
        return in_array($relation, (array) $request->attributes->get('api_includes', []), true);
    }

    public static function collection($resource): ApiCollection
    {
        return new ApiCollection($resource, static::class);
    }
}
