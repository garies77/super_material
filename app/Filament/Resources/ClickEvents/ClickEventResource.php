<?php

namespace App\Filament\Resources\ClickEvents;

use App\Filament\Resources\ClickEvents\Pages\ListClickEvents;
use App\Models\ClickEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class ClickEventResource extends Resource
{
    protected static ?string $model = ClickEvent::class;

    protected static ?string $modelLabel = 'Klik beli';

    protected static ?string $pluralModelLabel = 'Laporan klik';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('product.name')->label('Produk')->limit(40)->searchable(),
                TextColumn::make('product.category.name')->label('Kategori'),
                TextColumn::make('channel.name')->label('Kanal')->badge(),
                TextColumn::make('page_url')->label('Halaman asal')->limit(40)->toggleable(),
                TextColumn::make('utm_source')->label('UTM')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_bot')->label('Bot')->boolean(),
            ])
            ->filters([
                SelectFilter::make('sales_channel_id')->label('Kanal')->relationship('channel', 'name'),
                SelectFilter::make('category')->label('Kategori')
                    ->relationship('product.category', 'name'),
                Filter::make('range')->label('Rentang tanggal')
                    ->schema([DatePicker::make('from')->label('Dari'), DatePicker::make('until')->label('Sampai')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
                Filter::make('humans')->label('Sembunyikan bot')->default()
                    ->query(fn (Builder $q) => $q->where('is_bot', false)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListClickEvents::route('/')];
    }
}
