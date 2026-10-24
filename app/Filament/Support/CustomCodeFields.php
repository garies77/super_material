<?php

namespace App\Filament\Support;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class CustomCodeFields
{
    /**
     * Blok "Custom HTML" bertab HTML / CSS / JavaScript. Kode tampil langsung di halaman publik (tanpa iframe),
     * jadi hanya Admin atau Editor/Penulis yang diberi izin di menu Pengguna yang melihat blok ini.
     * Field tersembunyi tidak ikut disimpan, dan model menolak perubahan dari pengguna tanpa izin (GuardsCustomCode).
     */
    public static function make(): Section
    {
        return Section::make('Custom HTML')
            ->description('Kode dijalankan langsung di halaman publik, cocok untuk JSON-LD, tag pelacakan, atau widget. CSS otomatis dibatasi ke blok ini. Atribut onclick dan sejenisnya diblokir oleh CSP; pakai tab JavaScript.')
            ->collapsed()
            ->columnSpanFull()
            ->visible(fn () => auth()->user()?->canManageCustomHtml() ?? false)
            ->schema([
                Tabs::make('custom_code_tabs')->tabs([
                    Tab::make('HTML')->schema([
                        CodeEditor::make('custom_html')->label('HTML')->language(Language::Html)->columnSpanFull()
                            ->helperText('Boleh berisi <script>, termasuk <script type="application/ld+json">. Nonce CSP ditambahkan otomatis.'),
                    ]),
                    Tab::make('CSS')->schema([
                        CodeEditor::make('custom_css')->label('CSS')->language(Language::Css)->columnSpanFull()
                            ->helperText('Hanya berlaku di dalam blok Custom HTML ini, tidak memengaruhi bagian lain halaman.'),
                    ]),
                    Tab::make('JavaScript')->schema([
                        CodeEditor::make('custom_js')->label('JavaScript')->language(Language::JavaScript)->columnSpanFull()
                            ->helperText('Dijalankan setelah blok HTML, dengan akses penuh ke halaman (DOM, head, dataLayer).'),
                    ]),
                ])->columnSpanFull(),
            ]);
    }
}
