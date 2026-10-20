<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
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

    protected static ?int $navigationSort = 1;

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
        return $schema->components([
            Section::make('Umum')->columns(2)->schema([
                TextInput::make('name')->label('Nama')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)
                    ->helperText('Mengubah slug otomatis membuat redirect 301 dari URL lama.'),
                Select::make('parent_id')->label('Induk')->searchable()
                    ->options(fn (?Category $record) => self::parentOptions($record?->id))
                    ->placeholder('— Kategori utama —'),
                FileUpload::make('image')->label('Gambar')->image()->disk('public')->directory('categories')->visibility('public'),
                Textarea::make('intro')->label('Teks pengantar (di atas daftar produk)')->rows(3)->columnSpanFull(),
                RichEditor::make('bottom_content')->label('Teks pendukung (di bawah daftar produk)')->columnSpanFull(),
                TextInput::make('whatsapp_number')->label('Nomor WhatsApp kategori')->helperText('Kosong = pakai nomor global.'),
                TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
            SeoFields::make(),
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
