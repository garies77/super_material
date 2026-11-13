<?php

namespace App\Filament\Resources\LinkPages;

use App\Filament\Pages\LinkReport;
use App\Filament\Resources\LinkPages\Pages\CreateLinkPage;
use App\Filament\Resources\LinkPages\Pages\EditLinkPage;
use App\Filament\Resources\LinkPages\Pages\ListLinkPages;
use App\Filament\Support\DescriptiveFileName;
use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\LinkStat;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Support\LinkTarget;
use App\Support\Reports\ClickReport;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Link Bio (seperti Linktree): halaman /l/{slug} berisi tombol tautan, produk, dan WhatsApp untuk bio Instagram/TikTok. */
class LinkPageResource extends Resource
{
    protected static ?string $model = LinkPage::class;

    protected static ?string $slug = 'link-bio';

    protected static ?string $modelLabel = 'Link Bio';

    protected static ?string $pluralModelLabel = 'Link Bio';

    protected static ?string $navigationLabel = 'Link Bio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Pemasaran';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        $type = fn (string ...$types) => fn (Get $get) => in_array($get('type'), $types, true);

        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 7, '2xl' => 8])->schema([
                Section::make('Profil')->description('Yang tampil di bagian atas halaman.')->columns(2)->schema([
                    TextInput::make('title')->label('Nama tampil')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')->label('Alamat')->required()->unique(ignoreRecord: true)->maxLength(100)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->prefix('/l/')
                        ->validationMessages(['regex' => 'Gunakan huruf kecil, angka, dan tanda hubung.']),
                    Textarea::make('bio')->label('Bio')->rows(3)->maxLength(300)->columnSpanFull(),
                    FileUpload::make('avatar_path')->label('Foto profil / logo')->image()->imagePreset('square_small')
                        ->disk('public')->directory('link-bio')->visibility('public')
                        ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('title', 'link-bio')),
                    Select::make('theme')->label('Tema')->options(LinkPage::THEMES)->default('light')->required()->selectablePlaceholder(false),
                ]),

                Section::make('Tombol & tautan')->description('Seret untuk mengurutkan. Klik setiap tombol tercatat di Laporan Link Bio.')->schema([
                    Repeater::make('items')->relationship()->label('')->orderColumn('sort')->defaultItems(0)
                        ->reorderableWithDragAndDrop()->collapsible()->cloneable()->addActionLabel('Tambah tombol')->columns(2)
                        ->itemLabel(fn (array $state) => ($state['label'] ?? null)
                            ? ($state['label'].(($state['is_active'] ?? true) ? '' : ' (nonaktif)'))
                            : 'Tombol baru')
                        ->schema([
                            Select::make('type')->label('Jenis')->options(LinkItem::TYPES)->default('link')->required()->live()->selectablePlaceholder(false),
                            TextInput::make('label')->label(fn (Get $get) => $get('type') === 'heading' ? 'Teks judul' : 'Teks tombol')->required()->maxLength(120),

                            Group::make(LinkTarget::fields())->columns(2)->columnSpanFull()->visible($type('link')),

                            Select::make('product_id')->label('Produk')->searchable()->required($type('product'))->visible($type('product'))
                                ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id')),
                            Select::make('sales_channel_id')->label('Beli lewat')->visible($type('product'))
                                ->options(fn () => SalesChannel::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->placeholder('Halaman produk di situs')
                                ->helperText('Kosong: menuju halaman produk. Marketplace: lewat /go agar klik masuk laporan produk.'),

                            TextInput::make('whatsapp_number')->label('Nomor WhatsApp')->tel()->maxLength(30)->visible($type('whatsapp'))
                                ->regex('/^[0-9+()\-\s.]{6,25}$/')->placeholder('Kosong = nomor di Pengaturan situs'),
                            TextInput::make('whatsapp_message')->label('Pesan awal')->maxLength(500)->visible($type('whatsapp')),

                            FileUpload::make('thumbnail_path')->label('Gambar kecil (opsional)')->image()->imagePreset('square_small')
                                ->disk('public')->directory('link-bio')->visibility('public')
                                ->visible($type('link', 'product', 'whatsapp'))
                                ->helperText(fn (Get $get) => $get('type') === 'product' ? 'Kosong = foto produk.' : null),

                            Group::make([
                                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                                Toggle::make('is_highlighted')->label('Tonjolkan')->inline(false)->visible($type('link', 'product', 'whatsapp')),
                            ])->columns(2),

                            DateTimePicker::make('starts_at')->label('Mulai tampil (WIB)')->seconds(false)->timezone(ClickReport::TIMEZONE),
                            DateTimePicker::make('ends_at')->label('Berhenti tampil (WIB)')->seconds(false)->timezone(ClickReport::TIMEZONE)->after('starts_at'),
                        ]),
                ]),
            ]),

            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->schema([
                Section::make('Status')->schema([
                    Toggle::make('is_published')->label('Terbit')->default(false)
                        ->helperText('Draf hanya bisa dilihat tim lewat tombol Lihat halaman, dan tidak dihitung di laporan.'),
                    Text::make(fn (?LinkPage $record) => $record
                        ? 'Pasang di bio Instagram: '.$record->url().'?utm_source=instagram'
                        : 'Alamat tersedia setelah disimpan.'),
                    Text::make(fn (?LinkPage $record) => $record ? 'Pasang di bio TikTok: '.$record->url().'?utm_source=tiktok' : '')
                        ->visible(fn (?LinkPage $record) => (bool) $record),
                    Text::make('Aplikasi Instagram & TikTok sering tidak mengirim asal kunjungan; tambahan ?utm_source membuat sumbernya tetap terbaca di laporan.')
                        ->visible(fn (?LinkPage $record) => (bool) $record),
                ]),

                Section::make('Media sosial')->description('Ikon di bawah bio. URL harus https dan dari domain resmi platform.')->schema([
                    Repeater::make('socials')->label('')->defaultItems(0)->reorderableWithDragAndDrop()->addActionLabel('Tambah akun')
                        ->schema([
                            Select::make('platform')->label('Platform')->options(LinkPage::socialOptions())->required()->live(),
                            TextInput::make('url')->label('URL profil')->required()->maxLength(500)
                                ->rule(fn (Get $get) => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                                    if (! LinkPage::isValidSocialUrl($get('platform'), $value)) {
                                        $fail('URL harus https dan berada di domain resmi platform yang dipilih.');
                                    }
                                }),
                        ]),
                ]),

                Section::make('SEO')->collapsed()->schema([
                    TextInput::make('meta_title')->label('Judul halaman')->maxLength(70)->placeholder('Kosong = nama tampil'),
                    Textarea::make('meta_description')->label('Deskripsi')->rows(2)->maxLength(160)->placeholder('Kosong = bio'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $since = fn () => now(ClickReport::TIMEZONE)->subDays(29)->toDateString();

        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum(['stats as views_30d' => fn ($q) => $q->where('link_item_id', LinkStat::PAGE_VIEW)->where('date', '>=', $since())], 'count')
                ->withSum(['stats as clicks_30d' => fn ($q) => $q->where('link_item_id', '>', LinkStat::PAGE_VIEW)->where('date', '>=', $since())], 'count')
                ->withCount('items'))
            ->columns([
                TextColumn::make('title')->label('Nama')->searchable()->weight('medium')
                    ->description(fn (LinkPage $r) => '/l/'.$r->slug),
                TextColumn::make('link')->label('Tautan untuk bio')->state(fn (LinkPage $r) => $r->url())
                    ->copyable()->copyMessage('Tautan disalin')->color('gray')->toggleable(),
                IconColumn::make('is_published')->label('Terbit')->boolean(),
                TextColumn::make('items_count')->label('Tombol')->numeric(),
                TextColumn::make('views_30d')->label('Tayangan 30 hr')->numeric()->default(0)->sortable(),
                TextColumn::make('clicks_30d')->label('Klik 30 hr')->numeric()->default(0)->sortable(),
            ])
            ->recordActions([
                self::openAction(),
                Action::make('report')->label('Laporan')->icon('heroicon-o-chart-bar')->color('gray')
                    ->url(fn (LinkPage $record) => LinkReport::getUrl(['filters' => ['page' => $record->id]])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function openAction(): Action
    {
        return Action::make('open')->label('Lihat halaman')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
            ->url(fn (LinkPage $record) => $record->url())->openUrlInNewTab();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLinkPages::route('/'),
            'create' => CreateLinkPage::route('/create'),
            'edit' => EditLinkPage::route('/{record}/edit'),
        ];
    }
}
