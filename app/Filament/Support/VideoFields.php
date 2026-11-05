<?php

namespace App\Filament\Support;

use App\Support\Seo\VideoDuration;
use App\Support\Seo\VideoSchema;
use App\Support\YouTube;
use App\Support\YouTubeMetadata;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Field video bersama (artikel, produk) yang mengikuti properti VideoObject Google:
 * judul, deskripsi, tanggal unggah, durasi, thumbnail, dan momen penting (Clip).
 * Nama kolom bisa berbeda per tabel (mis. product_media memakai path/alt_text/poster_path).
 */
class VideoFields
{
    /**
     * @return array<int, Component|Field>
     */
    public static function make(
        string $urlField = 'url',
        string $titleField = 'title',
        string $thumbnailField = 'thumbnail_path',
        bool $titleRequired = true,
        bool $withPrimaryToggle = false,
    ): array {
        return array_values(array_filter([
            TextInput::make($urlField)->label('URL video YouTube')->required()->maxLength(500)->columnSpanFull()
                ->placeholder('https://www.youtube.com/watch?v=…')
                ->regex(YouTube::URL_RULE)
                ->validationMessages(['regex' => 'Masukkan URL video YouTube yang valid.'])
                ->live(onBlur: true)
                ->suffixAction(self::fetchAction($urlField, $titleField)),

            TextInput::make($titleField)->label('Judul video')->maxLength(255)->required($titleRequired)
                ->placeholder('cth: Cara memasang keramik 60x60')->live(onBlur: true),

            DateTimePicker::make('upload_date')->label('Tanggal unggah video (WIB)')->seconds(false)->timezone('Asia/Jakarta')
                ->helperText('Lihat di YouTube, di bawah judul video. Kosong = tanggal video ditambahkan di sini.'),

            Textarea::make('description')->label('Deskripsi video')->rows(3)->maxLength(5000)->columnSpanFull()
                ->live(onBlur: true)
                ->helperText('Tampil di bawah video dan dipakai Google. Tulis yang unik untuk video ini; kosong = judul video + ringkasan halaman.'),

            TextInput::make('creator_name')->label('Channel / pembuat video')->maxLength(255)
                ->placeholder('Terisi otomatis dari YouTube')
                ->helperText('Dipakai sebagai creator di Google. Kosong = tidak dicantumkan.'),

            TextInput::make('creator_url')->label('URL channel')->url()->maxLength(500)
                ->placeholder('https://www.youtube.com/@…'),

            TextInput::make('duration_seconds')->label('Durasi')->placeholder('4:32')
                ->formatStateUsing(fn ($state) => VideoDuration::format(is_numeric($state) ? (int) $state : null))
                ->dehydrateStateUsing(fn ($state) => VideoDuration::parse($state))
                ->rule(self::durationRule())
                ->live(onBlur: true)
                ->helperText('Format menit:detik atau jam:menit:detik.'),

            TextInput::make($thumbnailField)->label('Thumbnail kustom (opsional)')->maxLength(500)
                ->placeholder('Kosong = thumbnail dari YouTube')
                ->hintAction(MediaPicker::action('media')),

            $withPrimaryToggle
                ? Toggle::make('is_primary')->label('Video utama (tampil di atas artikel)')->columnSpanFull()
                    ->helperText('Aktifkan bila video adalah inti konten, mis. tutorial. Google hanya menampilkan video yang menjadi konten utama halaman. Hanya satu video utama.')
                : null,

            Repeater::make('clips')->label('Momen penting (chapter)')->columnSpanFull()
                ->defaultItems(0)->collapsed()->columns(3)->addActionLabel('Tambah momen')
                ->itemLabel(fn (array $state) => trim(($state['start'] ?? '').' '.($state['name'] ?? '')) ?: null)
                ->helperText('Opsional, hanya untuk video minimal 30 detik. Contoh: 0:00 Persiapan alat, 1:30 Mengaduk semen. Google bisa menampilkannya sebagai "Momen penting".')
                ->live()
                ->schema([
                    TextInput::make('name')->label('Judul momen')->required()->maxLength(120),
                    TextInput::make('start')->label('Mulai')->placeholder('1:30')->required()
                        ->formatStateUsing(fn ($state) => is_numeric($state) ? VideoDuration::format((int) $state) : $state)
                        ->dehydrateStateUsing(fn ($state) => VideoDuration::parse($state))
                        ->rule(self::durationRule()),
                    TextInput::make('end')->label('Selesai (opsional)')->placeholder('3:10')
                        ->formatStateUsing(fn ($state) => is_numeric($state) ? VideoDuration::format((int) $state) : $state)
                        ->dehydrateStateUsing(fn ($state) => VideoDuration::parse($state))
                        ->rule(self::durationRule()),
                ]),

            Text::make(fn (Get $get) => self::status($get, $urlField, $titleField))->columnSpanFull(),
        ]));
    }

    /** Tombol "Ambil dari YouTube" (oEmbed, tanpa API key): judul & channel, tanpa menimpa isian yang sudah ada. */
    private static function fetchAction(string $urlField, string $titleField): Action
    {
        return Action::make('fetchYouTubeMetadata')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->tooltip('Ambil judul & channel dari YouTube')
            ->action(function (Get $get, Set $set) use ($urlField, $titleField) {
                $videoId = YouTube::videoId($get($urlField));
                if (! $videoId) {
                    Notification::make()->title('Masukkan URL YouTube yang valid dulu')->warning()->send();

                    return;
                }

                $meta = YouTubeMetadata::fetch($videoId);
                if (! ($meta['title'] ?? null)) {
                    Notification::make()->title('Data video tidak bisa diambil')
                        ->body('Pastikan video publik atau unlisted, lalu isi manual.')->danger()->send();

                    return;
                }

                foreach ([$titleField => Str::limit($meta['title'], 255, ''), 'creator_name' => $meta['author_name'], 'creator_url' => $meta['author_url']] as $field => $value) {
                    if (filled($value) && blank($get($field))) {
                        $set($field, $value);
                    }
                }

                Notification::make()->title('Judul & channel terisi dari YouTube')->success()->send();
            });
    }

    private static function durationRule(): Closure
    {
        return fn () => function (string $attribute, mixed $value, Closure $fail) {
            if (filled($value) && VideoDuration::parse($value) === null) {
                $fail('Gunakan format menit:detik, mis. 4:32.');
            }
        };
    }

    private static function status(Get $get, string $urlField, string $titleField): string
    {
        $missing = array_keys(array_filter([
            'URL YouTube' => ! YouTube::videoId($get($urlField)),
            'judul' => blank($get($titleField)) && $titleField === 'title',
        ]));

        if ($missing) {
            return '⚠ Belum muncul di Google: isi '.implode(', ', $missing).'.';
        }

        $duration = VideoDuration::parse($get('duration_seconds'));
        $tips = array_filter([
            blank($get('description')) ? 'isi deskripsi yang unik untuk video ini' : null,
            filled($get('clips')) && $duration === null ? 'isi durasi agar momen penting bisa diperiksa' : null,
            filled($get('clips')) && $duration !== null && $duration < VideoSchema::MIN_CLIP_VIDEO_SECONDS
                ? 'momen penting diabaikan karena video di bawah 30 detik' : null,
        ]);

        return '✓ Data video lengkap untuk Google (VideoObject).'.($tips ? ' Saran: '.implode('; ', $tips).'.' : '');
    }
}
