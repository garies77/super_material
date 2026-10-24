<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\LoginGuard;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login panel dengan perlindungan brute force: setelah terlalu banyak kegagalan,
 * password yang benar memicu tautan login ke email, bukan sesi langsung.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $email = strtolower(trim((string) ($this->data['email'] ?? '')));

        if (LoginGuard::requiresEmailVerification($email, request())) {
            return $this->authenticateWithEmailLink($email);
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $e) {
            LoginGuard::recordFailure($email, request());
            throw $e;
        }

        if ($response) {
            LoginGuard::clearFailures($email, request());
        }

        return $response;
    }

    private function authenticateWithEmailLink(string $email): null
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $user = User::where('email', $email)->first();
        $valid = $user && Hash::check((string) $data['password'], $user->getAuthPassword())
            && $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());

        if ($valid) {
            LoginGuard::sendLink($user);
        } else {
            LoginGuard::recordFailure($email, request());
        }

        // Pesan sama untuk password benar maupun salah, agar percobaan brute force tidak tahu tebakannya tepat
        $this->data['password'] = null;
        Notification::make()->title('Verifikasi email diperlukan')
            ->body('Terlalu banyak percobaan login. Jika email dan password benar, kami telah mengirim tautan login ke email akun tersebut.')
            ->warning()->persistent()->send();

        return null;
    }
}
