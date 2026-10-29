<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Support\CustomCodeFields;
use App\Filament\Support\PageBlocks;
use App\Filament\Support\SeoFields;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Halaman statis dengan page builder. Buat dan sunting di halaman editor tersendiri (bukan modal);
 * konten disimpan sebagai draf sampai dipublikasikan.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $slug = 'pages';

    protected static ?string $modelLabel = 'Halaman';

    protected static ?string $pluralModelLabel = 'Halaman';

    protected static ?string $navigationLabel = 'Halaman';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        // Kiri: isi halaman, Custom HTML, SEO. Kanan: status & riwayat publikasi. Satu kolom di bawah xl.
        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 8, '2xl' => 9])->schema([
                Section::make('Halaman')->schema([
                    TextInput::make('title')->label('Judul')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->prefix(fn () => url('/').'/')
                        ->helperText('Mengubah slug halaman terbit otomatis membuat redirect 301 dari URL lama.'),
                    PageBlocks::make(),
                ]),
                CustomCodeFields::make(),
                SeoFields::make(true),
            ]),

            Group::make()->columnSpan(['xl' => 4, '2xl' => 3])->schema([
                Section::make('Status terbit')->schema([
                    Text::make(fn (?Page $record) => match (true) {
                        ! $record => 'Belum disimpan',
                        ! $record->is_published => 'Draf',
                        $record->hasUnpublishedChanges() => 'Terbit · ada perubahan draf',
                        default => 'Terbit',
                    })->badge()->color(fn (?Page $record) => match (true) {
                        ! $record?->is_published => 'gray',
                        $record->hasUnpublishedChanges() => 'warning',
                        default => 'success',
                    }),
                    Text::make(fn (?Page $record) => match (true) {
                        ! $record => 'Halaman belum tampil di situs. "Simpan draf" menyimpan tanpa menerbitkan; "Terbitkan" menampilkannya di situs.',
                        ! $record->is_published => 'Belum tampil di situs. Tekan "Terbitkan" bila sudah siap.',
                        $record->hasUnpublishedChanges() => 'Pengunjung masih melihat versi terbit. Tekan "Terbitkan" untuk menampilkan perubahan.',
                        default => 'Pengunjung melihat versi yang sama dengan isi editor.',
                    }),
                    Actions::make([
                        Action::make('openPublicPage')->label('Lihat halaman di situs')->icon('heroicon-o-arrow-top-right-on-square')
                            ->link()->url(fn (?Page $record) => $record?->url(), true),
                    ])->visible(fn (?Page $record) => (bool) $record?->is_published),
                    Text::make(fn (?Page $record) => 'Terakhir diubah '.$record?->updated_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i').' WIB'
                        .($record?->editor ? ' oleh '.$record->editor->name : ''))
                        ->visible(fn (?Page $record) => (bool) $record?->updated_at),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->sortable(),
                TextColumn::make('slug')->prefix('/'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Page $r) => ! $r->is_published ? 'Draf' : ($r->hasUnpublishedChanges() ? 'Ada perubahan draf' : 'Terbit'))
                    ->color(fn (string $state) => match ($state) {
                        'Terbit' => 'success', 'Draf' => 'gray', default => 'warning'
                    }),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('preview')->label('Pratinjau')->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (Page $r) => route('page.preview', $r))->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
