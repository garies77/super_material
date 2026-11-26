<?php

namespace App\Filament\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Template bagian siap pakai untuk page builder: menyisipkan beberapa blok sekaligus dengan susunan yang umum
 * dipakai (pembuka, keunggulan, bukti video, FAQ, ajakan penutup). Isian teks wajib sengaja dikosongkan
 * agar validasi memaksa admin mengisinya, sehingga teks contoh tidak pernah ikut terbit.
 */
class PageSectionTemplates
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function options(): array
    {
        return [
            'intro' => ['label' => 'Pembuka halaman', 'description' => 'Judul bagian, paragraf singkat, dan satu tombol utama.'],
            'highlights' => ['label' => 'Keunggulan', 'description' => 'Judul bagian dan 3 poin unggulan singkat.'],
            'video_proof' => ['label' => 'Bukti video pelanggan', 'description' => 'Judul bagian, paragraf pengantar, dan 3 video TikTok/Reels berjajar.'],
            'faq' => ['label' => 'Tanya-jawab (FAQ)', 'description' => 'Daftar 3 pertanyaan yang bisa dibuka-tutup.'],
            'closing_cta' => ['label' => 'Ajakan penutup', 'description' => 'Judul, paragraf, tombol "Lihat semua produk", dan tombol WhatsApp bila nomornya diisi di Pengaturan situs.'],
        ];
    }

    /**
     * Blok template dalam format state Builder (kunci UUID, tiap blok berisi type + data).
     *
     * @return array<string, array{type: string, data: array<string, mixed>}>
     */
    public static function blocks(string $template): array
    {
        $blocks = match ($template) {
            'intro' => [
                self::heading(),
                ['text', ['content' => null]],
                self::button('', 'primary', '', 'internal'),
            ],
            'highlights' => [
                self::heading(),
                ['highlights', ['items' => self::items(3, ['text' => ''])]],
            ],
            'video_proof' => [
                self::heading(),
                ['text', ['content' => null]],
                ['embed', ['videos' => self::items(3, ['url' => '', 'title' => '']), 'layout' => '3']],
            ],
            'faq' => [
                ['faq', ['heading' => 'Pertanyaan yang sering diajukan', 'items' => self::items(3, ['question' => '', 'answer' => ''])]],
            ],
            'closing_cta' => array_values(array_filter([
                self::heading(),
                ['text', ['content' => null]],
                self::button('Lihat semua produk', 'primary', route('product.index', absolute: false), 'internal'),
                ($wa = self::whatsappUrl()) ? self::button('Tanya via WhatsApp', 'outline', $wa, 'external') : null,
            ])),
            default => [],
        };

        $state = [];
        foreach ($blocks as [$type, $data]) {
            $state[(string) Str::uuid()] = ['type' => $type, 'data' => $data];
        }

        return $state;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private static function heading(): array
    {
        return ['heading', ['level' => 'h2', 'text' => '', 'align' => 'left']];
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private static function button(string $label, string $style, string $url, string $linkType): array
    {
        return ['button', ['label' => $label, 'style' => $style, 'align' => 'left', 'link_type' => $linkType, 'url' => $url, 'new_tab' => $linkType === 'external']];
    }

    /**
     * Item repeater berkunci UUID, sama seperti item yang ditambah lewat tombol "Tambah …".
     *
     * @param  array<string, mixed>  $item
     * @return array<string, array<string, mixed>>
     */
    private static function items(int $count, array $item): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[(string) Str::uuid()] = $item;
        }

        return $items;
    }

    private static function whatsappUrl(): ?string
    {
        $number = preg_replace('/\D+/', '', (string) Setting::get('whatsapp_number'));
        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return $number !== '' ? 'https://wa.me/'.$number : null;
    }
}
