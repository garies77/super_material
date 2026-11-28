<?php

namespace App\Filament\Resources\Webhooks;

use App\Filament\Resources\Webhooks\Pages\ManageWebhooks;
use App\Jobs\SendWebhook;
use App\Models\Setting;
use App\Models\Webhook;
use App\Support\WebhookEvents;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

/**
 * Webhook keluar ke n8n, Zapier, atau server sendiri. Setiap kiriman ditandatangani HMAC-SHA256 dengan secret webhook.
 */
class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static ?string $modelLabel = 'Webhook';

    protected static ?string $pluralModelLabel = 'Webhook';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255)->placeholder('mis. n8n posting Instagram'),
            TextInput::make('url')->label('URL tujuan')->required()->url()->maxLength(1000)
                ->placeholder('https://n8n.contoh.com/webhook/…')
                ->helperText('Alamat yang menerima permintaan POST berisi JSON setiap kali event terjadi.'),
            CheckboxList::make('events')->label('Kirim saat')->options(Webhook::EVENTS)->required()
                ->default(array_keys(Webhook::EVENTS))->columnSpanFull(),
            TextInput::make('secret')->label('Secret penandatangan')->required()->readOnly()
                ->default(fn () => Webhook::generateSecret())
                ->helperText('Simpan di penerima untuk memverifikasi header X-Supermaterial-Signature = "sha256=" + HMAC-SHA256("{X-Supermaterial-Timestamp}.{body}", secret).')
                ->suffixAction(Action::make('regenerateSecret')->icon(Heroicon::OutlinedArrowPath)->tooltip('Buat secret baru')
                    ->requiresConfirmation()->modalDescription('Penerima yang masih memakai secret lama akan menolak kiriman berikutnya.')
                    ->action(fn (Set $set) => $set('secret', Webhook::generateSecret())))
                ->columnSpanFull(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()
                    ->description(fn (Webhook $record) => $record->url)->wrap(),
                TextColumn::make('events')->label('Event')->badge()
                    ->formatStateUsing(fn (string $state) => Webhook::EVENTS[$state] ?? $state),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_status')->label('Kiriman terakhir')->badge()->placeholder('Belum pernah')
                    ->formatStateUsing(fn ($state, Webhook $record) => ($state ?: 'Gagal').' · '.$record->last_sent_at?->diffForHumans())
                    ->color(fn ($state) => $state >= 200 && $state < 300 ? 'success' : 'danger')
                    ->tooltip(fn (Webhook $record) => $record->last_error),
            ])
            ->recordActions([
                Action::make('sendTest')->label('Kirim tes')->icon(Heroicon::OutlinedPaperAirplane)
                    ->action(fn (Webhook $record) => self::sendTest($record)),
                EditAction::make(),
                DeleteAction::make()->iconButton()->tooltip('Hapus'),
            ])
            ->emptyStateHeading('Belum ada webhook')
            ->emptyStateDescription('Tambahkan webhook untuk memicu otomatisasi (mis. posting media sosial) saat produk atau artikel terbit.');
    }

    /** Kirim event webhook.ping langsung (tanpa antrean) agar admin segera tahu URL dan secret-nya benar. */
    public static function sendTest(Webhook $webhook): void
    {
        try {
            (new SendWebhook($webhook->id, WebhookEvents::payload(Webhook::PING_EVENT, [
                'message' => 'Tes webhook dari '.Setting::brandName(),
                'url' => url('/'),
            ])))->handle();

            Notification::make()->title('Tes terkirim')->body('Penerima merespons HTTP '.$webhook->refresh()->last_status.'.')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Tes gagal')->body($webhook->refresh()->last_error ?: $exception->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageWebhooks::route('/')];
    }
}
