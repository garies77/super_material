<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\LoginVerificationLink;
use App\Support\Api\ClientHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Perlindungan brute force login panel: kegagalan dihitung per email dan per alamat (hash).
 * Setelah melewati batas, password yang benar tidak langsung masuk; tautan login sekali pakai
 * dikirim ke email pemilik akun.
 */
class LoginGuard
{
    public static function requiresEmailVerification(string $email, Request $request): bool
    {
        $c = config('security.login');

        return (int) Cache::get(self::emailKey($email), 0) >= $c['max_failures_per_email']
            || (int) Cache::get(self::clientKey($request), 0) >= $c['max_failures_per_client'];
    }

    public static function recordFailure(string $email, Request $request): void
    {
        $ttl = now()->addMinutes(config('security.login.failure_window_minutes'));
        $wasProtected = self::requiresEmailVerification($email, $request);

        foreach ([self::emailKey($email), self::clientKey($request)] as $key) {
            Cache::add($key, 0, $ttl);
            Cache::increment($key);
        }

        // Dicatat sekali saat batas pertama kali terlampaui, bukan setiap percobaan
        if (! $wasProtected && self::requiresEmailVerification($email, $request)) {
            AuditLog::record($request, 'login.brute_force_detected', User::where('email', $email)->first(), [
                'email_hash' => hash('sha256', $email),
            ], 'failed');
        }
    }

    public static function clearFailures(string $email, Request $request): void
    {
        Cache::forget(self::emailKey($email));
        Cache::forget(self::clientKey($request));
    }

    public static function sendLink(User $user): void
    {
        $token = Str::random(64);
        $minutes = config('security.login.link_ttl_minutes');
        Cache::put(self::linkKey($token), $user->id, now()->addMinutes($minutes));

        $user->notify(new LoginVerificationLink(
            URL::temporarySignedRoute('login.verify', now()->addMinutes($minutes), ['token' => $token]),
            $minutes,
        ));
    }

    /** Tautan hanya bisa dipakai sekali: token langsung dihapus saat dibaca. */
    public static function consumeLink(string $token): ?User
    {
        $userId = Cache::pull(self::linkKey($token));

        return $userId ? User::find($userId) : null;
    }

    private static function emailKey(string $email): string
    {
        return 'login-fail:email:'.hash('sha256', strtolower(trim($email)));
    }

    private static function clientKey(Request $request): string
    {
        return 'login-fail:client:'.ClientHash::forRequest($request);
    }

    private static function linkKey(string $token): string
    {
        return 'login-link:'.hash('sha256', $token);
    }
}
