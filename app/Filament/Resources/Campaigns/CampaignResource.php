<?php

namespace App\Filament\Resources\Campaigns;

use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Support\DescriptiveFileName;
use App\Models\Campaign;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Banner kampanye modal: materi, jadwal, penargetan, pemicu, dan frekuensi (PRD 8.9). */
class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $modelLabel = 'Banner kampanye';

    protected static ?string $pluralModelLabel = 'Banner kampanye';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        $is = fn (string $field, string ...$values) => fn (Get $get) => in_array($get($field), $values, true);

        return $schema->components([
            Section::make('Kampanye')->columns(3)->schema([
                TextInput::make('name')->label('Nama kampanye')->required()->maxLength(255)
                    ->helperText('Juga dipakai sebagai utm_campaign pada tautan keluar.'),
                Select::make('status')->label('Status')->options(Campaign::STATUSES)->default('draft')->required()
                    ->helperText('Aktif = tampil otomatis sesuai jadwal.'),
                TextInput::make('priority')->label('Prioritas')->numeric()->default(0)->required()
                    ->helperText('Hanya satu modal tampil; prioritas tertinggi lebih dulu.'),
            ]),

            Section::make('Materi')->columns(2)->schema([
                Select::make('layout')->label('Tata letak')->options(Campaign::LAYOUTS)->default('image_text')->required()->live(),
                TextInput::make('image_alt')->label('Teks alternatif gambar')->required()->maxLength(255),
                FileUpload::make('image')->label('Gambar desktop')->image()->required()
                    ->disk('public')->directory('kampanye')->visibility('public')
                    ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'kampanye'))
                    ->helperText('Disarankan 1200×750 (16:10).'),
                FileUpload::make('image_mobile')->label('Gambar mobile (opsional)')->image()
                    ->disk('public')->directory('kampanye')->visibility('public')
                    ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'kampanye', 'mobile'))
                    ->helperText('Dipakai pada layar < 640 px, disarankan 800×1000 (4:5).'),
                TextInput::make('title')->label('Judul')->maxLength(255)
                    ->helperText('Dibacakan pembaca layar sebagai judul dialog.')
                    ->required($is('layout', 'image_text')),
                TextInput::make('cta_label')->label('Teks tombol (CTA)')->maxLength(60)->placeholder('Lihat promo')
                    ->visible($is('layout', 'image_text')),
                Textarea::make('body')->label('Teks singkat')->rows(3)->maxLength(500)->columnSpanFull()
                    ->visible($is('layout', 'image_text')),
                TextInput::make('target_url')->label('Tautan tujuan')->required()->maxLength(2000)->columnSpanFull()
                    ->rule('regex:#^(https?://[^\s]+|/[^/\s]*[^\s]*)$#i')
                    ->helperText('Path /produk/…, /kategori/…, /artikel/… untuk halaman sendiri; URL penuh (https://…, https://wa.me/…) untuk mitra atau WhatsApp — otomatis rel="sponsored nofollow", tab baru, dan UTM.'),
            ]),

            Section::make('Jadwal & penargetan')->columns(2)->schema([
                DateTimePicker::make('starts_at')->label('Mulai (WIB)')->seconds(false)->timezone('Asia/Jakarta'),
                DateTimePicker::make('ends_at')->label('Selesai (WIB)')->seconds(false)->timezone('Asia/Jakarta')->after('starts_at'),
                Select::make('targeting')->label('Tampil pada')->options(Campaign::TARGETING)->default('all')->required()->live(),
                Select::make('device')->label('Perangkat')->options(Campaign::DEVICES)->default('all')->required(),
                Select::make('target_category_ids')->label('Kategori')->multiple()->searchable()
                    ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Termasuk subkategori dan halaman produk di dalamnya.')
                    ->required($is('targeting', 'categories'))->visible($is('targeting', 'categories'))->columnSpanFull(),
            ]),

            Section::make('Perilaku tampil')->columns(2)
                ->description('Modal tidak pernah muncul saat halaman dimuat, dan tidak tampil di /go/, /ad/, pencarian, atau halaman kebijakan.')
                ->schema([
                    Select::make('trigger')->label('Pemicu')->options(Campaign::TRIGGERS)->default('delay')->required()->live(),
                    TextInput::make('delay_seconds')->label('Jeda (detik)')->numeric()->minValue(3)->maxValue(120)->default(5)->required()
                        ->visible($is('trigger', 'delay')),
                    TextInput::make('scroll_percent')->label('Gulir (persen halaman)')->numeric()->minValue(10)->maxValue(90)->default(30)->required()
                        ->visible($is('trigger', 'scroll')),
                    Select::make('frequency')->label('Frekuensi')->options(Campaign::FREQUENCIES)->default('days')->required()->live(),
                    TextInput::make('frequency_days')->label('Tampil lagi setelah (hari)')->numeric()->minValue(1)->maxValue(365)->default(7)->required()
                        ->visible($is('frequency', 'days')),
                    Toggle::make('allow_never_again')->label('Tampilkan opsi "Jangan tampilkan lagi"'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $count = fn (string $type) => fn ($e) => $e->where('type', $type)->where('is_bot', false);

        return $table
            ->defaultSort('priority', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'events as impressions_count' => $count('impression'),
                'events as clicks_count' => $count('click'),
                'events as closes_count' => $count('close'),
            ]))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->limit(40),
                TextColumn::make('display_status')->label('Status')->badge()
                    ->state(fn (Campaign $r) => $r->displayStatus())
                    ->color(fn (string $state) => match ($state) {
                        'Aktif' => 'success', 'Terjadwal' => 'info', 'Draft' => 'gray', default => 'warning',
                    }),
                TextColumn::make('priority')->label('Prioritas')->sortable(),
                TextColumn::make('targeting')->label('Tampil pada')->formatStateUsing(fn ($state) => Campaign::TARGETING[$state] ?? $state)->limit(24),
                TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('—'),
                TextColumn::make('ends_at')->label('Selesai')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('—'),
                TextColumn::make('impressions_count')->label('Impresi')->numeric()->sortable(),
                TextColumn::make('clicks_count')->label('Klik')->numeric()->sortable(),
                TextColumn::make('closes_count')->label('Tutup')->numeric()->sortable(),
                TextColumn::make('ctr')->label('CTR')->state(fn (Campaign $r) => $r->impressions_count
                    ? number_format($r->clicks_count / $r->impressions_count * 100, 2, ',', '.').'%' : '—'),
            ])
            ->filters([SelectFilter::make('status')->options(Campaign::STATUSES)])
            ->recordActions([self::previewAction(), self::duplicateAction(), EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function previewAction(): Action
    {
        return Action::make('preview')->label('Pratinjau')->icon('heroicon-o-eye')
            ->url(fn (Campaign $record) => route('campaign.preview', $record))->openUrlInNewTab();
    }

    /** Salinan selalu draft agar tidak langsung tayang. */
    public static function duplicateAction(): ReplicateAction
    {
        return ReplicateAction::make()->label('Duplikat')
            ->excludeAttributes(['impressions_count', 'clicks_count', 'closes_count'])
            ->beforeReplicaSaved(fn (Campaign $replica) => $replica->fill([
                'name' => $replica->name.' (salinan)',
                'status' => 'draft',
            ]))
            ->successRedirectUrl(fn (Campaign $replica) => EditCampaign::getUrl(['record' => $replica]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
