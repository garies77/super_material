<?php

namespace App\Filament\Resources\SalesChannels;

use App\Filament\Resources\SalesChannels\Pages\ManageSalesChannels;
use App\Models\SalesChannel;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SalesChannelResource extends Resource
{
    protected static ?string $model = SalesChannel::class;

    protected static ?string $modelLabel = 'Kanal beli';

    protected static ?string $pluralModelLabel = 'Kanal beli';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)
                ->helperText('Dipakai di URL /go/{produk}/{slug}.'),
            Select::make('type')->label('Tipe')->options(['marketplace' => 'Marketplace', 'whatsapp' => 'WhatsApp'])->required()->default('marketplace'),
            ColorPicker::make('color')->label('Warna tombol'),
            TagsInput::make('allowed_hosts')->label('Domain yang diizinkan')
                ->helperText('Contoh: shopee.co.id, tokopedia.com. Redirect hanya ke domain ini (anti open redirect). Tidak perlu untuk WhatsApp.'),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('type')->label('Tipe')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSalesChannels::route('/')];
    }
}
