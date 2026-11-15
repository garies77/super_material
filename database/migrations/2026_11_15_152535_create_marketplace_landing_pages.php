<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contoh landing page per marketplace (Shopee, Tokopedia, Lazada, TikTok Shop) memakai blok "Grid produk marketplace".
 * Dibuat sebagai DRAF agar ditinjau dulu di admin (Halaman → Terbitkan). Grid memakai sumber otomatis per kanal,
 * jadi berisi produk yang ada di database tempat migrasi berjalan. Halaman dengan slug yang sudah ada tidak ditimpa;
 * kanal yang tidak ada dilewati.
 */
return new class extends Migration
{
    private const CHANNELS = [
        'shopee' => ['Shopee', 'jual-peralatan-proyek-di-shopee'],
        'tokopedia' => ['Tokopedia', 'jual-peralatan-proyek-di-tokopedia'],
        'lazada' => ['Lazada', 'jual-peralatan-proyek-di-lazada'],
        'tiktok-shop' => ['TikTok Shop', 'jual-peralatan-proyek-di-tiktok-shop'],
    ];

    public function up(): void
    {
        foreach (self::CHANNELS as $channelSlug => [$name, $pageSlug]) {
            $channelId = DB::table('sales_channels')->where('slug', $channelSlug)->value('id');
            if (! $channelId || DB::table('pages')->where('slug', $pageSlug)->exists()) {
                continue;
            }

            DB::table('pages')->insert([
                'title' => "Supermaterial Jual Peralatan & Bahan Proyek di {$name}",
                'slug' => $pageSlug,
                'is_published' => false,
                'meta_title' => "Jual Peralatan Proyek di {$name}",
                'meta_description' => "Belanja peralatan dan bahan bangunan untuk proyek di {$name} lewat Supermaterial: alat kerja, perkakas, dan material pilihan dengan tombol beli langsung ke toko {$name}.",
                'draft_blocks' => json_encode($this->blocks($name, (int) $channelId), JSON_UNESCAPED_UNICODE),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function down(): void
    {
        // Hanya hapus contoh yang belum pernah diterbitkan; halaman yang sudah dipakai dibiarkan
        DB::table('pages')->whereIn('slug', array_column(self::CHANNELS, 1))->where('is_published', false)->whereNull('blocks')->delete();
    }

    /** @return array<int, array{type: string, data: array<string, mixed>}> */
    private function blocks(string $name, int $channelId): array
    {
        $text = fn (string $html) => ['type' => 'text', 'data' => ['content' => $html]];
        $heading = fn (string $value) => ['type' => 'heading', 'data' => ['text' => $value, 'level' => 'h2', 'align' => 'left']];

        return [
            $text("<p>Butuh peralatan untuk proyek konstruksi, renovasi rumah, atau pekerjaan bengkel? Supermaterial mengumpulkan peralatan kerja, perkakas, dan bahan bangunan pilihan yang bisa Anda beli langsung di <strong>{$name}</strong>. Lihat spesifikasi di halaman produk kami, lalu selesaikan pembelian di toko {$name} dengan satu klik.</p>"),

            ['type' => 'products', 'data' => [
                'heading' => "Peralatan & Bahan Proyek Pilihan di {$name}",
                'intro' => "Klik nama produk untuk melihat spesifikasi lengkap, atau tombol \"Beli di {$name}\" untuk langsung ke halaman toko.",
                'channel_id' => $channelId,
                'source' => 'channel',
                'sort' => 'featured',
                'limit' => 24,
                'columns' => '4',
                'show_price' => true,
            ]],

            $heading("Kenapa belanja kebutuhan proyek lewat Supermaterial di {$name}?"),
            ['type' => 'list', 'data' => ['style' => 'bullet', 'items' => [
                ['text' => 'Informasi produk lengkap di satu tempat: spesifikasi, ukuran, dan kegunaan untuk tiap jenis pekerjaan.'],
                ['text' => "Pembayaran, pengiriman, dan perlindungan pembeli mengikuti ketentuan {$name} yang sudah Anda kenal."],
                ['text' => 'Pilihan lengkap untuk proyek: perkakas tangan, alat listrik, alat ukur, perlengkapan keselamatan, dan material.'],
                ['text' => 'Bingung memilih? Tim kami siap membantu lewat WhatsApp sebelum Anda membeli.'],
            ]]],

            $heading("Cara belanja di {$name}"),
            ['type' => 'list', 'data' => ['style' => 'number', 'items' => [
                ['text' => 'Pilih produk di daftar di atas dan baca detailnya bila perlu.'],
                ['text' => "Klik tombol \"Beli di {$name}\" untuk membuka halaman produk di toko {$name}."],
                ['text' => "Atur jumlah atau varian, lalu selesaikan pembayaran di aplikasi atau situs {$name}."],
            ]]],

            $heading('Pertanyaan yang sering diajukan'),
            $text(
                "<h3>Apakah harga di sini sama dengan di {$name}?</h3>"
                ."<p>Harga yang tampil adalah harga acuan. Harga final, promo, dan ongkos kirim mengikuti halaman toko di {$name} saat Anda membeli.</p>"
                .'<h3>Bagaimana kalau produk yang saya cari tidak ada?</h3>'
                .'<p>Gunakan pencarian di situs ini atau hubungi kami lewat WhatsApp. Kami bantu carikan produk yang sesuai kebutuhan proyek Anda.</p>'
                .'<h3>Bisa beli dalam jumlah banyak untuk proyek?</h3>'
                ."<p>Bisa. Untuk pembelian partai besar, hubungi kami terlebih dahulu agar ketersediaan stok dan pengiriman bisa dipastikan sebelum checkout di {$name}.</p>"
            ),

            ['type' => 'button', 'data' => ['label' => 'Lihat semua produk', 'style' => 'outline', 'align' => 'center', 'link_type' => 'internal', 'url' => '/produk', 'new_tab' => false]],
        ];
    }
};
