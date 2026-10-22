<?php

namespace App\Filament\Support;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class CustomCodeFields
{
    /** Blok "Custom HTML" bertab HTML / CSS / JavaScript (mirip blok Custom HTML WordPress). */
    public static function make(): Section
    {
        return Section::make('Custom HTML')
            ->description('Sisipkan HTML, CSS, dan JavaScript kustom (tabel, embed, kalkulator, dsb). Tampil di bawah isi pada halaman publik.')
            ->collapsed()
            ->columnSpanFull()
            ->schema([
                Tabs::make('custom_code_tabs')->tabs([
                    Tab::make('HTML')->schema([
                        CodeEditor::make('custom_html')->label('HTML')->language(Language::Html)->columnSpanFull(),
                    ]),
                    Tab::make('CSS')->schema([
                        CodeEditor::make('custom_css')->label('CSS')->language(Language::Css)->columnSpanFull(),
                    ]),
                    Tab::make('JavaScript')->schema([
                        CodeEditor::make('custom_js')->label('JavaScript')->language(Language::JavaScript)->columnSpanFull(),
                    ]),
                ])->columnSpanFull(),
            ]);
    }
}
