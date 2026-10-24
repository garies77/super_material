<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\SudoMode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $isStaff = fn (Get $get) => in_array($get('role'), ['editor', 'author'], true);
        // Sudo mode (PRD Keamanan 2.2): menaikkan peran ke Admin/Editor, atau memberi izin Custom HTML
        $needsSudo = fn (Get $get, ?User $record) => (in_array($get('role'), ['admin', 'editor'], true) && $get('role') !== $record?->role)
            || ($get('can_manage_custom_html') && $isStaff($get) && ! $record?->can_manage_custom_html);

        return $schema->columns(1)->components([
            Section::make('Data akun')->icon(Heroicon::OutlinedUserCircle)->columns(2)->schema([
                TextInput::make('name')->label('Nama lengkap')->required()->maxLength(255),
                TextInput::make('email')->label('Alamat email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('password')->label('Password login pengguna')->password()->revealable()->columnSpanFull()
                    ->autocomplete('new-password')
                    ->required(fn (string $operation) => $operation === 'create')
                    ->rule(Password::default())
                    ->helperText(fn (string $operation) => 'Password yang dipakai pengguna ini untuk masuk. Minimal 9 karakter, berisi huruf, angka, dan simbol.'
                        .($operation === 'edit' ? ' Kosongkan bila tidak ingin mengubahnya.' : ''))
                    ->dehydrated(fn ($state) => filled($state)),
                Select::make('role')->label('Peran')->options(User::ROLES)->required()->default('author')->live()
                    ->selectablePlaceholder(false),
                Toggle::make('is_active')->label('Akun aktif')->default(true)->inline(false)
                    ->helperText('Nonaktif: tidak bisa masuk dan token API-nya tidak berlaku.'),
            ]),

            // Selalu tampil agar susunan form tidak melompat; isinya berganti sesuai peran
            Section::make('Hak akses & fitur sensitif')->icon(Heroicon::OutlinedShieldCheck)->schema([
                Callout::make('Admin otomatis memiliki akses penuh')
                    ->description('Termasuk Custom HTML. Tidak ada izin tambahan yang perlu diatur.')
                    ->info()->visible(fn (Get $get) => $get('role') === 'admin'),
                // Kode pengguna ini akan berjalan tanpa sandbox di halaman publik
                Toggle::make('can_manage_custom_html')->label('Boleh mengelola Custom HTML')->live()
                    ->visible($isStaff)
                    ->disabled(fn () => ! auth()->user()?->isAdmin())
                    ->helperText('HTML, CSS, dan JavaScript dari pengguna ini dijalankan langsung di halaman publik. Berikan hanya kepada orang yang Anda percaya penuh.'),
            ]),

            // Di bagian paling bawah, hanya saat dibutuhkan: tidak menggeser field lain
            Callout::make('Verifikasi keamanan')
                ->description('Tindakan ini memberikan wewenang tingkat lanjut. Demi alasan keamanan, konfirmasikan identitas Anda dengan memasukkan password akun Admin Anda yang sedang aktif.')
                ->warning()->icon(Heroicon::OutlinedLockClosed)->extraAttributes(['class' => 'sm-sudo-callout'])
                ->visible($needsSudo)
                ->footer([
                    TextInput::make('sudo_password')->label('Password Admin Anda saat ini')->password()->revealable()
                        ->placeholder('Masukkan password akun Anda untuk mengonfirmasi…')
                        ->autocomplete('current-password')
                        ->dehydrated(false)->required()
                        ->visible($needsSudo)
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            try {
                                SudoMode::confirm($value, 'user.role_change', $attribute);
                            } catch (ValidationException $e) {
                                $fail(collect($e->errors())->flatten()->first());
                            }
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->label('Peran')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                IconColumn::make('custom_html_access')->label('Custom HTML')->boolean()
                    ->state(fn (User $r) => $r->canManageCustomHtml())
                    ->tooltip(fn (User $r) => $r->isAdmin() ? 'Admin selalu punya izin' : null),
                TextColumn::make('last_login_at')->label('Login Terakhir')->since('Asia/Jakarta')->sortable()
                    ->dateTimeTooltip('d M Y H:i', 'Asia/Jakarta')
                    ->description(fn (User $r) => $r->last_login_ip)
                    ->placeholder('Belum pernah'),
            ])
            ->recordActions([
                Action::make('loginHistory')->label('Riwayat login')->icon('heroicon-o-clock')->color('gray')
                    ->modalHeading(fn (User $r) => "Riwayat login: {$r->name}")
                    ->modalContent(fn (User $r) => view('filament.users.login-history', [
                        'logs' => AuditLog::where('action', 'auth.login')->where('user_id', $r->id)->latest('created_at')->limit(50)->get(),
                    ]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Tutup'),
                EditAction::make()->modalWidth(Width::TwoExtraLarge),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
