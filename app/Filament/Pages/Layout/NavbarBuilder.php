<?php

namespace App\Filament\Pages\Layout;

use App\Support\LinkTarget;
use App\Support\SiteLayout;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Menu navigasi utama situs: tambah, ubah, hapus, urutkan (drag), aktif/nonaktif, dan submenu satu tingkat. */
class NavbarBuilder extends LayoutBuilderPage
{
    protected static ?string $title = 'Navbar Builder';

    protected static ?string $slug = 'navbar-builder';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?int $navigationSort = 2;

    protected function settingKey(): string
    {
        return SiteLayout::NAVBAR_KEY;
    }

    protected function defaults(): array
    {
        return ['items' => [
            ['label' => 'Produk', 'link_type' => 'internal', 'url' => '/produk', 'is_active' => true, 'children' => []],
            ['label' => 'Artikel', 'link_type' => 'internal', 'url' => '/artikel', 'is_active' => true, 'children' => []],
        ]];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Menu navigasi')
                ->description('Seret untuk mengubah urutan. Tombol pencarian selalu tampil di ujung kanan.')
                ->schema([
                    Repeater::make('items')->hiddenLabel()
                        ->schema(self::itemFields(withChildren: true))
                        ->reorderableWithDragAndDrop()->collapsible()->cloneable()
                        ->itemLabel(fn (array $state) => ($state['label'] ?? 'Menu baru').(($state['is_active'] ?? true) ? '' : ' (nonaktif)'))
                        ->addActionLabel('Tambah menu')->maxItems(12)->defaultItems(0),
                ]),
        ]);
    }

    /** @return array<int, Component> */
    public static function itemFields(bool $withChildren = false): array
    {
        return array_filter([
            Grid::make(4)->schema([
                TextInput::make('label')->label('Label')->required()->maxLength(40)->live(onBlur: true),
                ...LinkTarget::fields(),
            ]),
            Toggle::make('is_active')->label('Aktif')->default(true),
            $withChildren ? Repeater::make('children')->label('Submenu (dropdown)')
                ->schema(self::itemFields())
                ->reorderableWithDragAndDrop()->collapsible()
                ->itemLabel(fn (array $state) => $state['label'] ?? 'Submenu baru')
                ->addActionLabel('Tambah submenu')->maxItems(12)->defaultItems(0) : null,
        ]);
    }
}
