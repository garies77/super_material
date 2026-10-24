<?php

namespace App\Filament\Pages\Layout;

use App\Models\CustomForm;
use App\Support\LinkTarget;
use App\Support\SiteLayout;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Footer situs: deskripsi, kolom tautan (urut dengan drag), kontak, media sosial, dan copyright. */
class FooterBuilder extends LayoutBuilderPage
{
    protected static ?string $title = 'Footer Builder';

    protected static ?string $slug = 'footer-builder';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = 3;

    public const COLUMN_TYPES = [
        'links' => 'Daftar tautan (atur sendiri)',
        'categories' => 'Kategori produk (otomatis)',
        'pages' => 'Halaman statis terbit (otomatis)',
        'contact' => 'Informasi kontak',
        'text' => 'Teks bebas',
        'form' => 'Formulir (mis. berlangganan)',
    ];

    protected function settingKey(): string
    {
        return SiteLayout::FOOTER_KEY;
    }

    protected function defaults(): array
    {
        return SiteLayout::footerDefaults();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Identitas')->columns(2)->schema([
                Toggle::make('show_logo')->label('Tampilkan logo dan nama situs')->default(true)->inline(false),
                Select::make('bottom_align')->label('Perataan baris bawah')->options(['left' => 'Kiri', 'center' => 'Tengah'])->default('center'),
                Textarea::make('description')->label('Deskripsi perusahaan')->rows(3)->maxLength(500)->columnSpanFull()
                    ->helperText('Kosongkan untuk memakai tagline dari Pengaturan situs.'),
            ]),

            Section::make('Kolom footer')->description('Seret untuk mengubah urutan kolom dan tautan.')->schema([
                Repeater::make('columns')->hiddenLabel()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('title')->label('Judul kolom')->required()->maxLength(60)->live(onBlur: true),
                            Select::make('type')->label('Isi kolom')->options(self::COLUMN_TYPES)->default('links')->required()->live(),
                            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                        ]),
                        Repeater::make('links')->label('Tautan')
                            ->schema(NavbarBuilder::itemFields())
                            ->visible(fn (Get $get) => $get('type') === 'links')
                            ->reorderableWithDragAndDrop()->collapsible()
                            ->itemLabel(fn (array $state) => $state['label'] ?? 'Tautan baru')
                            ->addActionLabel('Tambah tautan')->maxItems(20)->defaultItems(0),
                        Textarea::make('text')->label('Teks')->rows(3)->maxLength(1000)
                            ->visible(fn (Get $get) => $get('type') === 'text'),
                        Select::make('form_id')->label('Formulir')->searchable()
                            ->options(fn () => CustomForm::embedOptions())
                            ->visible(fn (Get $get) => $get('type') === 'form')
                            ->required(fn (Get $get) => $get('type') === 'form')
                            ->helperText('Formulir pendek bekerja paling baik, misalnya hanya kolom email.'),
                    ])
                    ->reorderableWithDragAndDrop()->collapsible()->cloneable()
                    ->itemLabel(fn (array $state) => ($state['title'] ?? 'Kolom baru').(($state['is_active'] ?? true) ? '' : ' (nonaktif)'))
                    ->addActionLabel('Tambah kolom')->maxItems(4)->defaultItems(0),
            ]),

            Section::make('Kontak dan media sosial')->columns(3)->schema([
                TextInput::make('address')->label('Alamat')->maxLength(255)->columnSpanFull(),
                TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                Repeater::make('socials')->label('Media sosial')->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('platform')->label('Platform')->options(SiteLayout::SOCIALS)->required(),
                            TextInput::make('url')->label('URL profil')->required()->maxLength(500)->regex(LinkTarget::SAFE_URL),
                        ]),
                    ])
                    ->reorderableWithDragAndDrop()->addActionLabel('Tambah media sosial')->maxItems(8)->defaultItems(0),
            ])->description('Tampil di kolom "Informasi kontak" bila ada; jika tidak, di bawah deskripsi. Media sosial juga dipakai di halaman artikel (tombol Instagram), jadi cukup diisi di sini.'),

            Section::make('Baris bawah')->schema([
                TextInput::make('copyright')->label('Teks copyright')->maxLength(255)
                    ->helperText('Gunakan {year} untuk tahun berjalan dan {site} untuk nama situs.'),
                Repeater::make('bottom_links')->label('Tautan (misal Kebijakan Privasi)')
                    ->schema(NavbarBuilder::itemFields())
                    ->reorderableWithDragAndDrop()->collapsible()
                    ->itemLabel(fn (array $state) => $state['label'] ?? 'Tautan baru')
                    ->addActionLabel('Tambah tautan')->maxItems(8)->defaultItems(0),
            ]),
        ]);
    }
}
