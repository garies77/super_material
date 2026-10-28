<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Support\CustomCodeFields;
use App\Filament\Support\DescriptiveFileName;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\ReviewWorkflow;
use App\Filament\Support\SeoFields;
use App\Filament\Support\SeoScorePanel;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Rules\MinImagePixels;
use App\Support\Catalog\ProductDuplicator;
use App\Support\SeoScore;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Skor SEO dari isi form yang belum disimpan: salinan produk diisi nilai form, foto & varian dari state
     * (tidak ada yang disimpan ke database).
     *
     * @return array{score: int, checks: array<string, bool>, pending: bool}
     */
    public static function seoPreview(Get $get, ?Product $record): array
    {
        $product = $record ? clone $record : new Product;
        $fields = ['name', 'sku', 'brand_id', 'availability', 'price_display', 'price_min', 'price_max', 'price_valid_until', 'meta_title', 'meta_description', 'summary'];
        $product->forceFill(collect($fields)->mapWithKeys(fn (string $field) => [$field => $get($field)])->all());
        $product->og_image = SeoScorePanel::storedPaths($get('og_image'))[0] ?? null;

        // Teks alternatif foto baru diisi otomatis saat simpan (ProductPhotos), jadi dinilai terisi bila nama ada
        $product->setRelation('media', new Collection(collect(SeoScorePanel::storedPaths($get('photos')))
            ->map(fn (string $path, int $order) => new ProductMedia(['type' => 'image', 'provider' => 'upload', 'path' => $path, 'alt_text' => $product->name, 'sort_order' => $order]))
            ->all()));
        $product->setRelation('variants', new Collection(collect((array) $get('variants'))
            ->map(fn (array $variant) => (new ProductVariant)->forceFill(Arr::only($variant, ['price', 'compare_at_price', 'discount_starts_at', 'discount_ends_at', 'is_active'])))
            ->all()));

        return SeoScore::forProduct($product) + ['pending' => SeoScorePanel::hasPendingUpload($get('photos'))];
    }

    /** Salin produk sebagai draf lalu buka halaman edit salinannya (dipakai di daftar & halaman edit). */
    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')->label('Duplikat')->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Duplikat produk ini?')
            ->modalDescription('Salinan berisi spesifikasi, foto & video, varian, tautan kanal, dan FAQ, disimpan sebagai draf (belum terbit). SKU dan MPN dikosongkan karena harus unik.')
            ->modalSubmitActionLabel('Buat salinan')
            ->action(function (Product $record, Action $action): void {
                $copy = ProductDuplicator::duplicate($record);
                AuditLog::record(request(), 'product.duplicated', $copy, ['from_product_id' => $record->id]);

                Notification::make()->title('Salinan dibuat sebagai draf')->body('Ganti nama, harga, dan detail yang berbeda, lalu terbitkan.')->success()->send();
                $action->redirect(self::getUrl('edit', ['record' => $copy]));
            });
    }

    /** Baris video di product_media: tautan YouTube = embed, selain itu berkas video unggahan (API). */
    private static function videoData(array $data): array
    {
        $isYoutube = (bool) preg_match('#^https://(www\.|m\.)?(youtube\.com|youtu\.be)/#i', (string) ($data['path'] ?? ''));

        return [...$data, 'type' => 'video', 'provider' => $isYoutube ? 'youtube' : 'upload'];
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor', 'contributor'], true);
    }

    /** Kontributor hanya mengubah draf miliknya (HasReviewWorkflow menegakkan hal yang sama di model). */
    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && $record->isEditableBy(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return ! ReviewWorkflow::isContributor() && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return ! ReviewWorkflow::isContributor() && parent::canDeleteAny();
    }

    public static function form(Schema $schema): Schema
    {
        // Dua kolom utama: 7:5 di desktop menengah (xl), 8:4 di layar lebar (2xl); satu kolom di bawah 1280 px. Kiri: info, media & varian di kiri, publikasi, harga & kanal di kolom pendamping
        // Panel skor SEO di kolom kanan; field pemicu hanya me-render ulang panel ini
        $seoPanel = SeoScorePanel::make(fn (Get $get, ?Product $record) => self::seoPreview($get, $record));
        $refreshSeo = SeoScorePanel::refresh();

        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 7, '2xl' => 8])->schema([
                Section::make('Informasi produk')->columns(2)->schema([
                    TextInput::make('name')->label('Nama')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)
                        ->helperText('Mengubah slug otomatis membuat redirect 301 dari URL lama.'),
                    Select::make('category_id')->label('Kategori')->relationship('category', 'name')->searchable()->preload(),
                    Select::make('brand_id')->label('Merek')->relationship('brand', 'name')->searchable()->preload()->live()->afterStateUpdated($refreshSeo)
                        ->createOptionForm([
                            TextInput::make('name')->required(),
                            TextInput::make('slug')->required()->unique('brands', 'slug'),
                        ]),
                    TextInput::make('sku')->label('SKU Induk (productGroupID)')->placeholder('cth: STR-50')->maxLength(100)->live(onBlur: true)->afterStateUpdated($refreshSeo),
                    TextInput::make('mpn')->label('MPN (Manufacturer Part Number)')->placeholder('cth: MPN-9988')->maxLength(100),
                    TextInput::make('badge')->label('Badge Produk')->placeholder('Misal: Resmi, Best Seller, SNI'),
                    TextInput::make('unit')->label('Satuan Penjualan')->placeholder('sak, batang, m², dus'),
                    Textarea::make('summary')->label('Ringkasan')->rows(2)->columnSpanFull()->live(onBlur: true)->afterStateUpdated($refreshSeo),
                    RichEditor::make('description')->label('Deskripsi')
                        ->helperText('Tulis deskripsi unik; jangan salin dari marketplace.')->columnSpanFull(),
                    KeyValue::make('specifications')->label('Spesifikasi Teknis')->keyLabel('Atribut')->valueLabel('Nilai')->columnSpanFull(),
                ]),

                // Satu galeri foto (ProductPhotos menyimpannya ke product_media); video YouTube terpisah
                Section::make('Foto & video produk')
                    ->description('Seret beberapa foto sekaligus atau pilih dari Media. Foto pertama menjadi foto utama; geser untuk mengubah urutan.')
                    ->schema([
                        FileUpload::make('photos')->label('Foto produk')->image()->imagePreset('product')
                            ->multiple()->reorderable()->appendFiles()->panelLayout('grid')
                            ->disk('public')->directory('products')->visibility('public')
                            ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'products'))
                            ->rule(new MinImagePixels(50000))
                            ->helperText('Otomatis dipotong 1:1 dan diperkecil ke 1200×1200 px (minimal 50.000 piksel). Teks alternatif diisi dari Pustaka Media atau nama produk.')
                            ->live()->afterStateUpdated($refreshSeo),
                        Repeater::make('videos')->label('Video (opsional)')
                            ->relationship('media', fn (Builder $query) => $query->where('type', 'video'))
                            ->defaultItems(0)->columns(2)->addActionLabel('Tambah video')
                            ->schema([
                                TextInput::make('path')->label('URL video YouTube')->required()->maxLength(500)
                                    ->placeholder('https://www.youtube.com/watch?v=…'),
                                TextInput::make('alt_text')->label('Judul video')->maxLength(255)->placeholder('cth: Cara memasang keramik 60x60'),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::videoData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::videoData($data)),
                    ]),

                Section::make('Varian Produk')->description('Dapat berupa pilihan ukuran, ketebalan, warna, dsb. dengan harga & diskon masing-masing (Pola Single-Page ProductGroup PRD 9.14.2).')->schema([
                    Repeater::make('variants')->relationship()->label('')->columns(4)->defaultItems(0)->orderColumn('sort_order')->schema([
                        TextInput::make('option_group')->label('Grup')->default('Varian')->placeholder('cth: Ukuran / Warna')->required(),
                        TextInput::make('name')->label('Nama Varian')->placeholder('cth: 40 kg, Putih 60x60')->required()->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')->label('Slug Varian')->required(),
                        TextInput::make('sku')->label('SKU'),
                        TextInput::make('price')->label('Harga Jual')->numeric()->prefix('Rp')->required(),
                        TextInput::make('compare_at_price')->label('Harga Coret (Sebelum Diskon)')->numeric()->prefix('Rp'),
                        DateTimePicker::make('discount_starts_at')->label('Mulai Diskon')
                            ->requiredWith('compare_at_price')
                            ->helperText('Wajib diisi jika ada harga coret.'),
                        DateTimePicker::make('discount_ends_at')->label('Berakhir Diskon')
                            ->requiredWith('compare_at_price')
                            ->afterOrEqual('discount_starts_at')
                            ->helperText('Wajib >= tanggal mulai.'),
                        TextInput::make('image_path')->label('URL / Path Gambar Varian')->columnSpan(3)
                            ->hintAction(MediaPicker::action('products', 50000, 'product')),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ]),
                ]),

                CustomCodeFields::make(),

                SeoFields::make(),
            ]),

            // Kolom pendamping
            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->schema([
                Section::make('Skor SEO')->schema([$seoPanel]),

                Section::make('Publikasi')->columns(2)->schema([
                    // Kontributor tidak menerbitkan sendiri: Editor/Admin menyetujui lewat "Setujui & terbitkan"
                    Toggle::make('is_published')->label('Terbit')
                        ->disabled(! ReviewWorkflow::canPublish())->dehydrated(ReviewWorkflow::canPublish())
                        ->helperText(ReviewWorkflow::canPublish() ? null : 'Diterbitkan oleh Editor/Admin setelah Anda klik "Ajukan untuk ditinjau".'),
                    Toggle::make('is_featured')->label('Tampilkan di beranda')
                        ->disabled(! ReviewWorkflow::canPublish())->dehydrated(ReviewWorkflow::canPublish()),
                ]),

                Section::make('Harga & Catatan Pembelian')->columns(2)->schema([
                    Select::make('price_display')->label('Tampilan Harga')->live()->afterStateUpdated($refreshSeo)
                        ->options([
                            'show' => 'Tampilkan Harga (Acuan/Varian)',
                            'hidden' => 'Sembunyikan ("Hubungi kami untuk harga")',
                        ])->default('show')->required(),
                    TextInput::make('price_min')->label('Harga Minimum Acuan')->numeric()->prefix('Rp')->live(onBlur: true)->afterStateUpdated($refreshSeo),
                    TextInput::make('price_max')->label('Harga Maksimum Acuan')->numeric()->prefix('Rp'),
                    Select::make('availability')->label('Status Ketersediaan (Schema.org)')->live()->afterStateUpdated($refreshSeo)
                        ->options([
                            'InStock' => 'Tersedia (InStock)',
                            'LimitedAvailability' => 'Stok Terbatas (LimitedAvailability)',
                            'PreOrder' => 'Pre-order (PreOrder)',
                            'OutOfStock' => 'Habis (OutOfStock)',
                        ])
                        ->placeholder('Pilih ketersediaan (opsional)')
                        ->helperText('Hanya ditulis ke schema JSON-LD bila diisi (PRD 9.10.2).'),
                    DatePicker::make('price_valid_until')->label('Masa Berlaku Harga / Diskon')->live()->afterStateUpdated($refreshSeo)
                        ->helperText('Tanggal kedaluwarsa harga acuan (PRD 9.10.2).'),
                    TextInput::make('whatsapp_number')->label('Nomor WA Khusus (Timpa Global)'),
                    TextInput::make('shipping_note')->label('Catatan Pengiriman')->placeholder('Pengiriman dari Medan · Ongkir terjangkau'),
                    TextInput::make('guarantee_note')->label('Catatan Jaminan')->placeholder('100% Original · Garansi Pabrik'),
                    Toggle::make('show_quantity')->label('Tampilkan Stepper Kuantitas')->default(true),
                ]),

                Section::make('Penilaian & Ulasan (Rating)')->columns(2)->collapsed()->schema([
                    TextInput::make('rating_value')->label('Nilai Rating (1.0 - 5.0)')->numeric()->step(0.1)->minValue(1)->maxValue(5),
                    TextInput::make('rating_count')->label('Jumlah Penilaian')->numeric()->integer()->minValue(0),
                    DatePicker::make('rating_updated_at')->label('Tanggal Update Rating'),
                ]),

                Section::make('Tombol beli (kanal)')->description('Kanal tanpa tautan tidak ditampilkan. WhatsApp otomatis memakai nomor di Pengaturan.')->schema([
                    Repeater::make('links')->relationship()->label('')->defaultItems(0)->schema([
                        Select::make('sales_channel_id')->label('Kanal')
                            ->options(fn () => SalesChannel::orderBy('sort_order')->pluck('name', 'id'))
                            ->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('url')->label('URL marketplace / nomor WA')->required()->maxLength(1000),
                        TextInput::make('message')->label('Pesan awal WhatsApp')->helperText('Opsional, hanya untuk WhatsApp.'),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ]),
                ]),

                Section::make('FAQ')->collapsed()->schema([
                    Repeater::make('faqs')->relationship()->label('')->defaultItems(0)->orderColumn('sort_order')->schema([
                        TextInput::make('question')->label('Pertanyaan')->required(),
                        Textarea::make('answer')->label('Jawaban')->required()->rows(2),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('media'))
            ->columns([
                // Foto utama dari galeri (product_media), termasuk produk dari API yang kolom image-nya kosong
                ImageColumn::make('image')->label('')->square()->size(40)->state(fn (Product $record) => $record->imageUrl()),
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->limit(50),
                TextColumn::make('badge')->label('Badge')->badge()->color('warning'),
                TextColumn::make('category.name')->label('Kategori')->sortable(),
                TextColumn::make('brand.name')->label('Merek')->toggleable(),
                TextColumn::make('links_count')->counts('links')->label('Kanal'),
                TextColumn::make('seo_score')->label('Skor SEO')->badge()
                    ->state(fn (Product $r) => SeoScore::forProduct($r)['score'].'%')
                    ->color(fn (string $state) => (int) $state >= 80 ? 'success' : ((int) $state >= 50 ? 'warning' : 'danger'))
                    ->tooltip(fn (Product $r) => collect(SeoScore::forProduct($r)['checks'])->reject()->keys()->map(fn ($c) => '✗ '.$c)->implode("\n") ?: 'Semua pemeriksaan terpenuhi'),
                IconColumn::make('price_review_needed')->label('Harga kedaluwarsa')->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')->falseColor('gray')->toggleable(),
                IconColumn::make('is_published')->label('Terbit')->boolean(),
                ReviewWorkflow::statusColumn(),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Kategori')->relationship('category', 'name'),
                SelectFilter::make('brand_id')->label('Merek')->relationship('brand', 'name'),
                ReviewWorkflow::statusFilter(),
                TernaryFilter::make('is_published')->label('Terbit'),
                TernaryFilter::make('price_review_needed')->label('Perlu tinjau harga'),
            ])
            ->recordActions([EditAction::make(), self::duplicateAction()])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('publish')->label('Terbitkan')->visible(ReviewWorkflow::canPublish())
                    ->action(fn (Collection $records) => $records->each->update(['is_published' => true]))->deselectRecordsAfterCompletion(),
                BulkAction::make('unpublish')->label('Sembunyikan')->visible(ReviewWorkflow::canPublish())
                    ->action(fn (Collection $records) => $records->each->update(['is_published' => false]))->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make()->visible(ReviewWorkflow::canPublish())
                    ->before(fn (Collection $records) => AuditLog::record(request(), 'product.bulk_deleted', null, ['ids' => $records->modelKeys(), 'names' => $records->pluck('name')->all()])),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
