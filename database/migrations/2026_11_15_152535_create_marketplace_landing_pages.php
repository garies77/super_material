<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contoh landing page per marketplace (Shopee, Tokopedia, Lazada, TikTok Shop) memakai blok "Grid produk marketplace".
 * Copy sengaja umum (tidak menyebut nama barang) dan diperkaya temuan riset copywriting & SEO:
 * poin unggulan, penjelasan harga acuan, bagian "yang perlu Anda tahu" + cara belanja + sebagian FAQ yang khas
 * tiap marketplace, CTA WhatsApp, dan tautan antar-marketplace. Tanpa klaim yang belum dikonfirmasi pemilik
 * (toko resmi, garansi, gratis ongkir, termurah).
 *
 * Dibuat sebagai DRAF (Halaman → Terbitkan). Grid memakai sumber otomatis per kanal, jadi berisi produk yang ada
 * di database tempat migrasi berjalan. Halaman dengan slug yang sudah ada tidak ditimpa; kanal yang tidak ada dilewati.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pages = $this->pages();
        $whatsapp = $this->whatsappNumber();

        foreach ($pages as $channelSlug => $page) {
            $channelId = DB::table('sales_channels')->where('slug', $channelSlug)->value('id');
            if (! $channelId || DB::table('pages')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            $others = collect($pages)->except($channelSlug)
                ->filter(fn ($p, $slug) => DB::table('sales_channels')->where('slug', $slug)->exists())
                ->map(fn ($p) => '<a href="/'.$p['slug'].'">'.$p['name'].'</a>')->implode(' · ');

            $name = $page['name'];
            DB::table('pages')->insert([
                'title' => "Supermaterial Jual Peralatan & Bahan Proyek di {$name}",
                'slug' => $page['slug'],
                'is_published' => false,
                'meta_title' => "Jual Peralatan & Bahan Proyek di {$name}",
                'meta_description' => "Belanja peralatan dan bahan bangunan untuk proyek di {$name} lewat Supermaterial. Cek spesifikasi dan harga acuan, lalu beli langsung di toko {$name}.",
                'draft_blocks' => json_encode($this->blocks($page, (int) $channelId, $whatsapp, $others), JSON_UNESCAPED_UNICODE),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function down(): void
    {
        // Hanya hapus contoh yang belum pernah diterbitkan; halaman yang sudah dipakai dibiarkan
        DB::table('pages')->whereIn('slug', array_column($this->pages(), 'slug'))->where('is_published', false)->whereNull('blocks')->delete();
    }

    /** @return array<int, array{type: string, data: array<string, mixed>}> */
    private function blocks(array $page, int $channelId, ?string $whatsapp, string $others): array
    {
        $name = $page['name'];
        $text = fn (string $html) => ['type' => 'text', 'data' => ['content' => $html]];
        $heading = fn (string $value) => ['type' => 'heading', 'data' => ['text' => $value, 'level' => 'h2', 'align' => 'left']];
        $list = fn (string $style, array $items) => ['type' => 'list', 'data' => ['style' => $style, 'items' => array_map(fn ($t) => ['text' => $t], $items)]];

        // FAQ umum di semua halaman + pertanyaan khas tiap marketplace
        $faq = [
            ["Apakah harga di Supermaterial sama dengan di {$name}?", "Belum tentu. Harga di situs ini adalah harga acuan untuk membantu Anda memperkirakan anggaran. Harga final, promo, dan ongkos kirim mengikuti halaman toko di {$name} saat Anda membeli."],
            ...$page['faq'],
            ['Bagaimana kalau produk yang saya cari tidak ada?', 'Gunakan pencarian di situs ini atau hubungi kami lewat WhatsApp. Kami bantu carikan produk yang sesuai kebutuhan proyek Anda.'],
            ['Bisa beli dalam jumlah banyak untuk proyek?', "Bisa. Untuk kebutuhan dalam jumlah banyak, hubungi kami lewat WhatsApp terlebih dahulu agar ketersediaan stok dan pengiriman bisa dipastikan sebelum checkout di {$name}."],
        ];

        return array_values(array_filter([
            $text("<p>Butuh peralatan untuk proyek konstruksi, renovasi rumah, atau pekerjaan bengkel? Supermaterial mengumpulkan peralatan kerja, perkakas, dan bahan bangunan pilihan yang bisa Anda beli langsung di <strong>{$name}</strong>. Cek spesifikasi dan harga acuan di halaman produk kami, lalu selesaikan pembelian di toko {$name} dengan satu klik. Harga final, ongkir, dan promo mengikuti {$name}.</p>"),

            ['type' => 'highlights', 'data' => ['items' => array_map(fn ($t) => ['text' => $t], [
                "Harga acuan, harga final di {$name}",
                'Spesifikasi lengkap di tiap produk',
                'Tanya dulu via WhatsApp',
            ])]],

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
            $list('bullet', [
                'Informasi produk lengkap di satu tempat: spesifikasi, ukuran, dan kegunaan untuk tiap jenis pekerjaan.',
                'Harga acuan ditampilkan apa adanya, jadi Anda bisa memperkirakan anggaran sebelum membuka '.$name.'.',
                "Pembayaran, pengiriman, dan perlindungan pembeli mengikuti ketentuan {$name} yang sudah Anda kenal.",
                'Pilihan untuk berbagai tahap proyek: perkakas tangan, alat listrik, alat ukur, perlengkapan kerja, dan material.',
                'Bingung memilih? Tim kami siap membantu lewat WhatsApp sebelum Anda membeli.',
            ]),

            $heading("Belanja di {$name}: yang perlu Anda tahu"),
            $text('<p>'.$page['note'].'</p>'),

            $heading("Cara belanja di {$name}"),
            $list('number', $page['steps']),

            $heading('Tips belanja kebutuhan proyek secara online'),
            $list('bullet', [
                'Baca spesifikasi dan ukuran di halaman produk sebelum membeli, agar barang sesuai pekerjaan.',
                'Hitung kebutuhan dulu, lalu lebihkan sedikit untuk cadangan bila barangnya habis pakai.',
                'Untuk barang berat atau panjang, cek pilihan kurir dan area pengiriman toko sebelum checkout.',
                "Bandingkan harga acuan di sini dengan harga final di {$name}, termasuk ongkir dan voucher yang berlaku.",
                'Periksa dan uji barang begitu paket tiba, sebelum mengonfirmasi pesanan diterima.',
            ]),

            $heading('Tentang harga acuan'),
            $text("<p>Harga di Supermaterial adalah <strong>harga acuan</strong>. Harga final, promo, ongkos kirim, dan stok selalu mengikuti halaman toko di {$name} saat checkout. Transaksi, pembayaran, dan pengiriman diproses oleh {$name}.</p>"),

            ['type' => 'faq', 'data' => ['heading' => 'Pertanyaan yang sering diajukan', 'items' => array_map(
                fn ($qa) => ['question' => $qa[0], 'answer' => $qa[1]],
                $faq,
            )]],

            $text('<p><strong>'.$page['cta'].'</strong></p>'),
            ['type' => 'button', 'data' => ['label' => 'Lihat semua produk', 'style' => 'primary', 'align' => 'left', 'link_type' => 'internal', 'url' => '/produk', 'new_tab' => false]],
            $whatsapp ? ['type' => 'button', 'data' => [
                'label' => 'Tanya dulu via WhatsApp', 'style' => 'outline', 'align' => 'left', 'link_type' => 'external', 'new_tab' => true,
                'url' => 'https://wa.me/'.$whatsapp.'?text='.rawurlencode("Halo Supermaterial, saya mau tanya soal produk di {$name}."),
            ]] : null,
            $others ? $text("<p>Belanja di marketplace lain: {$others}</p>") : null,
        ]));
    }

    /** Nomor WhatsApp dari Pengaturan situs dalam format internasional (62…), atau null bila kosong. */
    private function whatsappNumber(): ?string
    {
        $raw = (string) DB::table('settings')->where('key', 'whatsapp_number')->value('value');
        // Nilai bisa tersimpan polos ("0812…") atau sebagai string JSON ("\"0812…\""); jangan decode angka (nol depan hilang)
        $decoded = json_decode($raw, true);
        $number = preg_replace('/\D+/', '', is_string($decoded) ? $decoded : $raw);
        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return $number !== '' ? $number : null;
    }

    /** @return array<string, array<string, mixed>> bagian yang khas tiap marketplace (slug kanal => isi) */
    private function pages(): array
    {
        return [
            'shopee' => [
                'name' => 'Shopee',
                'slug' => 'jual-peralatan-proyek-di-shopee',
                'note' => 'Di Shopee, pilihan pembayaran seperti SPayLater dan voucher ongkir tergantung akun Anda dan program yang diikuti toko. Cek di halaman checkout sebelum membayar. Saat paket tiba, periksa barang sebelum menekan "Pesanan Diterima".',
                'steps' => [
                    'Pilih produk di daftar di atas dan buka halaman detailnya untuk cek spesifikasi.',
                    'Tekan "Beli di Shopee". Halaman produk terbuka di aplikasi atau situs Shopee.',
                    'Periksa varian, jumlah, ongkir, dan voucher yang berlaku.',
                    'Pilih metode pembayaran, lalu selesaikan pesanan.',
                    'Saat paket tiba, cek kondisi barang sebelum menekan "Pesanan Diterima".',
                ],
                'faq' => [
                    ['Bisa bayar cicilan di Shopee?', 'Shopee menyediakan SPayLater dan cicilan kartu kredit. Ketersediaannya tergantung akun Anda dan pengaturan toko, dan bisa dicek saat checkout.'],
                    ['Barang rusak saat diterima, apa yang harus dilakukan?', 'Jangan tekan "Pesanan Diterima" dulu. Dokumentasikan kondisi barang, lalu ajukan pengembalian lewat fitur di Shopee sesuai ketentuan yang berlaku.'],
                ],
                'cta' => 'Sudah menemukan yang cocok? Lanjutkan ke Shopee, atau lihat semua produk Supermaterial dulu.',
            ],

            'tokopedia' => [
                'name' => 'Tokopedia',
                'slug' => 'jual-peralatan-proyek-di-tokopedia',
                'note' => 'Di Tokopedia, label Gratis Ongkir hanya berlaku pada toko yang ikut program tersebut. Jika Anda pelanggan Tokopedia PLUS, manfaat ongkirnya bisa berlaku pada toko yang ikut program. Cek label di halaman produk dan rincian biaya di halaman checkout sebelum membayar.',
                'steps' => [
                    'Pilih produk di daftar di atas dan pastikan spesifikasinya sesuai.',
                    'Tekan "Beli di Tokopedia".',
                    'Di Tokopedia, pilih varian, cek ongkir, kupon, dan estimasi tiba.',
                    'Bayar dengan metode yang tersedia di akun Anda.',
                    'Lacak pesanan di aplikasi Tokopedia; cek dan uji barang saat diterima.',
                ],
                'faq' => [
                    ['Apakah semua produk dapat Gratis Ongkir di Tokopedia?', 'Tidak selalu. Gratis Ongkir hanya berlaku pada toko yang ikut program tersebut dan sesuai ketentuan Tokopedia. Cek labelnya di halaman produk.'],
                    ['Bagaimana cara melacak pesanan?', 'Setelah membayar, status dan nomor resi bisa dipantau di menu pesanan aplikasi Tokopedia.'],
                ],
                'cta' => 'Bandingkan dulu, lalu beli di Tokopedia saat sudah yakin.',
            ],

            'lazada' => [
                'name' => 'Lazada',
                'slug' => 'jual-peralatan-proyek-di-lazada',
                'note' => 'Di Lazada, ketentuan pengembalian dan pilihan pembayaran, termasuk bayar di tempat, bisa berbeda per toko dan produk. Selalu cek bagian layanan di halaman produk Lazada sebelum checkout.',
                'steps' => [
                    'Pilih produk di daftar di atas dan cek spesifikasinya.',
                    'Tekan "Beli di Lazada".',
                    'Cek harga, voucher, opsi pengiriman, dan ketentuan pengembalian toko.',
                    'Pilih pembayaran yang tersedia di akun Anda.',
                    'Terima dan periksa barang; ajukan pengembalian lewat Lazada bila ada masalah.',
                ],
                'faq' => [
                    ['Apakah bisa bayar di tempat (COD) di Lazada?', 'Lazada menyediakan opsi bayar di tempat, tetapi ketersediaannya tergantung produk, toko, dan alamat. Cek di halaman checkout.'],
                    ['Berapa lama batas pengembalian barang di Lazada?', 'Tergantung jenis toko dan produk. Lihat keterangan pengembalian di halaman produk Lazada sebelum membeli.'],
                ],
                'cta' => 'Lengkapi kebutuhan proyek Anda, lalu checkout di Lazada.',
            ],

            'tiktok-shop' => [
                'name' => 'TikTok Shop',
                'slug' => 'jual-peralatan-proyek-di-tiktok-shop',
                'note' => 'Di TikTok Shop, Anda bisa melihat produk lewat video atau LIVE penjual sebelum membeli, lalu checkout lewat keranjang kuning. Gunakan halaman ini untuk mencocokkan spesifikasinya dulu. Harga di video atau LIVE bisa berbeda; yang berlaku adalah harga saat checkout.',
                'steps' => [
                    'Pilih produk di daftar di atas dan cek spesifikasinya.',
                    'Tekan "Beli di TikTok Shop". Halaman produk terbuka di aplikasi TikTok.',
                    'Pilih varian, cek voucher dan ongkir.',
                    'Selesaikan pembayaran.',
                    'Pantau pesanan di menu pesanan TikTok Shop; periksa barang saat diterima.',
                ],
                'faq' => [
                    ['Harga di LIVE sering berbeda, harga mana yang dipakai?', 'Harga yang berlaku adalah harga di TikTok Shop saat checkout. Harga di situs ini hanya acuan.'],
                    ['Bisa tanya dulu sebelum beli?', 'Bisa, lewat WhatsApp Supermaterial atau fitur chat di TikTok Shop.'],
                ],
                'cta' => 'Sudah lihat videonya? Pastikan spesifikasinya cocok, lalu checkout di TikTok Shop.',
            ],
        ];
    }
};
