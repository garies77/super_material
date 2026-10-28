<?php

namespace App\Models;

use Database\Factories\XpEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Satu pemberian XP gamifikasi (lihat App\Support\Gamification). */
class XpEvent extends Model
{
    /** @use HasFactory<XpEventFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'meta' => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at');
    }
}
