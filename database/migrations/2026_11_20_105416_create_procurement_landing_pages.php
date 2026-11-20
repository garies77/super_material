<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Landing page untuk kanal pengadaan B2B/pemerintah: Mbiz (mbizmarket.co.id) dan e-Katalog LKPP (INAPROC).
 * Mengikuti pola landing marketplace (2026_11_15_152535), tetapi copy ditujukan ke pembeli perusahaan dan
 * instansi (pejabat pengadaan, PPK, bagian purchasing), bukan pembeli eceran.
 *
 * Hanya berjalan bila sudah ada kanal beli (situs yang sedang dipakai). Kanal dibuat bila belum ada (aktif, tanpa tautan produk, jadi belum ada tombol beli yang muncul sampai admin
 * mengisi tautan Mbiz/LKPP di produk). Halaman dibuat sebagai DRAF; slug yang sudah ada tidak ditimpa.
 * Tanpa klaim yang belum dikonfirmasi pemilik (status PKP, nilai TKDN, sudah tayang di katalog tertentu, termurah).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Instalasi baru (belum ada kanal sama sekali): lewati, sama seperti contoh landing marketplace
        if (! DB::table('sales_channels')->exists()) {
            return;
        }

        $pages = $this->pages();
        $whatsapp = $this->whatsappNumber();
        $sortOrder = (int) DB::table('sales_channels')->max('sort_order');

        foreach ($pages as $channelSlug => $page) {
            if (! DB::table('sales_channels')->where('slug', $channelSlug)->exists()) {
                DB::table('sales_channels')->insert([
                    ...$page['channel'],
                    'slug' => $channelSlug,
                    'type' => 'marketplace',
                    'allowed_hosts' => json_encode($page['channel']['allowed_hosts']),
                    'sort_order' => ++$sortOrder,
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        foreach ($pages as $channelSlug => $page) {
            if (DB::table('pages')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            $channelId = (int) DB::table('sales_channels')->where('slug', $channelSlug)->value('id');
            $other = collect($pages)->except($channelSlug)->first();
            $retailLinks = collect(['shopee' => 'Shopee', 'tokopedia' => 'Tokopedia', 'lazada' => 'Lazada', 'tiktok-shop' => 'TikTok Shop'])
                ->filter(fn ($label, $slug) => DB::table('pages')->where('slug', "jual-peralatan-proyek-di-{$slug}")->exists())
                ->map(fn ($label, $slug) => '<a href="/jual-peralatan-proyek-di-'.$slug.'">'.$label.'</a>')
                ->implode(' · ');

            $name = $page['name'];
            DB::table('pages')->insert([
                'title' => "Supermaterial: Pengadaan Peralatan & Bahan Proyek di {$name}",
                'slug' => $page['slug'],
                'is_published' => false,
                'meta_title' => "Pengadaan Peralatan & Bahan Proyek di {$name}",
                'meta_description' => $page['meta_description'],
                'draft_blocks' => json_encode(
                    $this->blocks($page, $channelId, $whatsapp, '<a href="/'.$other['slug'].'">'.$other['name'].'</a>', $retailLinks),
                    JSON_UNESCAPED_UNICODE,
                ),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('slug', array_column($this->pages(), 'slug'))->where('is_published', false)->whereNull('blocks')->delete();

        // Kanal hanya dihapus bila belum pernah dipakai di produk
        $unused = DB::table('sales_channels')->whereIn('slug', array_keys($this->pages()))
            ->whereNotExists(fn ($q) => $q->from('product_links')->whereColumn('product_links.sales_channel_id', 'sales_channels.id'))
            ->pluck('id');
        DB::table('sales_channels')->whereIn('id', $unused)->delete();
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private function blocks(array $page, int $channelId, ?string $whatsapp, string $otherLink, string $retailLinks): array
    {
        $name = $page['name'];
        $text = fn (string $html) => ['type' => 'text', 'data' => ['content' => $html]];
        $heading = fn (string $value) => ['type' => 'heading', 'data' => ['text' => $value, 'level' => 'h2', 'align' => 'left']];
        $list = fn (string $style, array $items) => ['type' => 'list', 'data' => ['style' => $style, 'items' => array_map(fn ($t) => ['text' => $t], $items)]];

        $faq = [
            ["Apakah harga di Supermaterial sama dengan di {$name}?", "Belum tentu. Harga di situs ini adalah harga acuan untuk membantu menyusun perkiraan anggaran. Harga yang mengikat adalah harga yang tercantum di {$name} saat pesanan dibuat."],
            ...$page['faq'],
            ['Bisa minta penawaran untuk jumlah besar?', "Bisa. Kirim daftar kebutuhan (nama barang, spesifikasi, jumlah, dan lokasi pengiriman) lewat WhatsApp. Kami bantu cek ketersediaan dan jadwal kirim sebelum Anda memproses pesanan di {$name}."],
            ['Produk yang dibutuhkan belum ada di daftar, bagaimana?', "Hubungi kami lewat WhatsApp. Kami cek apakah barang tersebut bisa disediakan dan ditayangkan di {$name}."],
        ];

        return array_values(array_filter([
            $text('<p>'.$page['intro'].'</p>'),

            ['type' => 'highlights', 'data' => ['items' => array_map(fn ($t) => ['text' => $t], $page['highlights'])]],

            ['type' => 'products', 'data' => [
                'heading' => "Peralatan & Bahan Proyek di {$name}",
                'intro' => "Klik nama produk untuk melihat spesifikasi lengkap, atau tombol \"Beli di {$name}\" untuk membuka halaman produk di {$name}.",
                'channel_id' => $channelId,
                'source' => 'channel',
                'sort' => 'featured',
                'limit' => 24,
                'columns' => '4',
                'show_price' => true,
            ]],

            $heading("Kenapa pengadaan lewat Supermaterial di {$name}?"),
            $list('bullet', [
                'Spesifikasi, ukuran, dan kegunaan tiap produk tersedia di satu tempat, memudahkan penyusunan spesifikasi teknis dan perbandingan.',
                'Harga acuan ditampilkan apa adanya, membantu menyusun perkiraan anggaran sebelum membuat pesanan.',
                "Pemesanan, persetujuan, dan pembayaran tetap berjalan di {$name} sesuai alur yang sudah dipakai {$page['buyer']}.",
                'Pilihan untuk berbagai pekerjaan: perkakas tangan, alat ukur dan penanda, perlengkapan plester dan finishing, sampai baut angkur.',
                'Tim kami siap membantu cek stok, satuan, dan jadwal kirim lewat WhatsApp sebelum pesanan diproses.',
            ]),

            $heading("Pengadaan di {$name}: yang perlu Anda tahu"),
            $text('<p>'.$page['note'].'</p>'),

            $heading("Alur pemesanan di {$name}"),
            $list('number', $page['steps']),

            $heading('Tips pengadaan barang proyek'),
            $list('bullet', [
                'Cocokkan spesifikasi di halaman produk dengan spesifikasi teknis di dokumen kebutuhan sebelum memesan.',
                'Perhatikan satuan penjualan (per buah, per kotak, per sak, atau per gulung) agar jumlah di pesanan sesuai kebutuhan.',
                'Untuk barang berat atau panjang, pastikan alamat, akses bongkar muat, dan jadwal penerimaan di lokasi proyek.',
                'Tanyakan ketersediaan stok dan waktu kirim lebih dulu untuk pesanan jumlah besar atau tenggat yang ketat.',
                'Periksa barang saat diterima dan cocokkan dengan pesanan sebelum menandatangani berita acara atau mengonfirmasi penerimaan.',
            ]),

            $heading('Tentang harga acuan'),
            $text("<p>Harga di Supermaterial adalah <strong>harga acuan</strong>. Harga yang berlaku, ongkos kirim, dan stok mengikuti {$name} saat pesanan dibuat. Pemesanan, pembayaran, dan dokumen transaksi diproses melalui {$name}.</p>"),

            ['type' => 'faq', 'data' => ['heading' => 'Pertanyaan yang sering diajukan', 'items' => array_map(
                fn ($qa) => ['question' => $qa[0], 'answer' => $qa[1]],
                $faq,
            )]],

            $text('<p><strong>'.$page['cta'].'</strong></p>'),
            ['type' => 'button', 'data' => ['label' => 'Lihat semua produk', 'style' => 'primary', 'align' => 'left', 'link_type' => 'internal', 'url' => '/produk', 'new_tab' => false]],
            $whatsapp ? ['type' => 'button', 'data' => [
                'label' => 'Minta penawaran via WhatsApp', 'style' => 'outline', 'align' => 'left', 'link_type' => 'external', 'new_tab' => true,
                'url' => 'https://wa.me/'.$whatsapp.'?text='.rawurlencode("Halo Supermaterial, saya mau minta penawaran untuk pengadaan lewat {$name}."),
            ]] : null,
            $text("<p>Kanal pengadaan lain: {$otherLink}</p>"),
            $retailLinks ? $text("<p>Belanja eceran di marketplace: {$retailLinks}</p>") : null,
        ]));
    }

    /** Nomor WhatsApp dari Pengaturan situs dalam format internasional (62…), atau null bila kosong. */
    private function whatsappNumber(): ?string
    {
        $raw = (string) DB::table('settings')->where('key', 'whatsapp_number')->value('value');
        $decoded = json_decode($raw, true);
        $number = preg_replace('/\D+/', '', is_string($decoded) ? $decoded : $raw);
        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return $number !== '' ? $number : null;
    }

    /** @return array<string, array<string, mixed>> isi khas tiap kanal pengadaan (slug kanal => isi) */
    private function pages(): array
    {
        return [
            'mbiz' => [
                'name' => 'Mbiz',
                'slug' => 'jual-peralatan-proyek-di-mbiz',
                'channel' => ['name' => 'Mbiz', 'color' => '#1f4e9c', 'allowed_hosts' => ['mbizmarket.co.id', 'www.mbizmarket.co.id']],
                'buyer' => 'perusahaan atau instansi Anda',
                'meta_description' => 'Pengadaan peralatan dan bahan proyek untuk perusahaan dan instansi di Mbiz lewat Supermaterial. Cek spesifikasi dan harga acuan, lalu pesan di Mbiz.',
                'intro' => 'Bagian purchasing atau pengadaan sedang mencari perlengkapan proyek konstruksi, renovasi gedung, atau pemeliharaan fasilitas? Supermaterial menyediakan perkakas dan perlengkapan proyek yang bisa dipesan melalui <strong>Mbiz</strong>, marketplace pengadaan untuk perusahaan dan instansi. Cek spesifikasi dan harga acuan di sini, lalu proses pesanan di Mbiz sesuai alur persetujuan organisasi Anda.',
                'highlights' => ['Untuk pembeli perusahaan & instansi', 'Spesifikasi lengkap di tiap produk', 'Minta penawaran via WhatsApp'],
                'note' => 'Mbiz melayani pembeli perusahaan dan instansi, sehingga pesanan biasanya mengikuti alur persetujuan internal organisasi Anda. Pilihan pembayaran, termasuk pembayaran tempo bila tersedia, serta dokumen transaksi seperti invoice dan faktur pajak mengikuti ketentuan Mbiz, akun organisasi Anda, dan status penjual. Pastikan kebutuhan dokumen untuk administrasi keuangan Anda sebelum pesanan diproses.',
                'steps' => [
                    'Pilih produk di daftar di atas dan cocokkan spesifikasinya dengan kebutuhan.',
                    'Tekan "Beli di Mbiz" untuk membuka halaman produk di Mbiz, lalu masuk dengan akun organisasi Anda.',
                    'Masukkan jumlah, alamat pengiriman, dan cek harga serta ongkos kirim.',
                    'Ajukan pesanan sesuai alur persetujuan di organisasi Anda.',
                    'Terima barang, periksa kesesuaiannya, lalu konfirmasi penerimaan di Mbiz.',
                ],
                'faq' => [
                    ['Apakah bisa memakai pembayaran tempo?', 'Tergantung ketentuan Mbiz dan akun organisasi Anda. Cek pilihan pembayaran yang tersedia saat membuat pesanan di Mbiz.'],
                    ['Dokumen apa yang saya terima?', 'Dokumen transaksi seperti invoice diterbitkan sesuai ketentuan Mbiz. Untuk kebutuhan dokumen lain, misalnya faktur pajak, tanyakan dulu kepada kami lewat WhatsApp sebelum memesan.'],
                ],
                'cta' => 'Sudah sesuai kebutuhan proyek? Lanjutkan pemesanan di Mbiz, atau minta penawaran dulu.',
            ],

            'lkpp' => [
                'name' => 'e-Katalog LKPP',
                'slug' => 'jual-peralatan-proyek-di-e-katalog-lkpp',
                'channel' => ['name' => 'e-Katalog LKPP', 'color' => '#b91c1c', 'allowed_hosts' => ['katalog.inaproc.id', 'e-katalog.lkpp.go.id']],
                'buyer' => 'instansi Anda',
                'meta_description' => 'Pengadaan peralatan dan bahan proyek untuk instansi pemerintah lewat e-Katalog LKPP. Cek spesifikasi dan harga acuan di Supermaterial, lalu proses e-purchasing.',
                'intro' => 'Pejabat Pengadaan, PPK, atau tim pengadaan instansi sedang menyiapkan kebutuhan peralatan untuk pembangunan, rehabilitasi gedung, atau pemeliharaan sarana? Supermaterial menyediakan perkakas dan perlengkapan proyek yang dapat dicari di <strong>e-Katalog LKPP</strong> melalui INAPROC. Cek spesifikasi dan harga acuan di sini untuk membantu penyusunan kebutuhan, lalu proses e-purchasing di e-Katalog sesuai ketentuan yang berlaku.',
                'highlights' => ['Untuk pengadaan instansi pemerintah', 'Spesifikasi lengkap di tiap produk', 'Konsultasi kebutuhan via WhatsApp'],
                'note' => 'Pembelian di e-Katalog LKPP dilakukan melalui e-purchasing oleh pejabat yang berwenang di instansi Anda dan mengikuti peraturan pengadaan barang/jasa pemerintah yang berlaku. Informasi seperti nilai TKDN, kategori produk, dan harga yang mengikat tercantum di halaman produk e-Katalog. Selalu rujuk halaman tersebut, bukan situs ini, untuk keperluan dokumen pengadaan.',
                'steps' => [
                    'Pilih produk di daftar di atas dan cocokkan spesifikasinya dengan kebutuhan instansi.',
                    'Tekan "Beli di e-Katalog LKPP" untuk membuka halaman produk di e-Katalog.',
                    'Masuk dengan akun pengadaan instansi Anda dan periksa harga, ongkos kirim, serta informasi produk.',
                    'Buat paket atau pesanan e-purchasing sesuai ketentuan dan kewenangan di instansi Anda.',
                    'Terima barang, periksa kesesuaiannya, lalu selesaikan serah terima dan konfirmasi di sistem.',
                ],
                'faq' => [
                    ['Siapa yang bisa membeli lewat e-Katalog LKPP?', 'Pembelian dilakukan oleh pejabat pengadaan atau PPK di instansi pemerintah melalui akun e-purchasing masing-masing, sesuai ketentuan yang berlaku.'],
                    ['Di mana saya bisa melihat nilai TKDN produk?', 'Nilai TKDN, bila ada, tercantum di halaman produk e-Katalog. Untuk pertanyaan lebih lanjut, hubungi kami lewat WhatsApp.'],
                    ['Apakah bisa negosiasi harga?', 'Fitur dan ketentuan negosiasi mengikuti sistem e-Katalog dan aturan pengadaan yang berlaku. Untuk kebutuhan jumlah besar, hubungi kami dulu agar ketersediaan barang bisa dipastikan.'],
                ],
                'cta' => 'Spesifikasi sudah sesuai? Lanjutkan e-purchasing di e-Katalog LKPP, atau konsultasikan kebutuhan Anda dulu.',
            ],
        ];
    }
};
