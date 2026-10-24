<?php

namespace App\Support;

use App\Models\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Tujuan tautan yang dipakai blok tombol, Navbar, dan Footer:
 * halaman statis (dipilih), jalur internal (/produk), atau URL eksternal (https://…).
 */
class LinkTarget
{
    public const TYPES = ['page' => 'Halaman statis', 'internal' => 'Jalur internal', 'external' => 'URL eksternal'];

    /** Hanya jalur internal atau http(s)/mailto/tel; javascript: dan sejenisnya ditolak. */
    public const SAFE_URL = '/^(\/(?!\/)|https?:\/\/|mailto:|tel:)/i';

    /** @return array<int, Component> */
    public static function fields(bool $withNewTab = true): array
    {
        return array_filter([
            Select::make('link_type')->label('Jenis tujuan')->options(self::TYPES)->default('internal')->required()->live(),
            Select::make('page_id')->label('Halaman')
                ->options(fn () => Page::orderBy('title')->pluck('title', 'id'))->searchable()
                ->visible(fn (Get $get) => $get('link_type') === 'page')
                ->required(fn (Get $get) => $get('link_type') === 'page'),
            TextInput::make('url')->label(fn (Get $get) => $get('link_type') === 'external' ? 'URL' : 'Jalur')
                ->placeholder(fn (Get $get) => $get('link_type') === 'external' ? 'https://contoh.com' : '/produk')
                ->visible(fn (Get $get) => $get('link_type') !== 'page')
                ->required(fn (Get $get) => $get('link_type') !== 'page')
                ->maxLength(2000)
                ->regex(self::SAFE_URL)
                ->validationMessages(['regex' => 'Gunakan jalur yang diawali / atau URL http(s)://, mailto:, tel:.']),
            $withNewTab ? Toggle::make('new_tab')->label('Buka di tab baru')->default(false)->inline(false) : null,
        ]);
    }

    public static function url(array $item): ?string
    {
        $url = match ($item['link_type'] ?? 'internal') {
            'page' => self::pageUrl($item['page_id'] ?? null),
            default => trim((string) ($item['url'] ?? '')),
        };

        return $url !== '' && $url !== null && preg_match(self::SAFE_URL, $url) ? $url : null;
    }

    public static function isExternal(array $item): bool
    {
        return ($item['link_type'] ?? null) === 'external';
    }

    private static function pageUrl(mixed $id): ?string
    {
        static $slugs = null;
        $slugs ??= Page::published()->pluck('slug', 'id');

        return isset($slugs[$id]) ? route('page.show', $slugs[$id], false) : null;
    }
}
