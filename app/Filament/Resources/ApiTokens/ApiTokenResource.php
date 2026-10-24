<?php

namespace App\Filament\Resources\ApiTokens;

use App\Filament\Resources\ApiTokens\Pages\ManageApiTokens;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Token API (PRD 8.7 & 8.12.3): nama, ability, kedaluwarsa, terakhir dipakai, cabut.
 * Hanya Admin yang dapat membuat dan mencabut token.
 */
class ApiTokenResource extends Resource
{
    protected static ?string $model = ApiToken::class;

    protected static ?string $modelLabel = 'Token API';

    protected static ?string $pluralModelLabel = 'Token API';

    protected static ?string $slug = 'api-tokens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tokenable_type', (new User)->getMorphClass())->with('tokenable');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('tokenable.name')->label('Pengguna')
                    ->description(fn (ApiToken $record) => User::ROLES[$record->tokenable?->role] ?? null),
                TextColumn::make('abilities')->label('Ability')->badge()
                    ->color(fn (string $state) => in_array($state, config('api.write_abilities'), true) ? 'warning' : 'gray'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (ApiToken $record) => match (true) {
                        ! $record->tokenable?->is_active => 'Pengguna nonaktif',
                        $record->isExpired() => 'Kedaluwarsa',
                        default => 'Aktif',
                    })
                    ->color(fn (string $state) => $state === 'Aktif' ? 'success' : 'danger'),
                TextColumn::make('expires_at')->label('Kedaluwarsa')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('Tidak pernah')->sortable(),
                TextColumn::make('last_used_at')->label('Terakhir dipakai')->since()->placeholder('Belum pernah')->sortable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y', 'Asia/Jakarta')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('expired')->label('Kedaluwarsa')
                    ->queries(
                        true: fn (Builder $q) => $q->where('expires_at', '<', now()),
                        false: fn (Builder $q) => $q->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now())),
                    ),
            ])
            ->recordActions([
                DeleteAction::make()->label('Cabut')->modalHeading('Cabut token API')
                    ->modalDescription('Sistem yang memakai token ini langsung kehilangan akses. Tindakan ini tidak dapat dibatalkan.')
                    ->successNotificationTitle('Token dicabut')
                    ->after(fn (ApiToken $record) => AuditLog::record(request(), 'api_token.revoked', null, ['token_id' => $record->id, 'name' => $record->name])),
            ])
            ->headerActions([
                Action::make('docs')->label('Dokumentasi API')->icon('heroicon-o-book-open')->color('gray')
                    ->url(fn () => route('api.docs.admin'))->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageApiTokens::route('/')];
    }
}
