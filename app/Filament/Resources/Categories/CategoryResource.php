<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Support\DescriptiveFileName;
use App\Filament\Support\SeoFields;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'Kategori';

    protected static ?string $pluralModelLabel = 'Kategori';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    /** Induk yang boleh dipilih: kedalaman maksimal tiga level. */
    public static function parentOptions(?int $exceptId = null): array
    {
        return Category::with('parent.parent')->orderBy('name')->get()
            ->filter(fn (Category $c) => $c->id !== $exceptId && $c->depth() < 3)
            ->mapWithKeys(fn (Category $c) => [$c->id => collect($c->breadcrumbs())->pluck('name')->implode(' › ')])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        // Kiri: isi halaman kategori dan SEO. Kanan: posisi, gambar, dan pengaturan. Satu kolom di bawah xl.
        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 7, '2xl' => 8])->schema([
                Section::make('Konten kategori')->description('Nama, alamat URL, dan teks yang tampil di halaman kategori.')->columns(2)->schema([
                    TextInput::make('name')->label('Nama kategori')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)
                        ->helperText('Mengubah slug otomatis membuat redirect 301 dari URL lama.'),
                    Textarea::make('intro')->label('Teks pengantar (di atas daftar produk)')->rows(3)->columnSpanFull(),
                    RichEditor::make('bottom_content')->label('Teks pendukung (di bawah daftar produk)')->columnSpanFull(),
                ]),
                SeoFields::make(),
            ]),

            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->schema([
                Section::make('Pengaturan')->schema([
                    Toggle::make('is_active')->label('Aktif')->default(true)
                        ->helperText('Nonaktif: kategori dan halamannya disembunyikan dari situs.'),
                    Select::make('parent_id')->label('Kategori induk')->searchable()
                        ->options(fn (?Category $record) => self::parentOptions($record?->id))
                        ->placeholder('— Kategori utama —'),
                    TextInput::make('sort_order')->label('Urutan tampil')->numeric()->default(0)
                        ->helperText('Angka kecil tampil lebih dulu.'),
                    TextInput::make('whatsapp_number')->label('Nomor WhatsApp kategori')->helperText('Kosong = pakai nomor WhatsApp default.'),
                ]),
                Section::make('Gambar')->schema([
                    FileUpload::make('image')->hiddenLabel()->image()->imagePreset('square_small')->disk('public')->directory('categories')->visibility('public')
                        ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'categories'))
                        ->helperText('Ikon kartu kategori di beranda (dipotong persegi). Disarankan 200×200 px. Kosong = huruf awal nama.'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('Induk')->placeholder('—'),
                TextColumn::make('products_count')->counts('products')->label('Produk'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
