<?php

namespace App\Filament\Resources\SearchSynonyms;

use App\Filament\Resources\SearchSynonyms\Pages\ManageSearchSynonyms;
use App\Models\SearchSynonym;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Sinonim pencarian situs: istilah pengunjung (hebel, galvalum) diarahkan ke nama di katalog (bata ringan, baja ringan). */
class SearchSynonymResource extends Resource
{
    protected static ?string $model = SearchSynonym::class;

    protected static ?string $modelLabel = 'Sinonim pencarian';

    protected static ?string $pluralModelLabel = 'Sinonim pencarian';

    // Eksplisit agar tetap huruf kalimat seperti menu lain (label jamak otomatis dijadikan Title Case)
    protected static ?string $navigationLabel = 'Sinonim pencarian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canReview();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('term')->label('Kata yang dicari pengunjung')->required()->maxLength(100)
                ->unique(ignoreRecord: true)->placeholder('cth: hebel')
                ->helperText('Huruf besar/kecil tidak berpengaruh. Juga berlaku bila kata ini bagian dari pencarian, mis. "hebel 10 cm".'),
            TagsInput::make('synonyms')->label('Cari juga sebagai')->required()->placeholder('Ketik kata lalu tekan Enter')
                ->splitKeys([','])->nestedRecursiveRules(['max:100'])
                ->helperText('Nama yang dipakai di katalog, mis. "bata ringan". Bisa lebih dari satu.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('term')
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->emptyStateHeading('Belum ada sinonim')
            ->emptyStateDescription('Lihat "Dicari tapi tidak ketemu" di Pusat Laporan untuk ide sinonim.')
            ->columns([
                TextColumn::make('term')->label('Kata dicari')->searchable()->sortable()->weight('medium'),
                TextColumn::make('synonyms')->label('Cari juga sebagai')->badge()->color('gray'),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable()->color('gray'),
            ])
            ->recordActions([
                Action::make('try')->label('Coba')->icon(Heroicon::OutlinedMagnifyingGlass)->color('gray')
                    ->url(fn (SearchSynonym $record) => route('search', ['q' => $record->term]), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSearchSynonyms::route('/')];
    }
}
