<?php

namespace App\Filament\Support;

use App\Models\Category;
use App\Models\CustomForm;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Support\LinkTarget;
use App\Support\PagePriceTable;
use App\Support\PageProductGrid;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Blok konten halaman statis (page builder). Blok ditambah, disunting, dihapus, dan diurutkan
 * langsung di editor tanpa modal; konfigurasi tiap blok tersimpan sebagai JSON.
 */
class PageBlocks
{
    public const ALIGN = ['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'];

    public static function make(string $field = 'draft_blocks'): Builder
    {
        return Builder::make($field)->label('Konten halaman')
            ->blocks([
                Block::make('heading')->label('Judul (H1–H6)')->icon('heroicon-o-h1')->columns(4)->schema([
                    Select::make('level')->label('Level')->options(array_combine($h = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], array_map('strtoupper', $h)))->default('h2')->required(),
                    TextInput::make('text')->label('Teks')->required()->maxLength(255)->columnSpan(2),
                    Select::make('align')->label('Rata')->options(self::ALIGN)->default('left'),
                ]),

                Block::make('text')->label('Paragraf / teks')->icon('heroicon-o-bars-3-bottom-left')->schema([
                    self::richEditor('content')->label('Teks')
                        ->helperText('Bisa berisi paragraf, daftar, tautan, dan gambar. Gambar dapat diubah ukurannya dengan menarik sudutnya.'),
                ]),

                Block::make('image')->label('Gambar')->icon('heroicon-o-photo')->schema([
                    FileUpload::make('path')->label('Gambar')->image()->imagePreset('page_image')->altTextField('alt')
                        ->disk('public')->directory('pages')->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])->maxSize(4096)->required()
                        ->helperText('Klik ikon pensil di pratinjau untuk memotong, memutar, atau memilih rasio (bebas, 1:1, 4:3, 3:2, 16:9, 3:4).'),
                    Grid::make(3)->schema([
                        TextInput::make('alt')->label('Teks alternatif')->maxLength(255),
                        TextInput::make('caption')->label('Keterangan')->maxLength(255),
                        Select::make('align')->label('Rata')->options(self::ALIGN)->default('center'),
                    ]),
                ]),

                Block::make('gallery')->label('Galeri gambar')->icon('heroicon-o-squares-2x2')->schema([
                    FileUpload::make('images')->label('Gambar')->image()->imagePreset('page_gallery')->altTextField('alt')->multiple()->reorderable()->appendFiles()
                        ->disk('public')->directory('pages')->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])->maxSize(4096)->required(),
                    Grid::make(2)->schema([
                        Select::make('columns')->label('Kolom')->options([2 => '2', 3 => '3', 4 => '4'])->default(3),
                        TextInput::make('alt')->label('Teks alternatif')->maxLength(255),
                    ]),
                ]),

                Block::make('button')->label('Tombol / CTA')->icon('heroicon-o-cursor-arrow-rays')->columns(3)->schema([
                    TextInput::make('label')->label('Teks tombol')->required()->maxLength(60),
                    Select::make('style')->label('Gaya')->options(['primary' => 'Utama', 'dark' => 'Gelap', 'outline' => 'Garis'])->default('primary'),
                    Select::make('align')->label('Rata')->options(self::ALIGN)->default('left'),
                    ...LinkTarget::fields(),
                ]),

                // Kumpulan produk + tombol beli di satu marketplace (landing page Shopee/Tokopedia/Lazada/TikTok Shop)
                Block::make('products')->label('Grid produk marketplace')->icon('heroicon-o-shopping-bag')->columns(2)->schema([
                    TextInput::make('heading')->label('Judul bagian (H2)')->maxLength(120)
                        ->placeholder('mis. Peralatan Proyek Pilihan di Shopee'),
                    Select::make('channel_id')->label('Tombol beli di')->required()->live()
                        ->options(fn () => SalesChannel::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->helperText('Hanya produk yang punya tautan di kanal ini yang tampil.'),
                    Textarea::make('intro')->label('Teks pengantar (opsional)')->rows(2)->maxLength(500)->columnSpanFull(),
                    Select::make('source')->label('Produk yang ditampilkan')->options(PageProductGrid::SOURCES)->default('channel')
                        ->required()->live()->selectablePlaceholder(false)->columnSpanFull(),
                    Select::make('category_id')->label('Kategori (termasuk subkategori)')->searchable()
                        ->options(fn () => Category::orderBy('name')->pluck('name', 'id'))
                        ->visible(fn (Get $get) => $get('source') === 'category')
                        ->required(fn (Get $get) => $get('source') === 'category'),
                    Select::make('product_ids')->label('Produk (urutan sesuai pilihan)')->multiple()->searchable()->columnSpanFull()
                        ->getSearchResultsUsing(fn (string $search, Get $get) => Product::published()
                            ->where('name', 'like', "%{$search}%")
                            ->when($get('channel_id'), fn ($q, $channel) => $q->whereHas('links', fn ($l) => $l->where('sales_channel_id', $channel)))
                            ->orderBy('name')->limit(30)->pluck('name', 'id'))
                        ->getOptionLabelsUsing(fn (array $values) => Product::whereIn('id', $values)->pluck('name', 'id')->all())
                        ->visible(fn (Get $get) => $get('source') === 'manual')
                        ->required(fn (Get $get) => $get('source') === 'manual'),
                    Select::make('sort')->label('Urutan')->options(PageProductGrid::SORTS)->default('featured')->selectablePlaceholder(false)
                        ->visible(fn (Get $get) => $get('source') !== 'manual'),
                    TextInput::make('limit')->label('Jumlah maksimal')->numeric()->minValue(1)->maxValue(PageProductGrid::MAX_ITEMS)->default(12)->required(),
                    Select::make('columns')->label('Kolom di desktop')->options(['2' => '2', '3' => '3', '4' => '4'])->default('4')->selectablePlaceholder(false),
                    TextInput::make('button_label')->label('Teks tombol')->maxLength(40)
                        ->placeholder(fn (Get $get) => 'Beli di '.(SalesChannel::find($get('channel_id'))?->name ?? 'marketplace')),
                    Toggle::make('show_price')->label('Tampilkan harga')->default(true),
                ]),

                // Daftar harga acuan per varian (gaya halaman "daftar harga"), terisi otomatis dari data produk
                Block::make('price_table')->label('Tabel harga')->icon('heroicon-o-table-cells')->columns(2)->schema([
                    TextInput::make('heading')->label('Judul bagian (H2)')->maxLength(120)
                        ->placeholder('mis. Daftar Harga Peralatan Proyek di Shopee'),
                    Select::make('channel_id')->label('Tombol beli di')->required()
                        ->options(fn () => SalesChannel::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->helperText('Hanya produk yang punya tautan di kanal ini yang masuk tabel.'),
                    Textarea::make('intro')->label('Teks pengantar (opsional)')->rows(2)->maxLength(500)->columnSpanFull(),
                    Select::make('group_by')->label('Kelompokkan')->options(PagePriceTable::GROUPS)->default('category')->selectablePlaceholder(false),
                    TextInput::make('limit')->label('Jumlah produk maksimal')->numeric()->minValue(1)->maxValue(PageProductGrid::MAX_ITEMS)->default(PageProductGrid::MAX_ITEMS)->required()
                        ->helperText('Tiap varian tampil sebagai satu baris.'),
                ]),

                Block::make('highlights')->label('Poin unggulan')->icon('heroicon-o-check-badge')->schema([
                    Repeater::make('items')->label('Poin (maks. 4, singkat)')->schema([TextInput::make('text')->hiddenLabel()->required()->maxLength(80)])
                        ->reorderableWithDragAndDrop()->defaultItems(3)->maxItems(4)->addActionLabel('Tambah poin'),
                ]),

                // Tanya-jawab yang bisa dibuka-tutup; teks jawaban tetap ada di HTML sehingga terbaca mesin pencari
                Block::make('faq')->label('FAQ (tanya-jawab)')->icon('heroicon-o-question-mark-circle')->schema([
                    TextInput::make('heading')->label('Judul bagian (H2)')->maxLength(120)->default('Pertanyaan yang sering diajukan'),
                    Repeater::make('items')->label('Pertanyaan')->schema([
                        TextInput::make('question')->label('Pertanyaan (H3)')->required()->maxLength(200),
                        Textarea::make('answer')->label('Jawaban')->required()->rows(3)->maxLength(1000),
                    ])->reorderableWithDragAndDrop()->collapsible()->defaultItems(1)->addActionLabel('Tambah pertanyaan')
                        ->itemLabel(fn (array $state) => $state['question'] ?? null),
                ]),

                Block::make('list')->label('Daftar')->icon('heroicon-o-list-bullet')->schema([
                    Select::make('style')->label('Jenis')->options(['bullet' => 'Berpoin', 'number' => 'Bernomor'])->default('bullet'),
                    Repeater::make('items')->label('Butir')->schema([TextInput::make('text')->hiddenLabel()->required()->maxLength(500)])
                        ->reorderableWithDragAndDrop()->defaultItems(2)->addActionLabel('Tambah butir'),
                ]),

                Block::make('form')->label('Formulir')->icon('heroicon-o-clipboard-document-list')->schema([
                    Select::make('form_id')->label('Formulir')->required()->searchable()
                        ->options(fn () => CustomForm::embedOptions())
                        ->helperText('Hanya formulir dengan mode tampil "sematan". Kelola di menu Formulir.'),
                    Toggle::make('show_title')->label('Tampilkan judul & deskripsi formulir')->default(true),
                ]),

                Block::make('divider')->label('Pemisah')->icon('heroicon-o-minus')->schema([
                    Select::make('spacing')->label('Jarak')->options(['sm' => 'Rapat', 'md' => 'Sedang', 'lg' => 'Lebar'])->default('md'),
                ]),

                Block::make('spacer')->label('Spasi')->icon('heroicon-o-arrows-up-down')->schema([
                    TextInput::make('height')->label('Tinggi (px)')->numeric()->minValue(4)->maxValue(400)->default(40)->required(),
                ]),

                Block::make('embed')->label('Embed video')->icon('heroicon-o-play')->columns(2)->schema([
                    TextInput::make('url')->label('URL YouTube')->required()->maxLength(500)
                        ->regex('#^https://(www\.|m\.)?(youtube\.com/(watch\?v=|shorts/|embed/)|youtu\.be/)[\w-]{11}#i')
                        ->validationMessages(['regex' => 'Masukkan URL video YouTube yang valid.']),
                    TextInput::make('title')->label('Judul video')->maxLength(255),
                ]),

                Block::make('html')->label('HTML khusus')->icon('heroicon-o-code-bracket')->schema([
                    CodeEditor::make('html')->label('HTML')->language(Language::Html)
                        ->helperText('Disanitasi saat tampil: skrip, atribut on*, dan iframe dibuang. Untuk JavaScript gunakan bagian Custom HTML (khusus Admin).'),
                ]),
            ])
            ->addActionLabel('Tambah elemen')
            ->blockPickerColumns(3)
            ->reorderableWithDragAndDrop()
            ->collapsible()
            ->cloneable()
            ->blockNumbers(false)
            ->columnSpanFull();
    }

    private static function richEditor(string $name): RichEditor
    {
        // Gambar disimpan di disk publik agar src tetap di HTML; SVG ditolak karena tidak lewat sanitizer
        return RichEditor::make($name)
            ->resizableImages()
            ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('pages')->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])
            ->fileAttachmentsMaxSize(4096);
    }
}
