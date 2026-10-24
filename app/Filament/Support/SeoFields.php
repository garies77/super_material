<?php

namespace App\Filament\Support;

use App\Http\Controllers\SeoController;
use App\Rules\MinImagePixels;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

class SeoFields
{
    public static function make(bool $withImage = true): Section
    {
        $fields = [
            Text::make(fn (Get $get) => self::preview($get))->columnSpanFull(),
            TextInput::make('meta_title')->label('Judul meta')->maxLength(70)
                ->helperText('Ideal 50–60 karakter. Kosong = dibuat otomatis dari nama.')->live(onBlur: true),
            Textarea::make('meta_description')->label('Deskripsi meta')->rows(3)->maxLength(320)
                ->helperText('Ideal 140–158 karakter. Kosong = dibuat otomatis dari ringkasan.')->live(onBlur: true),
            TextInput::make('canonical_url')->label('URL kanonik')->url()->helperText('Opsional; kosong = URL halaman ini.'),
            Toggle::make('noindex')
                ->label('Noindex (sembunyikan dari Google)')
                ->helperText('Perayap dapat membaca meta noindex. Halaman yang diblokir di robots.txt Disallow dilarang ditandai noindex (PRD 9.11.3).')
                ->rules([
                    fn (Get $get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                        if ($value) {
                            $slug = (string) ($get('slug') ?? ($record?->slug ?? ''));
                            $path = '/'.ltrim($slug, '/');
                            foreach (SeoController::DISALLOW as $disallowed) {
                                if (str_starts_with($path, $disallowed)) {
                                    $fail("Halaman ini beririsan dengan daftar Disallow di robots.txt ({$disallowed}). Sesuai pedoman Google Search Central (PRD 9.11.3 & SEO-03), halaman noindex harus tetap dapat dirayapi dan tidak boleh masuk daftar Disallow.");
                                }
                            }
                        }
                    },
                ]),
            Toggle::make('nofollow')
                ->label('Nofollow (jangan ikuti tautan di halaman ini)')
                ->helperText('Noindex + nofollow setara dengan direktif "none".'),
        ];

        if ($withImage) {
            array_splice($fields, 3, 0, [
                FileUpload::make('og_image')
                    ->label('Gambar Open Graph')
                    ->image()
                    ->disk('public')
                    ->directory('og')
                    ->visibility('public')
                    ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('slug', 'og', 'og'))
                    ->rule(new MinImagePixels(50000))
                    ->helperText('Resolusi minimal 50.000 piksel (disarankan 1200×630).'),
            ]);
        }

        return Section::make('SEO')->icon('heroicon-o-magnifying-glass')->collapsed()->schema($fields)->columns(1);
    }

    private static function preview(Get $get): HtmlString
    {
        $title = (string) $get('meta_title');
        $desc = (string) $get('meta_description');
        $slug = (string) ($get('slug') ?? '');

        $checks = [
            [$title !== '' && mb_strlen($title) >= 30 && mb_strlen($title) <= 65, 'Judul meta 30–65 karakter'],
            [$desc !== '' && mb_strlen($desc) >= 80 && mb_strlen($desc) <= 160, 'Deskripsi meta 80–160 karakter'],
            [! empty($get('og_image')) || ! empty($get('image')), 'Gambar tersedia'],
        ];
        $list = collect($checks)->map(fn ($c) => ($c[0] ? '✅ ' : '⚠️ ').e($c[1]))->implode('<br>');

        $t = e($title ?: 'Judul halaman akan dibuat otomatis');
        $d = e($desc ?: 'Deskripsi akan dibuat otomatis dari ringkasan konten.');
        $host = e(parse_url(config('app.url'), PHP_URL_HOST) ?: 'supermaterial');

        return new HtmlString(
            '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#fff;color:#202124">'
            .'<div style="font-size:12px;color:#4d5156">'.$host.' › '.e($slug).'</div>'
            .'<div style="font-size:18px;color:#1a0dab">'.$t.'</div>'
            .'<div style="font-size:13px;color:#4d5156">'.$d.'</div></div>'
            .'<div style="margin-top:8px;font-size:13px">'.$list.'</div>'
        );
    }
}
