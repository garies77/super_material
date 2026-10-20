<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SiteSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.site-settings';

    protected static ?string $title = 'Pengaturan situs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(Setting::all_());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Umum')->columns(2)->schema([
                TextInput::make('site_tagline')->label('Tagline')->columnSpanFull(),
                Textarea::make('site_description')->label('Deskripsi situs (beranda)')->rows(3)->columnSpanFull(),
                TextInput::make('whatsapp_number')->label('Nomor WhatsApp default')->helperText('Format 08xx atau 628xx.'),
                TextInput::make('contact_email')->label('Email kontak')->email(),
                TextInput::make('contact_address')->label('Alamat')->columnSpanFull(),
            ]),
            Section::make('Organisasi (JSON-LD)')->columns(2)->schema([
                TextInput::make('org_name')->label('Nama organisasi'),
                TextInput::make('org_logo_url')->label('URL logo')->url(),
            ]),
            Section::make('Pelacakan')->columns(2)->schema([
                TextInput::make('ga4_id')->label('Google Analytics 4 ID')->placeholder('G-XXXXXXXXXX'),
                TextInput::make('meta_pixel_id')->label('Meta Pixel ID (opsional)'),
            ]),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value);
        }

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
