<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

/**
 * Setiap login berhasil (form panel, tautan email, atau cookie "ingat saya"): simpan waktu & IP terakhir di User
 * untuk tampilan cepat, dan catat ke AuditLog (append-only) sebagai riwayat permanen.
 */
class RecordSuccessfulLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $request = request();
        $ip = $request->ip();

        // forceFill + saveQuietly: kolom ini sengaja tidak fillable dan tidak memicu audit perubahan akses
        $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();

        // IP mentah dicatat khusus untuk login staf agar login mencurigakan bisa dikenali (pengecualian dari ip_hash biasa)
        AuditLog::record($request, 'auth.login', $event->user, array_filter([
            'ip' => $ip,
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'via' => $request->routeIs('login.verify') ? 'email_link' : 'form',
            'remember' => $event->remember,
            'guard' => $event->guard,
        ], fn ($v) => $v !== null && $v !== ''), actor: $event->user);
    }
}
