<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\LoginGuard;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login panel dengan perlindungan brute force: setelah terlalu banyak kegagalan,
 * password yang benar memicu tautan login ke email, bukan sesi langsung.
 */
class Login extends BaseLogin
{
    /** Layout dua kolom bermerek (panel brand + form); lihat resources/views/filament/layouts/auth.blade.php. */
    protected static string $layout = 'filament.layouts.auth';

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getEmailFormComponent();

        return $field->prefixIcon(Heroicon::OutlinedEnvelope)->placeholder('nama@email.com');
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getPasswordFormComponent();

        return $field->prefixIcon(Heroicon::OutlinedLockClosed);
    }

    /** Sambutan bermerek; langkah verifikasi dua faktor tetap memakai teks bawaan Filament. */
    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication) ? parent::getHeading() : 'Selamat datang kembali';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : 'Masuk untuk mengelola katalog, artikel, dan kampanye '.filament()->getBrandName().'.';
    }

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
