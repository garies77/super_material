<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** Hasil permintaan POST admin yang disimpan 24 jam agar pengulangan tidak membuat data ganda (PRD 8.12.4). */
class IdempotencyKey extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'status_code' => 'integer'];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subHours(config('api.idempotency.ttl_hours', 24)));
    }
}
