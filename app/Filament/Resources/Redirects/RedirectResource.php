<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $modelLabel = 'Redirect';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')->label('Dari (path)')->required()->unique(ignoreRecord: true)->prefix('/')
                ->dehydrateStateUsing(fn ($s) => '/'.ltrim((string) $s, '/')),
            TextInput::make('to_path')->label('Ke (path atau URL)')->required(),
            Select::make('code')->label('Kode')->options([301 => '301 Permanen', 302 => '302 Sementara'])->default(301)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')->label('Dari')->searchable(),
                TextColumn::make('to_path')->label('Ke')->searchable(),
                TextColumn::make('code')->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
