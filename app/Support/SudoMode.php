<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Konfirmasi password ulang untuk aksi berisiko (PRD Keamanan 2.2). Berlaku untuk satu permintaan saja:
 * password harus dikirim bersama setiap aksi. 5 kegagalan dalam 10 menit mengunci konfirmasi 15 menit.
 */
class SudoMode
{
    public const MAX_ATTEMPTS = 5;

    public const ATTEMPT_WINDOW = 600;

    public const LOCK_SECONDS = 900;

    public static function confirm(?string $password, string $action, string $field = 'password'): void
    {
        $user = auth()->user();
        $lockKey = 'sudo-lock:'.$user->id;
        $attemptKey = 'sudo-attempts:'.$user->id;

        if (Cache::has($lockKey)) {
            throw ValidationException::withMessages([$field => 'Terlalu banyak percobaan password. Coba lagi dalam 15 menit.']);
        }

        if (Hash::check((string) $password, $user->getAuthPassword())) {
            RateLimiter::clear($attemptKey);

            return;
        }

        RateLimiter::hit($attemptKey, self::ATTEMPT_WINDOW);
        $locked = RateLimiter::attempts($attemptKey) >= self::MAX_ATTEMPTS;
        if ($locked) {
            Cache::put($lockKey, true, self::LOCK_SECONDS);
            RateLimiter::clear($attemptKey);
        }

        AuditLog::record(request(), 'sudo.failed', null, ['action' => $action, 'locked' => $locked], 'failed');

        throw ValidationException::withMessages([$field => $locked
            ? 'Terlalu banyak percobaan password. Konfirmasi dikunci 15 menit.'
            : 'Password tidak sesuai.']);
    }
}
