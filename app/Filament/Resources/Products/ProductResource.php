<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Support\DescriptiveFileName;
use App\Filament\Support\SeoFields;
use App\Support\SeoScore;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Filament\Support\CustomCodeFields;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi produk')->columns(2)->schema([
                TextInput::make('name')->label('Nama')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)
                    ->helperText('Mengubah slug otomatis membuat redirect 301 dari URL lama.'),
                Select::make('category_id')->label('Kategori')->relationship('category', 'name')->searchable()->preload(),
                Select::make('brand_id')->label('Merek')->relationship('brand', 'name')->searchable()->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('slug')->required()->unique('brands', 'slug'),
                    ]),
                TextInput::make('sku')->label('SKU Induk (productGroupID)')->placeholder('cth: STR-50')->maxLength(100),
                TextInput::make('mpn')->label('MPN (Manufacturer Part Number)')->placeholder('cth: MPN-9988')->maxLength(100),
                TextInput::make('badge')->label('Badge Produk')->placeholder('Misal: Resmi, Best Seller, SNI'),
                TextInput::make('unit')->label('Satuan Penjualan')->placeholder('sak, batang, m², dus'),
                Textarea::make('summary')->label('Ringkasan')->rows(2)->columnSpanFull(),
                RichEditor::make('description')->label('Deskripsi')
                    ->helperText('Tulis deskripsi unik; jangan salin dari marketplace.')->columnSpanFull(),
                KeyValue::make('specifications')->label('Spesifikasi Teknis')->keyLabel('Atribut')->valueLabel('Nilai')->columnSpanFull(),
            ]),

            CustomCodeFields::make(),

            Section::make('Galeri Media (Rasio 1:1)')->description('Dukungan foto dan video (upload atau tautan YouTube).')->schema([
                Repeater::make('media')->relationship()->label('Media Produk')->columns(4)->defaultItems(0)->orderColumn('sort_order')->schema([
                    Select::make('type')->label('Tipe')
                        ->options(['image' => 'Gambar', 'video' => 'Video'])
                        ->default('image')->required()->live(),
                    Select::make('provider')->label('Sumber')
                        ->options(['upload' => 'Upload File', 'youtube' => 'YouTube Embed'])
                        ->default('upload')->required()->live(),
                    TextInput::make('path')->label('URL / Path')
                        ->helperText('Jika YouTube masukkan URL video, jika upload masukkan path berkas.')
                        ->required()->columnSpan(2),
                    TextInput::make('poster_path')->label('URL Poster (Video)')->placeholder('Opsional untuk thumbnail video'),
                    TextInput::make('alt_text')->label('Alt Text')->placeholder('Keterangan aksesibilitas'),
                ]),
                Section::make('Fallback Gambar Cepat')->columns(2)->collapsed()->schema([
                    FileUpload::make('image')->label('Gambar utama cepat')->image()->disk('public')->directory('products')->visibility('public')
                        ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'products'))
                        ->rule(new \App\Rules\MinImagePixels(50000))
                        ->helperText('Minimal 50.000 piksel. Sistem otomatis membuat turunan 16:9, 4:3, dan 1:1 untuk Search & Discover (PRD 9.13.3).'),
                    FileUpload::make('gallery')->label('Galeri foto cepat')->image()->multiple()->reorderable()->disk('public')->directory('products')->visibility('public')
                        ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'products'))
                        ->rule(new \App\Rules\MinImagePixels(50000)),
                ]),
            ]),

            Section::make('Harga & Catatan Pembelian')->columns(3)->schema([
                Select::make('price_display')->label('Tampilan Harga')
                    ->options([
                        'show' => 'Tampilkan Harga (Acuan/Varian)',
                        'hidden' => 'Sembunyikan ("Hubungi kami untuk harga")',
                    ])->default('show')->required(),
                TextInput::make('price_min')->label('Harga Minimum Acuan')->numeric()->prefix('Rp'),
                TextInput::make('price_max')->label('Harga Maksimum Acuan')->numeric()->prefix('Rp'),
                Select::make('availability')->label('Status Ketersediaan (Schema.org)')
                    ->options([
                        'InStock' => 'Tersedia (InStock)',
                        'LimitedAvailability' => 'Stok Terbatas (LimitedAvailability)',
                        'PreOrder' => 'Pre-order (PreOrder)',
                        'OutOfStock' => 'Habis (OutOfStock)',
                    ])
                    ->placeholder('Pilih ketersediaan (opsional)')
                    ->helperText('Hanya ditulis ke schema JSON-LD bila diisi (PRD 9.10.2).'),
                DatePicker::make('price_valid_until')->label('Masa Berlaku Harga / Diskon')
                    ->helperText('Tanggal kedaluwarsa harga acuan (PRD 9.10.2).'),
                TextInput::make('whatsapp_number')->label('Nomor WA Khusus (Timpa Global)'),
                TextInput::make('shipping_note')->label('Catatan Pengiriman')->placeholder('Pengiriman dari Medan · Ongkir terjangkau'),
                TextInput::make('guarantee_note')->label('Catatan Jaminan')->placeholder('100% Original · Garansi Pabrik'),
                Toggle::make('show_quantity')->label('Tampilkan Stepper Kuantitas')->default(true),
            ]),

            Section::make('Penilaian & Ulasan (Rating)')->columns(3)->collapsed()->schema([
                TextInput::make('rating_value')->label('Nilai Rating (0 - 5.0)')->numeric()->step(0.1)->minValue(0)->maxValue(5),
                TextInput::make('rating_count')->label('Jumlah Penilaian')->numeric(),
                DatePicker::make('rating_updated_at')->label('Tanggal Update Rating'),
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
                    \Filament\Forms\Components\DateTimePicker::make('discount_starts_at')->label('Mulai Diskon')
                        ->requiredWith('compare_at_price')
                        ->helperText('Wajib diisi jika ada harga coret.'),
                    \Filament\Forms\Components\DateTimePicker::make('discount_ends_at')->label('Berakhir Diskon')
                        ->requiredWith('compare_at_price')
                        ->afterOrEqual('discount_starts_at')
                        ->helperText('Wajib >= tanggal mulai.'),
                    TextInput::make('image_path')->label('URL / Path Gambar Varian')->columnSpan(3),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
            ]),

            Section::make('Tombol beli (kanal)')->description('Kanal tanpa tautan tidak ditampilkan. WhatsApp otomatis memakai nomor di Pengaturan.')->schema([
                Repeater::make('links')->relationship()->label('')->columns(2)->defaultItems(0)->schema([
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

            SeoFields::make(),

            Section::make('Publikasi')->columns(2)->schema([
                Toggle::make('is_published')->label('Terbit'),
                Toggle::make('is_featured')->label('Tampilkan di beranda'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->square()->size(40),
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
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Kategori')->relationship('category', 'name'),
                SelectFilter::make('brand_id')->label('Merek')->relationship('brand', 'name'),
                TernaryFilter::make('is_published')->label('Terbit'),
                TernaryFilter::make('price_review_needed')->label('Perlu tinjau harga'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('publish')->label('Terbitkan')
                    ->action(fn (Collection $records) => $records->each->update(['is_published' => true]))->deselectRecordsAfterCompletion(),
                BulkAction::make('unpublish')->label('Sembunyikan')
                    ->action(fn (Collection $records) => $records->each->update(['is_published' => false]))->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
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
