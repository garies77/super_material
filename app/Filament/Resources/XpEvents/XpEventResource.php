<?php

namespace App\Filament\Resources\XpEvents;

use App\Filament\Resources\XpEvents\Pages\ListXpEvents;
use App\Models\AuditLog;
use App\Models\XpEvent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Riwayat XP gamifikasi untuk Admin: memantau pemberian poin dan membatalkannya bila konten ternyata
 * tidak layak (mis. disetujui terlalu cepat). Pembatalan tidak menghapus riwayat.
 */
class XpEventResource extends Resource
{
    protected static ?string $model = XpEvent::class;

    protected static ?string $modelLabel = 'XP';

    protected static ?string $pluralModelLabel = 'Riwayat XP';

    protected static ?string $slug = 'riwayat-xp';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'revoker']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Pengguna')->searchable(),
                TextColumn::make('points')->label('XP')->numeric()->sortable()->prefix('+')
                    ->color(fn (XpEvent $record) => $record->isRevoked() ? 'gray' : 'success'),
                TextColumn::make('reason')->label('Alasan')->limit(60)->searchable()
                    ->description(fn (XpEvent $record) => collect($record->meta['breakdown'] ?? [])
                        ->map(fn (int $points, string $part) => "{$part} +{$points}")->implode(' · ')),
                TextColumn::make('revoked_at')->label('Status')->badge()
                    ->state(fn (XpEvent $record) => $record->isRevoked() ? 'Dibatalkan' : 'Aktif')
                    ->color(fn (string $state) => $state === 'Aktif' ? 'success' : 'danger')
                    ->tooltip(fn (XpEvent $record) => $record->isRevoked() ? 'Oleh '.$record->revoker?->name.', '.$record->revoked_at->format('d M Y H:i') : null),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Pengguna')->relationship('user', 'name')->searchable()->preload(),
                TernaryFilter::make('revoked_at')->label('Dibatalkan')->nullable(),
            ])
            ->recordActions([
                Action::make('revoke')->label('Batalkan')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (XpEvent $record) => ! $record->isRevoked())
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan XP ini?')
                    ->modalDescription('Poin tidak lagi dihitung untuk level dan lencana. Riwayatnya tetap tersimpan dan bisa dipulihkan.')
                    ->action(function (XpEvent $record): void {
                        $record->update(['revoked_at' => now(), 'revoked_by' => auth()->id()]);
                        AuditLog::record(request(), 'xp.revoked', $record, ['user_id' => $record->user_id, 'points' => $record->points]);
                    }),
                Action::make('restore')->label('Pulihkan')->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                    ->visible(fn (XpEvent $record) => $record->isRevoked())
                    ->action(function (XpEvent $record): void {
                        $record->update(['revoked_at' => null, 'revoked_by' => null]);
                        AuditLog::record(request(), 'xp.restored', $record, ['user_id' => $record->user_id, 'points' => $record->points]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListXpEvents::route('/')];
    }
}
