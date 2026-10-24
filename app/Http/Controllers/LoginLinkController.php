<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\LoginGuard;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/** Membuka sesi dari tautan login email (bertanda tangan, berlaku singkat, sekali pakai). */
class LoginLinkController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $user = LoginGuard::consumeLink($token);
        $panel = Filament::getPanel('admin');

        if (! $user || ! $user->canAccessPanel($panel)) {
            return redirect()->to($panel->getLoginUrl())->with('status', 'Tautan login tidak valid atau sudah dipakai.');
        }

        auth()->guard($panel->getAuthGuard())->login($user);
        $request->session()->regenerate();
        LoginGuard::clearFailures($user->email, $request);
        AuditLog::record($request, 'login.email_link_used', $user);

        return redirect()->to($panel->getUrl());
    }
}
