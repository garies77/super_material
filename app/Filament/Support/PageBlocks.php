<?php

namespace App\Filament\Support;

use App\Models\CustomForm;
use App\Support\LinkTarget;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

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
                    self::richEditor('image')->label('Gambar')
                        ->toolbarButtons([['attachFiles'], ['alignStart', 'alignCenter', 'alignEnd'], ['undo', 'redo']])
                        ->helperText('Klik ikon gambar untuk mengunggah, lalu tarik titik di sudut gambar untuk memperbesar/memperkecil. Rasio aspek dijaga otomatis.'),
                    Grid::make(4)->schema([
                        TextInput::make('width')->label('Lebar manual')->numeric()->minValue(1)->maxValue(2000)
                            ->helperText('Opsional, menimpa hasil tarik.'),
                        Select::make('width_unit')->label('Satuan')->options(['%' => '%', 'px' => 'px'])->default('%'),
                        Select::make('ratio')->label('Rasio aspek')->default('auto')
                            ->options(['auto' => 'Asli', '1/1' => '1:1', '4/3' => '4:3', '3/2' => '3:2', '16/9' => '16:9', '3/4' => '3:4']),
                        TextInput::make('caption')->label('Keterangan')->maxLength(255),
                    ]),
                ]),

                Block::make('gallery')->label('Galeri gambar')->icon('heroicon-o-squares-2x2')->schema([
                    FileUpload::make('images')->label('Gambar')->image()->imagePreset('page_gallery')->multiple()->reorderable()->appendFiles()
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
