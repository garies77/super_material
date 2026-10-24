<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\SudoMode;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ManageApiTokens extends ManageRecords
{
    protected static string $resource = ApiTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Buat token')->icon('heroicon-o-plus')
                ->modalHeading('Buat token API')
                ->modalDescription('Token hanya ditampilkan sekali setelah dibuat. Hak efektif adalah irisan ability token dan peran pengguna.')
                ->schema([
                    Select::make('user_id')->label('Pengguna')->required()->live()
                        ->options(fn () => User::where('is_active', true)->orderBy('name')->get()
                            ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email}) · ".(User::ROLES[$u->role] ?? $u->role)])),
                    TextInput::make('name')->label('Nama token')->required()->maxLength(100)
                        ->placeholder('cth: n8n impor produk'),
                    CheckboxList::make('abilities')->label('Hak akses token')->required()->live()
                        ->options(fn (Get $get) => collect(config('api.abilities'))
                            ->only(User::find($get('user_id'))?->apiAbilities() ?? array_keys(config('api.abilities')))
                            ->mapWithKeys(fn ($desc, $key) => [$key => "{$key} — {$desc}"])->all())
                        ->helperText('Hanya ability yang diizinkan peran pengguna yang dapat dipilih. Token tidak dapat mengubah branding, pengguna, peran, redirect, atau iklan.'),
                    DatePicker::make('expires_at')->label('Kedaluwarsa')->native(false)
                        ->helperText('Kosongkan agar token tidak pernah kedaluwarsa.')
                        ->minDate(now()->addDay()->startOfDay())->maxDate(now()->addYear()),
                    // Sama dengan form Pengguna: verifikasi Admin di kotak terpisah paling bawah, hanya saat ada hak tulis
                    Callout::make('Verifikasi keamanan')
                        ->description('Token dengan hak tulis dapat mengubah data situs. Konfirmasikan identitas Anda dengan password akun Admin Anda yang sedang aktif.')
                        ->warning()->icon(Heroicon::OutlinedLockClosed)->extraAttributes(['class' => 'sm-sudo-callout'])
                        ->visible(fn (Get $get) => (bool) array_intersect((array) $get('abilities'), config('api.write_abilities')))
                        ->footer([
                            TextInput::make('password')->label('Password Admin Anda saat ini')->password()->revealable()
                                ->placeholder('Masukkan password akun Anda untuk mengonfirmasi…')
                                ->autocomplete('current-password')
                                ->visible(fn (Get $get) => (bool) array_intersect((array) $get('abilities'), config('api.write_abilities')))
                                ->required(fn (Get $get) => (bool) array_intersect((array) $get('abilities'), config('api.write_abilities'))),
                        ]),
                ])
                ->action(fn (array $data) => $this->createToken($data)),
        ];
    }

    private function createToken(array $data): void
    {
        $user = User::where('is_active', true)->findOrFail($data['user_id']);
        $abilities = array_values(array_intersect((array) $data['abilities'], $user->apiAbilities()));

        if (! $abilities) {
            throw ValidationException::withMessages(['abilities' => 'Peran pengguna tidak mengizinkan ability yang dipilih.']);
        }

        // Konfirmasi password diperiksa ulang di server, tidak bergantung pada visibilitas kolom
        if (array_intersect($abilities, config('api.write_abilities'))) {
            SudoMode::confirm($data['password'] ?? null, 'api_token.create', 'mountedActions.0.data.password');
        }

        // Database hanya menyimpan hash SHA-256 (Sanctum); teks token tampil sekali di notifikasi ini
        $token = $user->createToken($data['name'], $abilities, filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at'], 'Asia/Jakarta')->endOfDay() : null);

        AuditLog::record(request(), 'api_token.created', $token->accessToken, [
            'name' => $data['name'],
            'user_id' => $user->id,
            'abilities' => $abilities,
            'expires_at' => $token->accessToken->expires_at?->toAtomString(),
        ]);

        Notification::make()
            ->title('Token dibuat. Salin sekarang, token tidak akan ditampilkan lagi.')
            ->body($token->plainTextToken)
            ->actions([
                Action::make('copy')->label('Salin token')->icon('heroicon-o-clipboard')
                    ->alpineClickHandler('window.navigator.clipboard.writeText('.json_encode($token->plainTextToken).'); $tooltip(\'Disalin\')'),
            ])
            ->success()
            ->persistent()
            ->send();
    }
}
