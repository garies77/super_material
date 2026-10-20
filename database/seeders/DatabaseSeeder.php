<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'admin@supermaterial.test'], [
            'name' => 'Admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        foreach ([
            'site_tagline' => 'Katalog bahan bangunan lengkap',
            'site_description' => 'Bandingkan produk, baca panduan memilih, lalu beli lewat Shopee, Tokopedia, Lazada, TikTok Shop, atau konsultasi langsung via WhatsApp.',
            'whatsapp_number' => '081234567890',
            'org_name' => 'Supermaterial',
        ] as $k => $v) {
            Setting::put($k, $v);
        }

        $channels = [];
        foreach ([
            ['Shopee', 'marketplace', '#ee4d2d', ['shopee.co.id', 'shopee.com', 's.shopee.co.id', 'shp.ee']],
            ['Tokopedia', 'marketplace', '#03ac0e', ['tokopedia.com', 'tokopedia.link']],
            ['Lazada', 'marketplace', '#0f146d', ['lazada.co.id', 's.lazada.co.id']],
            ['TikTok Shop', 'marketplace', '#111111', ['tiktok.com', 'vt.tiktok.com', 'shop.tiktok.com']],
            ['WhatsApp', 'whatsapp', '#25d366', []],
        ] as $i => [$name, $type, $color, $hosts]) {
            $channels[Str::slug($name)] = SalesChannel::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'type' => $type, 'color' => $color, 'allowed_hosts' => $hosts, 'sort_order' => $i, 'is_active' => true,
            ]);
        }

        $cats = [];
        foreach ([
            ['Semen & Perekat', 'Semen, mortar, dan perekat keramik untuk pondasi hingga finishing.'],
            ['Besi & Baja', 'Besi beton, baja ringan, dan wiremesh untuk struktur bangunan.'],
            ['Keramik & Lantai', 'Keramik lantai dan dinding, granit, serta aksesori pemasangan.'],
            ['Cat & Pelapis', 'Cat tembok interior, eksterior, dan pelapis anti bocor.'],
        ] as $i => [$name, $intro]) {
            $cats[$name] = Category::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'intro' => $intro, 'sort_order' => $i, 'is_active' => true,
                'bottom_content' => "<h2>Cara memilih {$name}</h2><p>Sesuaikan jenis produk dengan kebutuhan proyek: perhatikan spesifikasi, merek, dan ketersediaan. Bandingkan harga acuan lalu klik tombol beli pada produk pilihan Anda.</p>",
            ]);
        }
        $cats['Mortar'] = Category::updateOrCreate(['slug' => 'mortar'], [
            'name' => 'Mortar', 'parent_id' => $cats['Semen & Perekat']->id, 'intro' => 'Mortar siap pakai untuk plesteran dan pemasangan bata.', 'is_active' => true,
        ]);

        $brands = [];
        foreach (['Tiga Roda', 'Holcim', 'Krakatau Steel', 'Mulia', 'Dulux'] as $b) {
            $brands[$b] = Brand::updateOrCreate(['slug' => Str::slug($b)], ['name' => $b]);
        }

        // Generate SVG illustrations into storage
        $productsDir = storage_path('app/public/products');
        if (! is_dir($productsDir)) {
            mkdir($productsDir, 0777, true);
        }

        $svgDefinitions = [
            'semen-tiga-roda-1.svg' => ['SEMEN TIGA RODA 50 KG', 'Standar SNI & Kekuatan Teruji', '#1e3a8a', '#3b82f6', '3R'],
            'semen-tiga-roda-2.svg' => ['KEMASAN SAK 40 KG', 'PCC - Praktis untuk Renovasi', '#1e40af', '#60a5fa', '40KG'],
            'semen-tiga-roda-3.svg' => ['APLIKASI STRUKTUR & PLESTER', 'Hasil Padat & Tahan Retak', '#172554', '#2563eb', 'PCC'],
            'keramik-mulia-1.svg' => ['KERAMIK MULIA 40x40', 'Putih Glossy Kelas Premium', '#0f766e', '#14b8a6', '40x40'],
            'keramik-mulia-2.svg' => ['VARIAN MATTE GREY', 'Anti Selip & Elegan', '#334155', '#64748b', 'GREY'],
            'keramik-mulia-3.svg' => ['VARIAN MARBLE CREAM', 'Motif Marmer Alami Mewah', '#78350f', '#d97706', 'MARBLE'],
            'besi-beton-1.svg' => ['BESI BETON POLOS 10 MM', 'Standar Krakatau Steel SNI', '#374151', '#4b5563', '10MM'],
            'besi-beton-2.svg' => ['BESI BETON ULIR 13 MM', 'Daya Rekat Beton Maksimal', '#1f2937', '#111827', '13MM'],
            'cat-dulux-1.svg' => ['DULUX CATYLAC INTERIOR', 'Daya Sebar Luas & Warna Cerah', '#b91c1c', '#f87171', 'DULUX'],
            'mortar-holcim-1.svg' => ['MORTAR INSTAN 40 KG', 'Perekat Bata Ringan Cepat Kering', '#c2410c', '#fb923c', 'MORTAR'],
            'granit-mulia-1.svg' => ['GRANIT TILE 60x60 ABU', 'Kekuatan Beban Tinggi Glossy', '#0e7490', '#38bdf8', '60x60'],
        ];

        foreach ($svgDefinitions as $fileName => [$title, $sub, $c1, $c2, $symbol]) {
            $filePath = $productsDir.'/'.$fileName;
            $safeTitle = htmlspecialchars($title, ENT_XML1, 'UTF-8');
            $safeSub = htmlspecialchars($sub, ENT_XML1, 'UTF-8');
            $safeSymbol = htmlspecialchars($symbol, ENT_XML1, 'UTF-8');

            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800">'.
                '<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">'.
                '<stop offset="0%" stop-color="'.$c1.'"/><stop offset="100%" stop-color="'.$c2.'"/>'.
                '</linearGradient></defs>'.
                '<rect width="800" height="800" fill="url(#g)"/>'.
                '<circle cx="400" cy="350" r="160" fill="rgba(255,255,255,0.18)"/>'.
                '<text x="400" y="385" font-family="sans-serif" font-size="76" font-weight="bold" fill="#ffffff" text-anchor="middle">'.$safeSymbol.'</text>'.
                '<rect x="80" y="560" width="640" height="160" rx="24" fill="rgba(255,255,255,0.95)"/>'.
                '<text x="400" y="625" font-family="sans-serif" font-size="30" font-weight="800" fill="#1e293b" text-anchor="middle">'.$safeTitle.'</text>'.
                '<text x="400" y="675" font-family="sans-serif" font-size="19" font-weight="600" fill="#64748b" text-anchor="middle">'.$safeSub.'</text>'.
                '</svg>';
            file_put_contents($filePath, $svg);
        }

        $products = [
            [
                'name' => 'Semen Tiga Roda 50 kg',
                'category' => 'Semen & Perekat',
                'brand' => 'Tiga Roda',
                'min' => 52000,
                'max' => 62000,
                'unit' => 'sak',
                'badge' => 'Resmi',
                'shipping_note' => 'Pengiriman armada toko dari gudang Medan · Estimasi 1-2 hari kerja',
                'guarantee_note' => '100% Produk Asli Pabrik · Jaminan Mutu SNI',
                'rating_value' => 4.9,
                'rating_count' => 1240,
                'image' => 'products/semen-tiga-roda-1.svg',
                'gallery' => ['products/semen-tiga-roda-2.svg', 'products/semen-tiga-roda-3.svg'],
                'media' => [
                    ['type' => 'image', 'path' => 'products/semen-tiga-roda-1.svg', 'provider' => 'upload', 'alt_text' => 'Semen Tiga Roda 50 kg Kemasan Asli'],
                    ['type' => 'image', 'path' => 'products/semen-tiga-roda-2.svg', 'provider' => 'upload', 'alt_text' => 'Varian Sak 40 kg Praktis'],
                    ['type' => 'image', 'path' => 'products/semen-tiga-roda-3.svg', 'provider' => 'upload', 'alt_text' => 'Hasil Campuran Semen Tiga Roda Halus dan Padat'],
                    ['type' => 'video', 'path' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'provider' => 'youtube', 'poster_path' => 'products/semen-tiga-roda-1.svg', 'alt_text' => 'Video Panduan Aplikasi Semen'],
                ],
                'variants' => [
                    ['option_group' => 'Ukuran Kemasan', 'name' => 'Sak 40 kg', 'slug' => 'sak-40-kg', 'sku' => 'STR-40', 'price' => 52000, 'compare_at_price' => 58000, 'image_path' => 'products/semen-tiga-roda-2.svg'],
                    ['option_group' => 'Ukuran Kemasan', 'name' => 'Sak 50 kg', 'slug' => 'sak-50-kg', 'sku' => 'STR-50', 'price' => 62000, 'compare_at_price' => 68000, 'image_path' => 'products/semen-tiga-roda-1.svg'],
                ],
                'specs' => ['Berat' => '50 kg', 'Tipe Semen' => 'PCC (Portland Composite Cement)', 'Standar' => 'SNI 7064:2014', 'Kemasan' => 'Sak Kertas Kraft 3 Lapis', 'Aplikasi' => 'Struktur beton, plesteran, acian, dan pasangan bata'],
            ],
            [
                'name' => 'Keramik Lantai Mulia 40x40 Putih',
                'category' => 'Keramik & Lantai',
                'brand' => 'Mulia',
                'min' => 52000,
                'max' => 58000,
                'unit' => 'dus',
                'badge' => 'Terlaris',
                'shipping_note' => 'Pengiriman armada toko khusus Medan & Deli Serdang · Free ongkir min 50 dus',
                'guarantee_note' => '100% Original Mulia Ceramics · Bebas retur jika ada cacat pecah saat pengiriman',
                'rating_value' => 4.8,
                'rating_count' => 850,
                'image' => 'products/keramik-mulia-1.svg',
                'gallery' => ['products/keramik-mulia-2.svg', 'products/keramik-mulia-3.svg'],
                'media' => [
                    ['type' => 'image', 'path' => 'products/keramik-mulia-1.svg', 'provider' => 'upload', 'alt_text' => 'Keramik Mulia 40x40 White Glossy'],
                    ['type' => 'image', 'path' => 'products/keramik-mulia-2.svg', 'provider' => 'upload', 'alt_text' => 'Keramik Mulia 40x40 Grey Matte'],
                    ['type' => 'image', 'path' => 'products/keramik-mulia-3.svg', 'provider' => 'upload', 'alt_text' => 'Keramik Mulia 40x40 Cream Marble'],
                ],
                'variants' => [
                    ['option_group' => 'Pilihan Motif', 'name' => 'White Glossy (40x40)', 'slug' => 'white-glossy', 'sku' => 'KM-40-WHT', 'price' => 52000, 'compare_at_price' => 60000, 'image_path' => 'products/keramik-mulia-1.svg'],
                    ['option_group' => 'Pilihan Motif', 'name' => 'Grey Matte (40x40)', 'slug' => 'grey-matte', 'sku' => 'KM-40-GRY', 'price' => 56000, 'compare_at_price' => 64000, 'image_path' => 'products/keramik-mulia-2.svg'],
                    ['option_group' => 'Pilihan Motif', 'name' => 'Cream Marble (40x40)', 'slug' => 'cream-marble', 'sku' => 'KM-40-CRM', 'price' => 58000, 'compare_at_price' => 68000, 'image_path' => 'products/keramik-mulia-3.svg'],
                ],
                'specs' => ['Ukuran' => '40x40 cm', 'Isi per Dus' => '6 lembar (0.96 m²)', 'Permukaan' => 'Glossy / Matte', 'Kualitas' => 'Grade 1 / KW 1', 'Ketebalan' => '8 mm'],
            ],
            [
                'name' => 'Besi Beton Polos 10 mm',
                'category' => 'Besi & Baja',
                'brand' => 'Krakatau Steel',
                'min' => 74000,
                'max' => 82000,
                'unit' => 'batang',
                'badge' => 'SNI Full',
                'shipping_note' => 'Pengiriman armada truk ekspedisi · Siap kirim skala proyek',
                'guarantee_note' => 'Sertifikat SNI Resmi Krakatau Steel · Berat & Toleransi terjamin',
                'rating_value' => 5.0,
                'rating_count' => 430,
                'image' => 'products/besi-beton-1.svg',
                'gallery' => ['products/besi-beton-2.svg'],
                'media' => [
                    ['type' => 'image', 'path' => 'products/besi-beton-1.svg', 'provider' => 'upload', 'alt_text' => 'Besi Beton Polos 10 mm Krakatau Steel'],
                    ['type' => 'image', 'path' => 'products/besi-beton-2.svg', 'provider' => 'upload', 'alt_text' => 'Detail Penampang Besi Beton SNI'],
                ],
                'variants' => [
                    ['option_group' => 'Standar Toleransi', 'name' => 'SNI Full (12 Meter)', 'slug' => 'sni-full', 'sku' => 'BB-10-SNI', 'price' => 82000, 'compare_at_price' => 89000, 'image_path' => 'products/besi-beton-1.svg'],
                    ['option_group' => 'Standar Toleransi', 'name' => 'Toleransi BJKU (12 Meter)', 'slug' => 'bjku', 'sku' => 'BB-10-BJKU', 'price' => 74000, 'compare_at_price' => 78000, 'image_path' => 'products/besi-beton-2.svg'],
                ],
                'specs' => ['Diameter' => '10 mm', 'Panjang' => '12 meter', 'Bentuk' => 'Polos (Round Bar)', 'Standar' => 'SNI 2052:2017', 'Kekuatan Tarik' => 'BjTP 280'],
            ],
            [
                'name' => 'Cat Tembok Dulux Catylac 5 kg',
                'category' => 'Cat & Pelapis',
                'brand' => 'Dulux',
                'min' => 135000,
                'max' => 155000,
                'unit' => 'pail',
                'badge' => 'Best Formula',
                'shipping_note' => 'Packing kayu & bubble wrap aman · Pengiriman reguler/instan',
                'guarantee_note' => 'Formula ChromaBrite Technology · Warna cerah tahan lebih lama',
                'rating_value' => 4.9,
                'rating_count' => 610,
                'image' => 'products/cat-dulux-1.svg',
                'gallery' => [],
                'media' => [
                    ['type' => 'image', 'path' => 'products/cat-dulux-1.svg', 'provider' => 'upload', 'alt_text' => 'Cat Tembok Dulux Catylac Interior 5 kg'],
                ],
                'variants' => [
                    ['option_group' => 'Kemasan', 'name' => 'Pail 5 kg', 'slug' => 'pail-5-kg', 'sku' => 'DLX-5KG', 'price' => 135000, 'compare_at_price' => 150000, 'image_path' => 'products/cat-dulux-1.svg'],
                    ['option_group' => 'Kemasan', 'name' => 'Pail 25 kg', 'slug' => 'pail-25-kg', 'sku' => 'DLX-25KG', 'price' => 620000, 'compare_at_price' => 690000, 'image_path' => 'products/cat-dulux-1.svg'],
                ],
                'specs' => ['Kemasan' => '5 kg & 25 kg', 'Daya Sebar' => '10-12 m²/kg per lapis', 'Waktu Pengeringan' => '1-2 jam', 'Hasil Akhir' => 'Matt Halus', 'Aplikasi' => 'Roll, Kuas, Spray'],
            ],
            [
                'name' => 'Semen Holcim Serba Guna 40 kg',
                'category' => 'Semen & Perekat',
                'brand' => 'Holcim',
                'min' => 54000,
                'max' => 58000,
                'unit' => 'sak',
                'badge' => null,
                'shipping_note' => 'Pengiriman armada toko dari gudang Medan',
                'guarantee_note' => '100% Produk Original Holcim',
                'rating_value' => 4.7,
                'rating_count' => 320,
                'image' => 'products/semen-tiga-roda-2.svg',
                'gallery' => [],
                'media' => [
                    ['type' => 'image', 'path' => 'products/semen-tiga-roda-2.svg', 'provider' => 'upload', 'alt_text' => 'Semen Holcim Serba Guna 40 kg'],
                ],
                'variants' => [],
                'specs' => ['Berat' => '40 kg', 'Tipe' => 'PCC Serba Guna', 'Penggunaan' => 'Pondasi & Pasangan'],
            ],
            [
                'name' => 'Mortar Instan Perekat Bata Ringan 40 kg',
                'category' => 'Mortar',
                'brand' => 'Holcim',
                'min' => 85000,
                'max' => 95000,
                'unit' => 'sak',
                'badge' => 'Siap Pakai',
                'shipping_note' => 'Armada toko & kurir kargo',
                'guarantee_note' => 'Daya rekat tinggi tahan goncangan',
                'rating_value' => 4.8,
                'rating_count' => 190,
                'image' => 'products/mortar-holcim-1.svg',
                'gallery' => [],
                'media' => [
                    ['type' => 'image', 'path' => 'products/mortar-holcim-1.svg', 'provider' => 'upload', 'alt_text' => 'Mortar Instan Perekat Bata Ringan 40 kg'],
                ],
                'variants' => [],
                'specs' => ['Berat' => '40 kg', 'Ketebalan Aplikasi' => '3 mm', 'Daya Sebar' => '± 10-12 m² per sak'],
            ],
            [
                'name' => 'Besi Beton Ulir 13 mm',
                'category' => 'Besi & Baja',
                'brand' => 'Krakatau Steel',
                'min' => 125000,
                'max' => 142000,
                'unit' => 'batang',
                'badge' => 'SNI Ulir',
                'shipping_note' => 'Pengiriman armada truk proyek',
                'guarantee_note' => 'Standar Deformed Bar SNI Krakatau Steel',
                'rating_value' => 4.9,
                'rating_count' => 280,
                'image' => 'products/besi-beton-2.svg',
                'gallery' => [],
                'media' => [
                    ['type' => 'image', 'path' => 'products/besi-beton-2.svg', 'provider' => 'upload', 'alt_text' => 'Besi Beton Ulir 13 mm Krakatau Steel'],
                ],
                'variants' => [],
                'specs' => ['Diameter' => '13 mm', 'Panjang' => '12 m', 'Tipe' => 'Ulir (Deformed Bar)', 'Standar' => 'SNI 2052:2017'],
            ],
            [
                'name' => 'Granit Tile Mulia 60x60 Abu',
                'category' => 'Keramik & Lantai',
                'brand' => 'Mulia',
                'min' => 165000,
                'max' => 195000,
                'unit' => 'dus',
                'badge' => 'Homogeneous',
                'shipping_note' => 'Free ongkir Medan min 30 dus',
                'guarantee_note' => 'Permukaan tahan gores nano glossy',
                'rating_value' => 4.8,
                'rating_count' => 310,
                'image' => 'products/granit-mulia-1.svg',
                'gallery' => [],
                'media' => [
                    ['type' => 'image', 'path' => 'products/granit-mulia-1.svg', 'provider' => 'upload', 'alt_text' => 'Granit Tile Mulia 60x60 Abu'],
                ],
                'variants' => [],
                'specs' => ['Ukuran' => '60x60 cm', 'Isi per Dus' => '4 pcs (1.44 m²)', 'Tipe' => 'Glazed Porcelain / Granit'],
            ],
        ];

        foreach ($products as $i => $item) {
            $p = Product::updateOrCreate(['slug' => Str::slug($item['name'])], [
                'name' => $item['name'],
                'category_id' => $cats[$item['category']]->id,
                'brand_id' => $brands[$item['brand']]->id,
                'summary' => "{$item['name']} berkualitas tinggi untuk proyek bangunan, rumah tinggal, maupun renovasi.",
                'description' => "<p>{$item['name']} diproduksi dengan standar kualitas tinggi untuk menjamin ketahanan dan kekuatan jangka panjang bangunan Anda.</p><h2>Keunggulan Produk</h2><ul><li>Kualitas teruji dan bersertifikasi SNI</li><li>Stok terjamin dan pengiriman tepat waktu</li><li>Harga acuan kompetitif dan transparan</li><li>Dukungan konsultasi teknis via WhatsApp</li></ul><h2>Petunjuk Penyimpanan & Aplikasi</h2><p>Pastikan produk disimpan di area yang kering dan tidak terkena genangan air langsung. Gunakan peralatan keselamatan kerja yang memadai saat proses instalasi atau pengecoran.</p>",
                'specifications' => $item['specs'],
                'image' => $item['image'],
                'gallery' => $item['gallery'],
                'price_min' => $item['min'],
                'price_max' => $item['max'],
                'unit' => $item['unit'],
                'badge' => $item['badge'] ?? null,
                'shipping_note' => $item['shipping_note'] ?? 'Pengiriman armada toko dari gudang Medan · Estimasi 1-2 hari kerja',
                'guarantee_note' => $item['guarantee_note'] ?? '100% Produk Asli Pabrik · Jaminan Kualitas Pabrikan',
                'rating_value' => $item['rating_value'] ?? 4.8,
                'rating_count' => $item['rating_count'] ?? 150,
                'rating_updated_at' => now()->subDays(rand(1, 14)),
                'price_display' => 'show',
                'show_quantity' => true,
                'is_published' => true,
                'is_featured' => $i < 4,
            ]);

            // Sync media
            if (! empty($item['media'])) {
                $p->media()->delete();
                foreach ($item['media'] as $sort => $med) {
                    ProductMedia::create([
                        'product_id' => $p->id,
                        'type' => $med['type'],
                        'path' => $med['path'],
                        'provider' => $med['provider'] ?? 'upload',
                        'poster_path' => $med['poster_path'] ?? null,
                        'alt_text' => $med['alt_text'] ?? $p->name,
                        'sort_order' => $sort,
                    ]);
                }
            }

            // Sync variants
            if (! empty($item['variants'])) {
                $p->variants()->delete();
                foreach ($item['variants'] as $sort => $v) {
                    ProductVariant::create([
                        'product_id' => $p->id,
                        'option_group' => $v['option_group'] ?? 'Varian',
                        'name' => $v['name'],
                        'slug' => $v['slug'] ?? Str::slug($v['name']),
                        'sku' => $v['sku'] ?? null,
                        'price' => $v['price'],
                        'reference_price' => $v['price'],
                        'compare_at_price' => $v['compare_at_price'] ?? null,
                        'image_path' => $v['image_path'] ?? null,
                        'is_active' => true,
                        'sort_order' => $sort,
                    ]);
                }
            }

            $channelUrls = [
                'shopee' => fn ($name) => 'https://shopee.co.id/search?keyword='.urlencode($name),
                'tokopedia' => fn ($name) => 'https://www.tokopedia.com/search?keyword='.urlencode($name),
                'lazada' => fn ($name) => 'https://www.lazada.co.id/catalog/?q='.urlencode($name),
                'tiktok-shop' => fn ($name) => 'https://shop.tiktok.com/search?q='.urlencode($name),
            ];

            foreach ($channelUrls as $slug => $urlGenerator) {
                if (isset($channels[$slug])) {
                    ProductLink::updateOrCreate(['product_id' => $p->id, 'sales_channel_id' => $channels[$slug]->id], [
                        'url' => $urlGenerator($item['name']),
                        'is_active' => true,
                    ]);
                }
            }

            if (! $p->faqs()->exists()) {
                $p->faqs()->createMany([
                    ['question' => "Apakah {$item['name']} cocok untuk kebutuhan proyek besar maupun renovasi rumah?", 'answer' => 'Ya, produk ini telah teruji untuk konstruksi komersial, gedung bertingkat, dan renovasi rumah tinggal dengan daya tahan optimal.', 'sort_order' => 0],
                    ['question' => 'Bagaimana cara pemesanan dalam jumlah banyak atau konsultasi pengiriman?', 'answer' => 'Anda dapat mengklik tombol "Chat WhatsApp" di atas. Tim kami siap membantu perhitungan estimasi volume material dan penawaran armada pengiriman.', 'sort_order' => 1],
                    ['question' => 'Apakah barang dijamin asli dan memiliki tanggal produksi baru?', 'answer' => 'Seluruh material didatangkan langsung dari pabrik resmi rekanan Supermaterial dengan jaminan mutu SNI dan masa simpan yang fresh.', 'sort_order' => 2],
                ]);
            }
        }

        // Seed Tags
        $tags = [];
        foreach ([
            'Semen' => 'semen',
            'Besi Beton' => 'besi-beton',
            'Cat Tembok' => 'cat-tembok',
            'Mortar' => 'mortar',
            'Baja Ringan' => 'baja-ringan',
            'Standar SNI' => 'sni',
            'Tips Konstruksi' => 'tips-konstruksi',
            'Pondasi' => 'pondasi',
        ] as $tagName => $tagSlug) {
            $tags[$tagSlug] = \App\Models\Tag::updateOrCreate(['slug' => $tagSlug], ['name' => $tagName]);
        }

        // Generate Article SVG Images (16:9 1200x675)
        $articlesDir = storage_path('app/public/articles');
        if (! is_dir($articlesDir)) {
            mkdir($articlesDir, 0777, true);
        }

        $articleSvgs = [
            'artikel-semen-1.svg' => ['PANDUAN MEMILIH SEMEN', 'Karakteristik PCC, OPC & Kuat Tekan Pondasi', '#1e3a8a', '#2563eb', 'SEMEN'],
            'artikel-besi-1.svg' => ['CARA MEMBACA MARKING BESI SNI', 'Menghindari Besi Banci & Toleransi Diameter', '#374151', '#4b5563', 'BESI SNI'],
            'artikel-cat-1.svg' => ['TEKNIK PENGECATAN ANTI JAMUR', 'Persiapan Dinding Plesteran Baru', '#b91c1c', '#ef4444', 'CAT DINDING'],
            'artikel-mortar-1.svg' => ['MORTAR VS SEMEN PASIR', 'Efisiensi Biaya & Kecepatan Pasang Dinding', '#c2410c', '#f97316', 'MORTAR'],
            'artikel-atap-1.svg' => ['7 KESALAHAN BAJA RINGAN', 'Desain Kuda-kuda & Jarak Reng Presisi', '#0f766e', '#14b8a6', 'ATAP'],
        ];

        foreach ($articleSvgs as $fileName => [$title, $sub, $c1, $c2, $symbol]) {
            $filePath = $articlesDir.'/'.$fileName;
            $safeTitle = htmlspecialchars($title, ENT_XML1, 'UTF-8');
            $safeSub = htmlspecialchars($sub, ENT_XML1, 'UTF-8');
            $safeSymbol = htmlspecialchars($symbol, ENT_XML1, 'UTF-8');

            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 675" width="1200" height="675">'.
                '<defs><linearGradient id="ga" x1="0%" y1="0%" x2="100%" y2="100%">'.
                '<stop offset="0%" stop-color="'.$c1.'"/><stop offset="100%" stop-color="'.$c2.'"/>'.
                '</linearGradient></defs>'.
                '<rect width="1200" height="675" fill="url(#ga)"/>'.
                '<circle cx="600" cy="270" r="140" fill="rgba(255,255,255,0.18)"/>'.
                '<text x="600" y="300" font-family="sans-serif" font-size="64" font-weight="bold" fill="#ffffff" text-anchor="middle">'.$safeSymbol.'</text>'.
                '<rect x="150" y="460" width="900" height="150" rx="20" fill="rgba(255,255,255,0.96)"/>'.
                '<text x="600" y="525" font-family="sans-serif" font-size="28" font-weight="800" fill="#1e293b" text-anchor="middle">'.$safeTitle.'</text>'.
                '<text x="600" y="570" font-family="sans-serif" font-size="18" font-weight="600" fill="#64748b" text-anchor="middle">'.$safeSub.'</text>'.
                '</svg>';
            file_put_contents($filePath, $svg);
        }

        // Seed 5 Rich Articles (CNN Style Layout)
        $art1 = Article::updateOrCreate(['slug' => 'cara-memilih-semen-untuk-rumah'], [
            'title' => 'Cara Memilih Semen yang Tepat untuk Bangunan Tahan Gempa',
            'summary' => 'Panduan komprehensif membedakan jenis semen PCC dan OPC, perhitungan campuran adukan pondasi, dan tips penyimpanan sak agar kualitas hidrasi tetap optimal.',
            'content' => '<p>Pemilihan jenis semen yang tepat merupakan faktor penentu utama keselamatan dan daya tahan struktur bangunan tempat tinggal Anda dalam jangka panjang. Penggunaan tipe semen yang tidak tepat pada elemen struktur berisiko menimbulkan retak rambut hingga kegagalan struktur saat terjadi goncangan gempa bumi.</p><h2>1. Kenali Perbedaan Semen PCC dan OPC</h2><p>Semen Portland Composite (PCC) merupakan semen serbaguna yang dirancang khusus untuk pekerjaan perumahan, pasangan dinding, plesteran, dan acian. Kandungan material anorganik tambahannya memberikan daya plastisitas yang baik dan menghasilkan permukaan dinding yang lebih halus serta minim retak susut.</p><p>Sementara itu, Semen Portland Tipe I (OPC) memiliki laju hidrasi lebih cepat dan kekuatan tekan awal yang lebih tinggi, sehingga sangat ideal untuk pengecoran struktur bertulang berat, kolom sloof, dan pondasi bore pile.</p><h2>2. Standar Uji Mutu dan Sertifikasi SNI</h2><p>Pastikan setiap sak semen yang Anda beli memiliki cap logo SNI timbul pada kemasannya. Mutu semen SNI menjamin kehalusan butiran (Blaine fineness) di atas 280 m²/kg serta waktu ikat awal (initial setting time) tidak kurang dari 45 menit, sehingga para tukang memiliki waktu pengerjaan yang cukup.</p><h3>Uji Sederhana di Lapangan</h3><p>Ambil segenggam semen dari dalam sak. Semen berkualitas prima terasa dingin, halus seperti bedak, dan bebas dari butiran gumpalan keras (lump). Jika ditemukan gumpalan yang tidak hancur saat ditekan jari, semen tersebut sudah terpapar kelembapan udara dan sebaiknya tidak digunakan untuk pengecoran struktur.</p><h2>3. Tips Penyimpanan Sak Semen di Lokasi Proyek</h2><p>Hindari meletakkan sak semen langsung di atas lantai semen atau tanah. Gunakan palet kayu dengan jarak minimal 15-20 cm dari permukaan tanah, dan beri jarak setidaknya 30 cm dari dinding pembatas. Tumpukan maksimal yang disarankan adalah 10 sak untuk mencegah pemadatan gravitasi pada lapisan paling bawah.</p><h2>4. Estimasi Kebutuhan dan Konsultasi Teknis</h2><p>Untuk pekerjaan beton struktur perbandingan 1:2:3 standar (semen : pasir : kerikil), rata-rata 1 meter kubik beton membutuhkan sekitar 6 hingga 7 sak semen ukuran 50 kg. Konsultasikan rancangan denah Anda dengan tim teknis Supermaterial untuk mendapatkan estimasi kebutuhan material yang presisi.</p>',
            'image' => 'articles/artikel-semen-1.svg',
            'image_caption' => 'Aplikasi semen mutu tinggi untuk struktur sloof dan kolom rumah tinggal bertingkat.',
            'image_credit' => 'Dok. Supermaterial',
            'author_id' => $admin->id,
            'editor_name' => 'Gema Suaria',
            'author_initials' => 'gar',
            'editor_initials' => 'ed',
            'dateline' => 'Medan, Supermaterial --',
            'views_count' => 1540,
            'is_featured' => true,
            'published_at' => now()->subDays(2),
        ]);
        $art1->categories()->syncWithoutDetaching([$cats['Semen & Perekat']->id]);
        $art1->tags()->syncWithoutDetaching([$tags['semen']->id, $tags['sni']->id, $tags['pondasi']->id, $tags['tips-konstruksi']->id]);
        $art1->products()->syncWithoutDetaching(Product::whereIn('slug', ['semen-tiga-roda-50-kg', 'semen-holcim-serba-guna-40-kg', 'besi-beton-polos-10-mm'])->pluck('id'));

        $art2 = Article::updateOrCreate(['slug' => 'panduan-memilih-besi-beton-sni'], [
            'title' => 'Panduan Memilih Besi Beton SNI: Cara Membaca Marking dan Toleransi Diameter',
            'summary' => 'Ketahui cara membedakan besi beton SNI asli dengan besi non-standar melalui pembacaan marking pabrik, timbangan berat per batang, dan toleransi resmi.',
            'content' => '<p>Di pasaran material konstruksi, istilah besi banci atau besi non-standar masih sering dijumpai. Menggunakan besi tulangan yang diameternya di bawah standar resmi SNI dapat membahayakan kapasitas beban lentur balok dan kolom rumah tinggal.</p><h2>1. Membaca Marking Timbul pada Besi Beton</h2><p>Besi beton SNI resmi wajib memiliki tanda timbul (marking) yang memuat inisial nama produsen (contoh: KS untuk Krakatau Steel), nomor standar SNI, diameter nominal, dan kelas baja tulangan (BjTP untuk polos, BjTS untuk ulir).</p><h2>2. Toleransi Diameter Resmi Menurut SNI 2052:2017</h2><p>Badan Standardisasi Nasional menetapkan batas toleransi diameter maksimal untuk besi diameter 10 mm adalah ±0.4 mm. Jika diameter yang terukur menggunakan jangka sorong kurang dari 9.6 mm, maka besi tersebut masuk kategori non-standar.</p><h3>Tabel Berat Minimum per Batang</h3><p>Besi polos 10 mm standar SNI panjang 12 meter memiliki berat nominal sekitar 7.4 kg per batang. Menimbang bundel besi merupakan cara tercepat memverifikasi keaslian material di gudang distributor.</p><h2>3. Rekomendasi Aplikasi Kolom dan Balok</h2><p>Untuk struktur rumah 2 lantai, kombinasikan besi tulangan utama ulir BjTS dengan sengkang (begel) polos diameter minimal 8 mm atau 10 mm untuk perlindungan lateral saat gempa.</p>',
            'image' => 'articles/artikel-besi-1.svg',
            'image_caption' => 'Pemeriksaan marking timbul dan diameter penampang besi beton SNI menggunakan kaliper.',
            'image_credit' => 'Foto: Antara / Supermaterial',
            'author_id' => $admin->id,
            'editor_name' => 'Gema Suaria',
            'author_initials' => 'gar',
            'editor_initials' => 'ed',
            'dateline' => 'Jakarta, Supermaterial --',
            'views_count' => 1220,
            'is_featured' => false,
            'published_at' => now()->subDays(3),
        ]);
        $art2->categories()->syncWithoutDetaching([$cats['Besi & Baja']->id]);
        $art2->tags()->syncWithoutDetaching([$tags['besi-beton']->id, $tags['sni']->id, $tags['pondasi']->id]);
        $art2->products()->syncWithoutDetaching(Product::whereIn('slug', ['besi-beton-polos-10-mm'])->pluck('id'));

        $art3 = Article::updateOrCreate(['slug' => 'tips-mengecat-dinding-baru-bebas-jamur'], [
            'title' => 'Tips Mengecat Dinding Baru agar Cat Tidak Mengelupas dan Bebas Jamur',
            'summary' => 'Langkah persiapan plesteran acian, batas kadar air dan keasaman pH dinding, serta teknik cat dasar alkali resistant sealer.',
            'content' => '<p>Banyak pemilik rumah mengeluhkan cat dinding yang baru diaplikasikan menggelembung atau muncul bercak putih kekuningan setelah beberapa bulan. Masalah ini 90% disebabkan oleh proses pengecatan yang dilakukan sebelum plesteran benar-benar matang.</p><h2>1. Tunggu Masa Kering Plesteran & Acian (Minimal 28 Hari)</h2><p>Semen membutuhkan waktu 28 hari untuk proses hidrasi sempurna. Selama periode ini, kadar kelembaban (moisture content) harus turun di bawah 16% dan tingkat keasaman (pH) berada di angka 7-8.</p><h2>2. Wajib Menggunakan Cat Dasar Alkali Resisting Primer</h2><p>Jangan langsung mengaplikasikan cat topcoat berwarna pada plesteran acian. Lapisan alkali sealer berfungsi menahan garam-garam alkali semen agar tidak merusak pigmen cat dekoratif.</p><h2>3. Teknik Pelapisan untuk Hasil Halus</h2><p>Aplikasikan minimal dua lapis cat dinding berkualitas dengan interval waktu pengeringan 1 hingga 2 jam antar lapisan untuk warna solid dan daya sebar optimal.</p>',
            'image' => 'articles/artikel-cat-1.svg',
            'image_caption' => 'Pengecatan dinding plesteran interior menggunakan roller micro-fiber.',
            'image_credit' => 'Dok. Supermaterial',
            'author_id' => $admin->id,
            'editor_name' => 'Gema Suaria',
            'author_initials' => 'gar',
            'editor_initials' => 'ed',
            'dateline' => 'Surabaya, Supermaterial --',
            'views_count' => 980,
            'is_featured' => false,
            'published_at' => now()->subDays(4),
        ]);
        $art3->categories()->syncWithoutDetaching([$cats['Cat & Pelapis']->id]);
        $art3->tags()->syncWithoutDetaching([$tags['cat-tembok']->id, $tags['tips-konstruksi']->id]);
        $art3->products()->syncWithoutDetaching(Product::whereIn('slug', ['cat-tembok-dulux-catylac-5-kg'])->pluck('id'));

        $art4 = Article::updateOrCreate(['slug' => 'perbandingan-mortar-instan-vs-semen-pasir'], [
            'title' => 'Perbandingan Mortar Instan vs Semen Pasir Tradisional untuk Dinding Bata Ringan',
            'summary' => 'Analisis komparatif efisiensi biaya material, kecepatan kerja tukang, dan ketahanan dinding bata hebel menggunakan semen instan tipis.',
            'content' => '<p>Pemasangan bata ringan (hebel) memerlukan perekat khusus dengan ketebalan spesi tipis sekitar 3 mm. Penggunaan adukan semen-pasir manual pada bata ringan terbukti menurunkan daya rekat dan menambah beban bobot dinding.</p><h2>1. Konsistensi Komposisi Pabrikan</h2><p>Mortar instan diproduksi secara terukur menggunakan pasir silika murni, semen Portland, dan aditif polimer rekat tinggi. Hal ini meniadakan risiko kesalahan perbandingan takaran pasir di lapangan.</p><h2>2. Kecepatan Instalasi dan Penghematan Waktu</h2><p>Cukup tambahkan air bersih sesuai rasio kemasan dan aduk dengan mixer. Tukang dapat menyelesaikan volume dinding 2 kali lebih cepat dibandingkan mengayak pasir konvensional.</p><h2>3. Perbandingan Anggaran Biaya Proyek</h2><p>Meskipun harga per sak mortar tampak lebih tinggi, penghematan dari eliminasi ongkos kirim pasir, eliminasi limbah sisa ayak, serta ketebalan spesi tipis menjadikan biaya total per meter persegi jauh lebih hemat.</p>',
            'image' => 'articles/artikel-mortar-1.svg',
            'image_caption' => 'Pemasangan bata ringan hebel menggunakan sendok semen bergigi mortar instan.',
            'image_credit' => 'Dok. Supermaterial',
            'author_id' => $admin->id,
            'editor_name' => 'Gema Suaria',
            'author_initials' => 'gar',
            'editor_initials' => 'ed',
            'dateline' => 'Bandung, Supermaterial --',
            'views_count' => 750,
            'is_featured' => false,
            'published_at' => now()->subDays(5),
        ]);
        $art4->categories()->syncWithoutDetaching([$cats['Mortar']->id, $cats['Semen & Perekat']->id]);
        $art4->tags()->syncWithoutDetaching([$tags['mortar']->id, $tags['tips-konstruksi']->id]);
        $art4->products()->syncWithoutDetaching(Product::whereIn('slug', ['mortar-instan-perekat-bata-ringan-40-kg'])->pluck('id'));

        $art5 = Article::updateOrCreate(['slug' => 'kesalahan-pemasangan-rangka-baja-ringan'], [
            'title' => '7 Kesalahan Umum dalam Pemasangan Rangka Baja Ringan Atap Rumah',
            'summary' => 'Waspadai kesalahan jarak kuda-kuda, salah baut screw, dan ketidaksesuaian beban genteng yang dapat menyebabkan atap melengkung atau ambruk.',
            'content' => '<p>Rangka atap baja ringan telah menggantikan kayu sebagai pilihan utama konstruksi modern. Namun, kesalahan kalkulasi beban angin dan pemasangan bracing lateral dapat berakibat fatal pada integritas atap.</p><h2>1. Mengabaikan Beban Jenis Penutup Atap</h2><p>Beban genteng tanah liat atau genteng beton jauh lebih berat dibandingkan atap spandek galvalum. Profil baja ringan kanal C75 dengan ketebalan 0.75 mm TCT wajib dihitung dengan jarak kuda-kuda maksimal 1.2 meter.</p><h2>2. Salah Memilih Baut Self-Drilling Screw</h2><p>Gunakan baut galvanis tahan karat dengan lapisan antikarat teruji. Baut yang berkarat akan memicu korosi bimetalik pada profil baja ringan.</p><h2>3. Kurangnya Batang Pengaku (Bracing Lateral)</h2><p>Bracing diagonal berfungsi menahan gaya geser angin. Jangan mengurangi jumlah batang pengaku demi memangkas biaya material.</p>',
            'image' => 'articles/artikel-atap-1.svg',
            'image_caption' => 'Rangka kuda-kuda baja ringan kanal C dengan bracing diagonal terstandarisasi.',
            'image_credit' => 'Dok. Supermaterial',
            'author_id' => $admin->id,
            'editor_name' => 'Gema Suaria',
            'author_initials' => 'gar',
            'editor_initials' => 'ed',
            'dateline' => 'Medan, Supermaterial --',
            'views_count' => 610,
            'is_featured' => false,
            'published_at' => now()->subDays(6),
        ]);
        $art5->categories()->syncWithoutDetaching([$cats['Besi & Baja']->id]);
        $art5->tags()->syncWithoutDetaching([$tags['baja-ringan']->id, $tags['sni']->id, $tags['tips-konstruksi']->id]);

        // Hubungkan related articles antar artikel
        $art1->relatedArticles()->syncWithoutDetaching([$art2->id, $art4->id]);
        $art2->relatedArticles()->syncWithoutDetaching([$art1->id, $art5->id]);
        $art3->relatedArticles()->syncWithoutDetaching([$art1->id, $art4->id]);
        $art4->relatedArticles()->syncWithoutDetaching([$art1->id, $art3->id]);
        $art5->relatedArticles()->syncWithoutDetaching([$art2->id, $art1->id]);

        foreach ([['Tentang Kami', 'tentang', '<p>Supermaterial adalah katalog bahan bangunan yang membantu Anda menemukan produk tepat dan membelinya lewat kanal favorit.</p>'],
            ['Kontak', 'kontak', '<p>Hubungi kami lewat WhatsApp untuk konsultasi kebutuhan material proyek.</p>'],
            ['Kebijakan Privasi', 'kebijakan-privasi', '<p>Kami tidak menyimpan data pribadi pengunjung. Klik tombol beli dicatat secara anonim untuk analitik.</p>'],
            ['Syarat Penggunaan', 'syarat-penggunaan', '<p>Harga di situs ini bersifat acuan. Transaksi dilakukan di marketplace atau langsung dengan penjual.</p>'],
        ] as [$title, $slug, $content]) {
            Page::updateOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'is_published' => true]);
        }
    }
}
