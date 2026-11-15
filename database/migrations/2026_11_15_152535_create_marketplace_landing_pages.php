<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contoh landing page per marketplace (Shopee, Tokopedia, Lazada, TikTok Shop), disusun dari riset copywriting & SEO
 * (lihat plan-riset-copywriting-seo-landing-marketplace.md). Prinsip dari riset:
 * - halaman bermerek "Supermaterial di {marketplace}", bukan pengejar kata kunci generik;
 * - isi tiap halaman berbeda nyata (kategori unggulan, tips memilih, fakta marketplace, FAQ) agar tidak dianggap doorway;
 * - kategori tidak dipakai dua kali antarhalaman sehingga produk tidak tumpang-tindih;
 * - tanpa klaim yang belum dikonfirmasi pemilik (toko resmi, garansi, gratis ongkir, termurah).
 *
 * Dibuat sebagai DRAF (Halaman → Terbitkan). Grid memakai kategori berdasarkan slug di database tempat migrasi
 * berjalan; kategori yang tidak ada dilewati. Halaman dengan slug yang sudah ada tidak ditimpa.
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

            DB::table('pages')->insert([
                'title' => $page['h1'],
                'slug' => $page['slug'],
                'is_published' => false,
                'meta_title' => $page['meta_title'],
                'meta_description' => $page['meta_description'],
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
        $whatsappButton = fn (string $label, string $align) => $whatsapp ? ['type' => 'button', 'data' => [
            'label' => $label, 'style' => 'outline', 'align' => $align, 'link_type' => 'external', 'new_tab' => true,
            'url' => 'https://wa.me/'.$whatsapp.'?text='.rawurlencode("Halo Supermaterial, saya mau tanya soal produk di {$name}."),
        ]] : null;

        $grids = [];
        foreach ($page['grids'] as [$categorySlug, $gridHeading, $intro]) {
            $categoryId = DB::table('categories')->where('slug', $categorySlug)->value('id');
            if ($categoryId) {
                $grids[] = ['type' => 'products', 'data' => [
                    'heading' => $gridHeading, 'intro' => $intro, 'channel_id' => $channelId,
                    'source' => 'category', 'category_id' => $categoryId, 'sort' => 'featured',
                    'limit' => 8, 'columns' => '4', 'show_price' => true,
                ]];
            }
        }

        // Tanpa kategori yang cocok: satu grid otomatis berisi semua produk di kanal ini
        if ($grids === []) {
            $grids[] = ['type' => 'products', 'data' => [
                'heading' => "Produk Supermaterial di {$name}", 'channel_id' => $channelId,
                'source' => 'channel', 'sort' => 'featured', 'limit' => 24, 'columns' => '4', 'show_price' => true,
            ]];
        }

        return array_values(array_filter([
            $text('<p>'.$page['intro'].'</p>'),
            ['type' => 'highlights', 'data' => ['items' => array_map(fn ($t) => ['text' => $t], [
                "Harga acuan, harga final di {$name}",
                'Spesifikasi lengkap di tiap produk',
                'Tanya dulu via WhatsApp',
            ])]],
            ...$grids,
            $heading($page['tips_heading']),
            $text($page['tips']),
            $page['video'] ?? null,
            $heading("Belanja di {$name}: yang perlu Anda tahu"),
            $text('<p>'.$page['marketplace_note'].'</p>'),
            $heading("Kenapa lewat Supermaterial sebelum checkout di {$name}?"),
            $list('bullet', $page['why']),
            $heading("Cara belanja di {$name}"),
            $list('number', $page['steps']),
            $heading('Tentang harga acuan'),
            $text("<p>Harga di Supermaterial adalah <strong>harga acuan</strong> untuk membantu Anda memperkirakan anggaran. Harga final, promo, ongkos kirim, dan stok selalu mengikuti halaman toko di {$name} saat checkout. Transaksi, pembayaran, dan pengiriman diproses oleh {$name}.</p>"),
            ['type' => 'faq', 'data' => ['heading' => 'Pertanyaan yang sering diajukan', 'items' => array_map(
                fn ($qa) => ['question' => $qa[0], 'answer' => $qa[1]],
                $page['faq'],
            )]],
            $text('<p><strong>'.$page['cta'].'</strong></p>'),
            ['type' => 'button', 'data' => ['label' => 'Lihat semua produk', 'style' => 'primary', 'align' => 'left', 'link_type' => 'internal', 'url' => '/produk', 'new_tab' => false]],
            $whatsappButton('Tanya dulu via WhatsApp', 'left'),
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

    /** @return array<string, array<string, mixed>> isi per kanal (slug kanal => halaman) */
    private function pages(): array
    {
        $table = fn (array $head, array $rows) => '<table><thead><tr>'.implode('', array_map(fn ($h) => "<th>{$h}</th>", $head)).'</tr></thead><tbody>'
            .implode('', array_map(fn ($r) => '<tr>'.implode('', array_map(fn ($c) => "<td>{$c}</td>", $r)).'</tr>', $rows)).'</tbody></table>';

        return [
            'shopee' => [
                'name' => 'Shopee',
                'slug' => 'jual-peralatan-proyek-di-shopee',
                'h1' => 'Bahan Bangunan & Finishing Supermaterial di Shopee',
                'meta_title' => 'Bahan Bangunan & Finishing di Shopee',
                'meta_description' => 'Semen 40 kg, mortar instan, keramik, dan cat pilihan untuk proyek. Cek spesifikasi dan harga acuan di Supermaterial, lalu beli di toko Shopee.',
                'intro' => 'Di halaman ini Anda bisa melihat bahan bangunan Supermaterial yang tersedia di Shopee: semen 40 kg, mortar instan untuk perekat bata ringan dan acian, keramik serta granit, sampai cat untuk tahap finishing. Cek spesifikasi dan harga acuan di sini, lalu tekan <strong>Beli di Shopee</strong> untuk checkout. Harga final, ongkir, dan promo mengikuti Shopee.',
                'grids' => [
                    ['semen-perekat', 'Semen, mortar & perekat', 'Semen 40 kg dan mortar instan untuk pasangan bata, acian, dan perekat. Perhatikan berat per sak dan opsi pengiriman toko sebelum memesan dalam jumlah banyak.'],
                    ['keramik-lantai', 'Keramik & lantai', 'Keramik dan granit untuk lantai dan dinding. Cek ukuran, isi per dus, dan motif di halaman produk.'],
                    ['cat-pelapis', 'Cat & pelapis', 'Cat dan pelapis untuk tahap akhir pekerjaan. Lihat kegunaan (interior/eksterior) dan daya sebar di halaman produk.'],
                ],
                'tips_heading' => 'Tips memilih semen, mortar & keramik',
                'tips' => $table(['Pekerjaan', 'Pilihan bahan', 'Catatan'], [
                    ['Pasang bata merah & plester', 'Semen serba guna', 'Dicampur pasir dan air sesuai takaran adukan.'],
                    ['Pasang bata ringan (hebel)', 'Mortar instan perekat bata ringan', 'Cukup ditambah air; lapisan lebih tipis dari adukan biasa.'],
                    ['Acian dinding', 'Mortar acian', 'Untuk permukaan halus sebelum dicat.'],
                    ['Pasang keramik & granit', 'Perekat keramik atau adukan semen', 'Untuk ubin berukuran besar, ikuti rekomendasi produsen ubin.'],
                    ['Membeli keramik', 'Hitung luas + cadangan', 'Lebihkan beberapa persen untuk potongan dan cadangan perbaikan.'],
                ]),
                'marketplace_note' => 'Di Shopee, pilihan pembayaran seperti SPayLater dan voucher ongkir tergantung akun Anda dan program yang diikuti toko. Cek di halaman checkout sebelum membayar. Untuk barang berat seperti semen per sak, perhatikan opsi kurir dan area pengiriman yang tersedia di halaman produk.',
                'why' => [
                    'Spesifikasi penting (berat, ukuran, isi kemasan) dirangkum di satu halaman, tanpa membuka banyak listing.',
                    'Bahan dikelompokkan menurut tahap pekerjaan: pasangan, lantai, dan finishing.',
                    'Harga acuan ditampilkan apa adanya; harga final tetap di Shopee.',
                    'Ragu kebutuhan bahan? Tanyakan lewat WhatsApp sebelum checkout.',
                ],
                'steps' => [
                    'Pilih produk dan buka halaman detailnya untuk cek spesifikasi.',
                    'Tekan Beli di Shopee. Halaman produk terbuka di aplikasi atau situs Shopee.',
                    'Periksa varian, jumlah, ongkir, dan voucher yang berlaku.',
                    'Pilih metode pembayaran, lalu selesaikan pesanan.',
                    'Saat paket tiba, cek kondisi barang sebelum menekan "Pesanan Diterima".',
                ],
                'faq' => [
                    ['Apakah harga di Supermaterial sama dengan di Shopee?', 'Belum tentu. Harga di situs ini adalah harga acuan. Harga final, diskon, dan ongkir mengikuti yang tampil di Shopee saat checkout.'],
                    ['Apakah semen bisa dikirim ke luar kota lewat Shopee?', 'Tergantung kurir dan area layanan toko. Semen berat (40 kg per sak), jadi ongkir dan area pengirimannya bisa terbatas. Cek opsi pengiriman di halaman produk Shopee atau tanyakan lewat WhatsApp.'],
                    ['Berapa banyak keramik yang perlu dibeli?', 'Hitung luas area dalam meter persegi, bagi dengan luas per dus yang tertera di halaman produk, lalu lebihkan beberapa persen untuk potongan dan cadangan.'],
                    ['Apa beda semen serba guna dan mortar instan?', 'Semen serba guna perlu dicampur pasir sesuai takaran. Mortar instan sudah berisi campuran siap pakai untuk kegunaan tertentu, misalnya perekat bata ringan atau acian, sehingga cukup ditambah air.'],
                    ['Bisa bayar cicilan?', 'Shopee menyediakan SPayLater dan cicilan kartu kredit. Ketersediaannya tergantung akun Anda dan pengaturan toko, dan bisa dicek saat checkout.'],
                    ['Barang rusak saat diterima, apa yang harus dilakukan?', 'Jangan tekan "Pesanan Diterima" dulu. Dokumentasikan kondisi barang, lalu ajukan pengembalian lewat fitur di Shopee sesuai ketentuan yang berlaku.'],
                ],
                'cta' => 'Sudah menemukan bahan yang cocok? Lanjutkan ke Shopee, atau lihat semua produk Supermaterial dulu.',
            ],

            'tokopedia' => [
                'name' => 'Tokopedia',
                'slug' => 'jual-peralatan-proyek-di-tokopedia',
                'h1' => 'Kompresor, Pneumatik & Besi Beton Supermaterial di Tokopedia',
                'meta_title' => 'Kompresor, Paku Tembak & Besi Beton di Tokopedia',
                'meta_description' => 'Kompresor angin, paku tembak, dan besi beton untuk bengkel dan proyek. Bandingkan spesifikasi di Supermaterial, lalu checkout di Tokopedia.',
                'intro' => 'Halaman ini merangkum alat pneumatik dan material struktur Supermaterial yang bisa dibeli di Tokopedia: kompresor angin, paku tembak, dan besi beton ulir maupun polos. Setiap produk punya halaman spesifikasi dan harga acuan, jadi Anda bisa membandingkan dulu sebelum checkout. Ada yang belum jelas? Tanyakan lewat WhatsApp.',
                'grids' => [
                    ['pneumatik-kompresor', 'Kompresor angin & paku tembak', 'Cocokkan kapasitas tangki (liter), tekanan kerja (bar), dan debit udara kompresor dengan alat yang akan dipakai, misalnya paku tembak atau spray gun.'],
                    ['besi-baja', 'Besi beton & material struktur', 'Besi ulir dan besi polos untuk tulangan. Perhatikan diameter (mm) dan panjang batang sebelum memesan.'],
                ],
                'tips_heading' => 'Tips memilih kompresor untuk paku tembak & spray gun',
                'tips' => $table(['Yang dicek', 'Di spesifikasi', 'Kenapa penting'], [
                    ['Tekanan kerja', 'bar atau psi', 'Tekanan maksimal kompresor harus di atas tekanan kerja alat.'],
                    ['Debit udara', 'liter/menit atau CFM', 'Harus mencukupi kebutuhan udara alat; spray gun umumnya lebih boros udara daripada paku tembak.'],
                    ['Kapasitas tangki', 'liter', 'Tangki lebih besar membuat kompresor tidak terlalu sering menyala saat dipakai lama.'],
                    ['Jenis kompresor', 'oil-less atau berpelumas', 'Oil-less umumnya lebih ringan perawatannya; cocokkan dengan pemakaian harian Anda.'],
                ]).'<p>Untuk besi beton, pastikan diameter dan jenis (ulir atau polos) mengikuti gambar kerja atau arahan perencana struktur.</p>',
                'marketplace_note' => 'Di Tokopedia, label Gratis Ongkir hanya berlaku pada toko yang ikut program tersebut. Jika Anda pelanggan Tokopedia PLUS, manfaat ongkirnya bisa berlaku pada toko yang ikut program. Cek label di halaman produk dan rincian biaya di halaman checkout sebelum membayar.',
                'why' => [
                    'Data teknis (liter, bar, debit udara, diameter) dijelaskan dengan bahasa lapangan.',
                    'Ada panduan singkat memadukan alat, misalnya kompresor dengan paku tembak.',
                    'Produk dikurasi untuk bengkel, kontraktor, dan tukang.',
                    'Harga acuan transparan; transaksi dan perlindungan pembeli mengikuti Tokopedia.',
                ],
                'steps' => [
                    'Buka halaman produk di Supermaterial dan pastikan spesifikasinya sesuai.',
                    'Tekan Beli di Tokopedia.',
                    'Di Tokopedia, pilih varian, cek ongkir, kupon, dan estimasi tiba.',
                    'Bayar dengan metode yang tersedia di akun Anda.',
                    'Lacak pesanan di aplikasi Tokopedia; cek dan uji barang saat diterima.',
                ],
                'faq' => [
                    ['Kompresor berapa liter yang cocok untuk paku tembak?', 'Tergantung kebutuhan udara dan tekanan kerja paku tembak yang tertera di spesifikasinya. Bandingkan dengan tekanan maksimal dan debit udara kompresor di halaman produk, atau tanyakan lewat WhatsApp.'],
                    ['Apa beda kompresor oil-less dan kompresor berpelumas?', 'Kompresor oil-less tidak memakai oli di ruang pompa sehingga perawatannya lebih ringan. Kompresor berpelumas memerlukan penggantian oli berkala. Pilih sesuai pola pemakaian dan anjuran produsen.'],
                    ['Apa beda besi ulir dan besi polos?', 'Besi ulir punya sirip di permukaannya agar lebih mengikat beton. Pemakaiannya mengikuti gambar kerja atau arahan perencana struktur.'],
                    ['Harga di sini berbeda dengan di Tokopedia, mana yang berlaku?', 'Harga yang berlaku adalah harga di Tokopedia saat checkout. Harga di situs ini hanya acuan.'],
                    ['Apakah ongkir besi beton mahal?', 'Besi beton panjang dan berat, sehingga pilihan kurir dan ongkirnya bisa terbatas. Cek opsi pengiriman di halaman produk Tokopedia atau tanyakan lewat WhatsApp sebelum memesan.'],
                ],
                'cta' => 'Bandingkan dulu, beli di Tokopedia saat sudah yakin.',
            ],

            'lazada' => [
                'name' => 'Lazada',
                'slug' => 'jual-peralatan-proyek-di-lazada',
                'h1' => 'Peralatan Tukang & Alat Ukur Supermaterial di Lazada',
                'meta_title' => 'Peralatan Tukang & Alat Ukur di Lazada',
                'meta_description' => 'Palu godam, sekop, gerobak sorong, waterpass laser, dan mesin las untuk proyek. Cek spesifikasi di Supermaterial, lalu beli lewat toko Lazada.',
                'intro' => 'Butuh beberapa perlengkapan tukang sekaligus? Di halaman ini kami kumpulkan produk Supermaterial di Lazada untuk pekerjaan harian di lokasi proyek: palu godam, sekop, dan gerobak sorong, ditambah alat ukur seperti waterpass laser dan mesin las. Buka halaman produk untuk membaca spesifikasinya, lalu lanjutkan pembelian di Lazada. Harga di situs ini adalah harga acuan.',
                'grids' => [
                    ['alat-proyek-perkakas-manual', 'Perkakas manual untuk tukang', 'Alat dasar yang dipakai hampir setiap hari di lokasi: palu godam, sekop, dan gerobak sorong.'],
                    ['alat-ukur-digital-las', 'Alat ukur, waterpass laser & mesin las', 'Untuk memastikan pasangan bata, keramik, dan plafon lurus dan rata, serta mesin las untuk pekerjaan rangka.'],
                ],
                'tips_heading' => 'Daftar alat tukang per tahap pekerjaan',
                'tips' => $table(['Tahap', 'Alat yang biasa dipakai'], [
                    ['Persiapan & ukur', 'Meteran, benang, waterpass atau waterpass laser'],
                    ['Galian & adukan', 'Sekop, cangkul, gerobak sorong, ember'],
                    ['Bongkar', 'Palu godam, linggis, pahat'],
                    ['Pasang & finishing', 'Waterpass laser, sendok semen, roskam'],
                ]).'<p>Saat memilih waterpass laser, perhatikan jumlah garis laser, fitur <em>self-leveling</em>, dan warna sinar. Sinar hijau umumnya lebih mudah terlihat daripada merah di ruangan terang.</p>',
                'marketplace_note' => 'Di Lazada, ketentuan pengembalian dan pilihan pembayaran, termasuk bayar di tempat, bisa berbeda per toko dan produk. Selalu cek bagian layanan di halaman produk Lazada sebelum checkout.',
                'why' => [
                    'Daftar alat disusun per tahap pekerjaan, memudahkan Anda menyusun daftar belanja tukang.',
                    'Spesifikasi alat ukur dan mesin las dijelaskan singkat dan jelas.',
                    'Harga acuan membantu memperkirakan anggaran sebelum membuka Lazada.',
                    'Bantuan WhatsApp untuk memastikan alat sesuai pekerjaan.',
                ],
                'steps' => [
                    'Pilih produk dan cek spesifikasinya di Supermaterial.',
                    'Tekan Beli di Lazada.',
                    'Cek harga, voucher, opsi pengiriman, dan ketentuan pengembalian toko.',
                    'Pilih pembayaran yang tersedia di akun Anda.',
                    'Terima dan periksa barang; ajukan pengembalian lewat Lazada bila ada masalah.',
                ],
                'faq' => [
                    ['Apakah bisa bayar di tempat (COD)?', 'Lazada menyediakan opsi bayar di tempat, tetapi ketersediaannya tergantung produk, toko, dan alamat. Cek di halaman checkout.'],
                    ['Berapa lama batas pengembalian barang di Lazada?', 'Tergantung jenis toko dan produk. Lihat keterangan pengembalian di halaman produk Lazada sebelum membeli.'],
                    ['Alat apa saja yang perlu disiapkan tukang untuk renovasi rumah?', 'Umumnya meteran, waterpass, palu, sekop, gerobak, bor, dan gerinda. Daftar per tahap ada di tabel di atas.'],
                    ['Waterpass laser berapa garis yang cocok untuk pasang keramik?', 'Untuk lantai dan dinding, laser silang (horizontal dan vertikal) biasanya sudah cukup. Model dengan lebih banyak garis memudahkan pekerjaan plafon dan sudut ruangan.'],
                    ['Apakah harga di sini sudah termasuk ongkir?', 'Tidak. Harga acuan belum termasuk ongkir; biaya kirim dihitung di Lazada.'],
                ],
                'cta' => 'Lengkapi daftar alat Anda, lalu checkout di Lazada.',
            ],

            'tiktok-shop' => [
                'name' => 'TikTok Shop',
                'slug' => 'jual-peralatan-proyek-di-tiktok-shop',
                'h1' => 'Mesin Bor, Bobok & Gerinda Supermaterial di TikTok Shop',
                'meta_title' => 'Mesin Bor, Bobok & Gerinda di TikTok Shop',
                'meta_description' => 'Lihat demonya di video, cek spesifikasinya di Supermaterial. Mesin bor, mesin bobok, gerinda, dan chainsaw untuk proyek, checkout di TikTok Shop.',
                'intro' => 'Sering melihat mesin gerinda atau bor dipakai di video TikTok, tapi ragu soal spesifikasinya? Halaman ini membantu Anda mengecek dulu: daya (watt), ukuran mata atau piringan, dan kegunaan tiap alat dari katalog Supermaterial, mulai dari mesin bor, mesin bobok, gerinda, sampai chainsaw. Setelah yakin, tekan <strong>Beli di TikTok Shop</strong> untuk checkout.',
                'grids' => [
                    ['mesin-bor-bobok', 'Mesin bor & bobok', 'Dari bor tangan untuk melubangi, sampai mesin bobok untuk membongkar keramik dan beton.'],
                    ['mesin-pemotong-gerinda', 'Mesin gerinda, pemotong & chainsaw', 'Perhatikan ukuran piringan dan daya; gerinda 4 inci paling umum untuk pekerjaan tukang. Untuk chainsaw, cek panjang bilah dan jenis tenaganya.'],
                ],
                'tips_heading' => 'Tips memilih mesin bor, bobok & gerinda',
                'tips' => $table(['Pekerjaan', 'Alat yang cocok', 'Patokan umum'], [
                    ['Melubangi kayu, besi tipis, aluminium', 'Bor biasa', 'Pilih ukuran mata sesuai lubang.'],
                    ['Melubangi tembok bata', 'Bor dengan fungsi impact/hammer', 'Gunakan mata bor tembok.'],
                    ['Membobok beton & membongkar keramik', 'Rotary hammer atau mesin bobok', 'Pekerjaan menengah: sekitar 1.300–1.700 watt, 20–30 joule.'],
                    ['Membongkar beton berat', 'Demolition hammer', 'Sekitar 2.000 watt ke atas dan minimal 40 joule.'],
                    ['Memotong & menghaluskan', 'Gerinda tangan 4 inci', 'Pakai mata sesuai material: diamond untuk keramik/beton, mata potong untuk besi.'],
                ]).'<p>Angka di atas adalah patokan umum dari panduan pemilihan mesin bobok; sesuaikan dengan spesifikasi dan anjuran produsen.</p>',
                'marketplace_note' => 'Di TikTok Shop, Anda bisa melihat alat dipakai lewat video atau LIVE penjual sebelum membeli, lalu checkout lewat keranjang kuning. Gunakan halaman ini untuk mencocokkan spesifikasinya dulu. Harga di video atau LIVE bisa berbeda; yang berlaku adalah harga saat checkout.',
                'why' => [
                    'Video menunjukkan cara kerja alat; halaman ini melengkapinya dengan angka spesifikasi.',
                    'Perbandingan antarmodel ada di satu halaman, tanpa perlu menggulir banyak video.',
                    'Penjelasan "cocok untuk pekerjaan apa" di tiap kategori.',
                    'Harga acuan jelas; harga final dan promo mengikuti TikTok Shop.',
                ],
                'steps' => [
                    'Cek spesifikasi produk di Supermaterial.',
                    'Tekan Beli di TikTok Shop. Halaman produk terbuka di aplikasi TikTok.',
                    'Pilih varian, cek voucher dan ongkir.',
                    'Selesaikan pembayaran.',
                    'Pantau pesanan di menu pesanan TikTok Shop; uji alat saat diterima.',
                ],
                'faq' => [
                    ['Harga di LIVE sering berbeda, harga mana yang dipakai?', 'Harga yang berlaku adalah harga di TikTok Shop saat checkout. Harga di situs ini hanya acuan.'],
                    ['Apa beda mesin bor biasa dan mesin bobok?', 'Mesin bor untuk membuat lubang. Mesin bobok (hammer) untuk membongkar atau memahat beton dan keramik.'],
                    ['Gerinda 4 inci cukup untuk memotong keramik?', 'Ya, dengan mata potong yang sesuai, misalnya mata diamond untuk keramik. Cek kecocokan mata dan mesin di halaman produk.'],
                    ['Mesin bor biasa bisa dipakai untuk tembok?', 'Untuk tembok dan beton, pilih bor dengan fungsi impact/hammer. Bor biasa cocok untuk kayu, besi tipis, dan aluminium.'],
                    ['Bisa tanya dulu sebelum beli?', 'Bisa, lewat WhatsApp Supermaterial atau fitur chat di TikTok Shop.'],
                ],
                'cta' => 'Sudah lihat videonya? Pastikan spesifikasinya cocok, lalu checkout di TikTok Shop.',
            ],
        ];
    }
};
