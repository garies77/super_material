<?php

namespace App\Models;

use App\Support\Api\ClientHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

/** Jejak perubahan: pelaku, token, waktu, ringkasan perubahan. Tanpa IP mentah (PRD 8.12.6). */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    // Append-only (PRD Keamanan 6.1): rekaman tidak dapat diubah atau dihapus lewat model
    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(Request $request, string $action, ?Model $subject = null, array $changes = [], string $status = 'success', ?User $actor = null): self
    {
        $user = $actor ?? $request->user(); // actor eksplisit: event Login terjadi sebelum pengguna terpasang di request
        $token = $user?->currentAccessToken();

        return static::create([
            'user_id' => $user?->id,
            'token_id' => $token instanceof ApiToken ? $token->id : null,
            'source' => $request->is('api/*') ? 'api' : 'admin',
            'action' => $action,
            'status' => $status,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip_hash' => ClientHash::forRequest($request),
            'created_at' => now(),
        ]);
    }
}
