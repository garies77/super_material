<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PowerToolsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Kategori Power Tools & Perkakas Proyek
        $parentCategory = Category::updateOrCreate(['slug' => 'power-tools-mesin'], [
            'name' => 'Power Tools & Mesin',
            'intro' => 'Peralatan pertukangan, mesin bor, gerinda, circular saw, inverter las, kompresor, jack hammer, dan alat ukur laser berdaya tahan tinggi untuk proyek konstruksi dan profesional.',
            'sort_order' => 5,
            'is_active' => true,
            'bottom_content' => '<h2>Panduan Memilih Power Tools & Mesin Konstruksi</h2><p>Pilih power tools brushless untuk efisiensi baterai maksimal dan ketahanan motor jangka panjang. Perhatikan voltase (18V-20V untuk mobilitas tanpa kabel, atau kabel listrik AC untuk pekerjaan berat nonstop). Pastikan selalu menggunakan alat pelindung diri (APD) seperti kacamata pelindung dan sarung tangan saat mengoperasikan mesin.</p>',
        ]);

        $subCategories = [
            'bor-bobok' => Category::updateOrCreate(['slug' => 'mesin-bor-bobok'], [
                'name' => 'Mesin Bor & Bobok',
                'parent_id' => $parentCategory->id,
                'intro' => 'Bor impact cordless brushless, rotary hammer drill beton SDS-Plus, dan mesin demolition jack hammer heavy duty.',
                'sort_order' => 1,
                'is_active' => true,
            ]),
            'gerinda-potong' => Category::updateOrCreate(['slug' => 'mesin-pemotong-gerinda'], [
                'name' => 'Mesin Pemotong & Gerinda',
                'parent_id' => $parentCategory->id,
                'intro' => 'Mesin gerinda cordless 4 inch, gergaji circular saw kayu presisi, dan chainsaw mesin bensin.',
                'sort_order' => 2,
                'is_active' => true,
            ]),
            'ukur-las' => Category::updateOrCreate(['slug' => 'alat-ukur-digital-las'], [
                'name' => 'Alat Ukur Digital & Las',
                'parent_id' => $parentCategory->id,
                'intro' => 'Waterpass laser level 4D 16-line green beam, meteran laser digital, dan trafo las inverter IGBT.',
                'sort_order' => 3,
                'is_active' => true,
            ]),
            'pneumatik' => Category::updateOrCreate(['slug' => 'pneumatik-kompresor'], [
                'name' => 'Pneumatik & Kompresor',
                'parent_id' => $parentCategory->id,
                'intro' => 'Kompresor angin silent oil-less, air nailer gun paku tembak, dan aksesoris pneumatic workshop.',
                'sort_order' => 4,
                'is_active' => true,
            ]),
            'manual-proyek' => Category::updateOrCreate(['slug' => 'alat-proyek-perkakas-manual'], [
                'name' => 'Alat Proyek & Perkakas Manual',
                'parent_id' => $parentCategory->id,
                'intro' => 'Sekop baja tempa, palu godam sledge hammer anti-vibrasi, dan gerobak sorong pasir proyek heavy duty.',
                'sort_order' => 5,
                'is_active' => true,
            ]),
        ];

        // 2. Merek / Brand Power Tools & Hardware
        $brands = [
            'pro-power' => Brand::updateOrCreate(['slug' => 'pro-power-tools'], ['name' => 'Pro-Power Tools']),
            'powertek' => Brand::updateOrCreate(['slug' => 'powertek-industrial'], ['name' => 'Powertek Industrial']),
            'arcweld' => Brand::updateOrCreate(['slug' => 'arcweld-pro'], ['name' => 'Arcweld Pro']),
            'huepar' => Brand::updateOrCreate(['slug' => 'huepar-pro'], ['name' => 'Huepar Pro']),
            'masterforce' => Brand::updateOrCreate(['slug' => 'masterforce-heavy-duty'], ['name' => 'Masterforce Heavy Duty']),
            'maktec' => Brand::updateOrCreate(['slug' => 'maktec-by-makita'], ['name' => 'Maktec by Makita']),
            'forge-pro' => Brand::updateOrCreate(['slug' => 'forge-pro-heavy-duty'], ['name' => 'Forge Pro Heavy Duty']),
            'ultra-steel' => Brand::updateOrCreate(['slug' => 'ultra-steel-pro'], ['name' => 'Ultra-Steel Pro']),
            'toughbuilt' => Brand::updateOrCreate(['slug' => 'toughbuilt-forge'], ['name' => 'ToughBuilt Forge']),
            'max-pro' => Brand::updateOrCreate(['slug' => 'max-pro-industrial'], ['name' => 'Max-Pro Industrial']),
            'aeroflow' => Brand::updateOrCreate(['slug' => 'aeroflow-silent'], ['name' => 'AeroFlow Silent']),
            'pneufast' => Brand::updateOrCreate(['slug' => 'pneufast-industrial'], ['name' => 'PneuFast Industrial']),
            'pro-wheel' => Brand::updateOrCreate(['slug' => 'pro-wheelbarrow'], ['name' => 'Pro-Wheelbarrow']),
        ];

        // 3. Sales Channels
        $channels = SalesChannel::where('is_active', true)->get()->keyBy('slug');

        // 4. Data Produk Dummy Berkualitas Tinggi (13 Produk)
        $products = [
            // --- Batch 1: Power Tools Utama ---
            [
                'name' => 'Mesin Bor Impact Cordless Brushless 20V Pro-Power',
                'category_id' => $subCategories['bor-bobok']->id,
                'brand_id' => $brands['pro-power']->id,
                'min' => 580000,
                'max' => 1250000,
                'unit' => 'unit',
                'badge' => 'Terlaris 2026',
                'rating_value' => 4.9,
                'rating_count' => 480,
                'image' => 'products/bor-cordless-impact-drill-20v.jpg',
                'gallery' => ['products/bor-cordless-impact-drill-20v.jpg'],
                'shipping_note' => 'Ready stock gudang Medan & Jabodetabek · Pengiriman same day / instan tersedia',
                'guarantee_note' => 'Garansi Resmi 2 Tahun Sparepart & Servis · 100% Brushless Motor Asli',
                'summary' => 'Mesin bor impact cordless 20V brushless motor bertenaga tinggi dengan torsi maksimal hingga 65 Nm, 2 pilihan kecepatan variabel, chuck all-metal keyless 13mm, serta fitur hammer drill untuk melubangi beton bertulang.',
                'description' => '<p><strong>Mesin Bor Impact Cordless Brushless 20V Pro-Power</strong> dirancang khusus untuk kebutuhan tukang profesional, kontraktor, dan pencinta DIY yang membutuhkan kebebasan mobilitas tanpa kabel dengan tenaga setara mesin bor kabel.</p>
<h2>Keunggulan Utama</h2>
<ul>
    <li><strong>Motor Brushless Generasi Terbaru:</strong> Bebas perawatan carbon brush, temperatur kerja 30% lebih dingin, serta efisiensi konsumsi baterai hingga 50% lebih hemat.</li>
    <li><strong>3 Mode Kerja Lengkap:</strong> Mode obeng presisi (21 setelan torsi), mode bor biasa untuk kayu & besi, serta mode impact hammer untuk dinding bata dan beton.</li>
    <li><strong>All-Metal Keyless Chuck 1/2" (13mm):</strong> Mengunci mata bor kokoh tanpa slip dan tidak mudah aus meski dipakai kerja berat terus-menerus.</li>
    <li><strong>Lampu Kerja LED Terang:</strong> Menyinari area kerja di sudut sempit atau kondisi minim pencahayaan secara otomatis saat tombol ditekan.</li>
</ul>',
                'specs' => [
                    'Tegangan Baterai' => '20V Max Lithium-Ion',
                    'Tipe Motor' => 'Brushless Heavy Duty Motor',
                    'Torsi Maksimum' => '65 Nm (21+3 Tingkat Torsi)',
                    'Kecepatan Putar' => 'Gear 1: 0-450 RPM / Gear 2: 0-1.850 RPM',
                    'Kapasitas Chuck' => 'All-Metal Keyless 13 mm (1/2 Inch)',
                    'Kapasitas Bor' => 'Beton 13 mm, Besi Baja 13 mm, Kayu 38 mm',
                ],
                'variants' => [
                    ['option_group' => 'Kelengkapan Paket', 'name' => 'Unit Only (Tanpa Baterai)', 'slug' => 'unit-only-tanpa-baterai', 'sku' => 'PPT-20V-SOLO', 'price' => 580000, 'compare_at_price' => 650000, 'image_path' => 'products/bor-cordless-impact-drill-20v.jpg'],
                    ['option_group' => 'Kelengkapan Paket', 'name' => 'Paket 1 Baterai 2.0Ah + Fast Charger', 'slug' => 'paket-1-baterai-2ah', 'sku' => 'PPT-20V-1BAT', 'price' => 849000, 'compare_at_price' => 990000, 'image_path' => 'products/bor-cordless-impact-drill-20v.jpg'],
                    ['option_group' => 'Kelengkapan Paket', 'name' => 'Paket Lengkap 2 Baterai 4.0Ah + Koper Hardcase', 'slug' => 'paket-lengkap-2-baterai-4ah', 'sku' => 'PPT-20V-2BAT-FULL', 'price' => 1250000, 'compare_at_price' => 1450000, 'image_path' => 'products/bor-cordless-impact-drill-20v.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah bor impact cordless ini kuat untuk mengebor dak beton?', 'answer' => 'Ya, dengan impact rate tinggi dan mode hammer drill, alat ini mampu melubangi dinding bata ringan, bata merah, hingga dak beton s.d. diameter 13mm.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Mesin Gerinda Tangan Cordless 4 Inch Brushless Powertek 18V/20V',
                'category_id' => $subCategories['gerinda-potong']->id,
                'brand_id' => $brands['powertek']->id,
                'min' => 590000,
                'max' => 1350000,
                'unit' => 'unit',
                'badge' => 'Heavy Duty',
                'rating_value' => 4.85,
                'rating_count' => 315,
                'image' => 'products/gerinda-tangan-cordless-4-inch.jpg',
                'gallery' => ['products/gerinda-tangan-cordless-4-inch.jpg'],
                'shipping_note' => 'Pengiriman gratis ongkir dengan kurir kargo & reguler · Packing bubble tebal',
                'guarantee_note' => 'Garansi Resmi Motor Brushless 18 Bulan · Bebas Biaya Servis Selamanya',
                'summary' => 'Mesin gerinda tangan cordless 4 inci (100mm) berteknologi Brushless motor dengan kecepatan putaran 8.500 RPM, body ramping ergonomis, serta sistem proteksi overload dan anti-kickback.',
                'description' => '<p><strong>Mesin Gerinda Tangan Cordless 4 Inch Powertek</strong> memberikan keleluasaan potong dan ampelas besi maupun keramik tanpa kendala kabel yang terlilit. Ditenagai motor Brushless bertenaga besar dengan getaran rendah.</p>',
                'specs' => [
                    'Diameter Mata Gerinda' => '100 mm (4 Inch)',
                    'Ukuran Spindle' => 'M10 Standard',
                    'Kecepatan Putar' => '8.500 RPM (3-Speed Selector)',
                    'Sistem Baterai' => 'Pro-Flex 18V / 20V Max Li-Ion',
                    'Fitur Keamanan' => 'Electronic Brake, Soft-Start, Anti-Restart',
                ],
                'variants' => [
                    ['option_group' => 'Pilihan Varian', 'name' => 'Bare Tool (Tanpa Baterai)', 'slug' => 'bare-tool-gerinda', 'sku' => 'PTK-AG100-SOLO', 'price' => 590000, 'compare_at_price' => 690000, 'image_path' => 'products/gerinda-tangan-cordless-4-inch.jpg'],
                    ['option_group' => 'Pilihan Varian', 'name' => 'Full Set 1x Baterai 5.0Ah + Fast Charger', 'slug' => 'set-1-baterai-5ah', 'sku' => 'PTK-AG100-5AH', 'price' => 990000, 'compare_at_price' => 1150000, 'image_path' => 'products/gerinda-tangan-cordless-4-inch.jpg'],
                    ['option_group' => 'Pilihan Varian', 'name' => 'Dual Battery Pack (2x 5.0Ah + Koper + 5 Batu Potong)', 'slug' => 'dual-pack-5ah-koper', 'sku' => 'PTK-AG100-DUAL', 'price' => 1350000, 'compare_at_price' => 1550000, 'image_path' => 'products/gerinda-tangan-cordless-4-inch.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah bisa dipakai untuk memotong keramik granit?', 'answer' => 'Bisa. Gunakan mata gerinda tipe diamond wheel kering atau basah diameter 4 inci.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Mesin Las Inverter Digital Mini Arcweld Pro MMA-160 IGBT',
                'category_id' => $subCategories['ukur-las']->id,
                'brand_id' => $brands['arcweld']->id,
                'min' => 650000,
                'max' => 820000,
                'unit' => 'unit',
                'badge' => 'Hemat Listrik',
                'rating_value' => 4.9,
                'rating_count' => 540,
                'image' => 'products/mesin-las-inverter-digital-arcweld.jpg',
                'gallery' => ['products/mesin-las-inverter-digital-arcweld.jpg'],
                'shipping_note' => 'Siap kirim ke seluruh Indonesia via Cargo & JNE Trucking · Packing kardus ganda tebal',
                'guarantee_note' => 'Garansi Pabrik 1 Tahun Full Sparepart Inverter & Modul IGBT',
                'summary' => 'Mesin las inverter mini bertenaga modul IGBT canggih, tarikan daya listrik mulai 450 Watt, dilengkapi display digital LED ampere presisi, teknologi anti-lengket (Anti-Stick), dan busur api stabil.',
                'description' => '<p><strong>Mesin Las Inverter Digital Mini Arcweld Pro MMA-160</strong> adalah pilihan tepat untuk bengkel las teralis, kanopi, karoseri, maupun perbaikan pagar rumah tinggal. Mampu beroperasi lancar pada daya listrik 450W - 900W.</p>',
                'specs' => [
                    'Rentang Arus Output' => '20A - 160A (Real Ampere)',
                    'Tegangan Input' => '220V ± 15% (50/60 Hz)',
                    'Daya Listrik Awal' => 'Mulai 450 Watt (Kawat 1.6mm - 2.0mm)',
                    'Ukuran Kawat Las' => '1.6 mm s.d. 3.2 mm (RB-26, LB-52, dll)',
                    'Duty Cycle' => '60% pada 160A / 100% pada 120A',
                ],
                'variants' => [
                    ['option_group' => 'Paket Pembelian', 'name' => 'Paket Standar (Unit + Tang Las + Tang Massa + Kabel)', 'slug' => 'paket-standar-las', 'sku' => 'AWP-160-STD', 'price' => 650000, 'compare_at_price' => 750000, 'image_path' => 'products/mesin-las-inverter-digital-arcweld.jpg'],
                    ['option_group' => 'Paket Pembelian', 'name' => 'Paket Pro (+ Topeng Auto-Darkening Otomatis + Kawat Las 1 Kg)', 'slug' => 'paket-pro-auto-darkening', 'sku' => 'AWP-160-PRO', 'price' => 820000, 'compare_at_price' => 950000, 'image_path' => 'products/mesin-las-inverter-digital-arcweld.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah listrik 450 Watt bisa pakai mesin las ini?', 'answer' => 'Bisa. Gunakan kawat las diameter 1.6mm atau 2.0mm dengan setelan ampere 40-70A.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Waterpass Laser 16 Line 4D Green Beam Self-Leveling Huepar Pro',
                'category_id' => $subCategories['ukur-las']->id,
                'brand_id' => $brands['huepar']->id,
                'min' => 720000,
                'max' => 980000,
                'unit' => 'set',
                'badge' => 'Akurasi Tinggi',
                'rating_value' => 4.95,
                'rating_count' => 275,
                'image' => 'products/waterpass-laser-16-line-4d.jpg',
                'gallery' => ['products/waterpass-laser-16-line-4d.jpg'],
                'shipping_note' => 'Sudah termasuk koper hardcase tahan banting · Pengiriman aman berasuransi',
                'guarantee_note' => 'Garansi Kalibrasi 1 Tahun · Dioda Laser Osram Jerman Asli',
                'summary' => 'Laser level 4D 16 baris cahaya hijau super terang dengan sudut proyeksi 360 derajat horizontal & vertikal. Dilengkapi remote control wireless, base micro-adjusting, dan sensor self-leveling otomatis.',
                'description' => '<p><strong>Waterpass Laser 16 Line 4D Green Beam Huepar Pro</strong> adalah instrumen pengukuran presisi mutlak untuk pemasangan keramik lantai/dinding, partisi gipsum, dan plafon drop ceiling.</p>',
                'specs' => [
                    'Jumlah Garis Laser' => '16 Baris (4 Bidang 360 Derajat)',
                    'Warna Laser' => 'Ultra Green Beam (Dioda Osram 515nm)',
                    'Akurasi Garis' => '± 1 mm per 7 Meter',
                    'Jarak Jangkauan' => 'Hingga 30 Meter (50 Meter dengan Detektor)',
                ],
                'variants' => [
                    ['option_group' => 'Pilihan Paket', 'name' => 'Paket Basic Set (+ Mini Base + Remote + Hardcase + 2 Baterai)', 'slug' => 'paket-basic-huepar-4d', 'sku' => 'HUE-4D16-BSC', 'price' => 720000, 'compare_at_price' => 850000, 'image_path' => 'products/waterpass-laser-16-line-4d.jpg'],
                    ['option_group' => 'Pilihan Paket', 'name' => 'Paket Pro (+ Tripod Aluminium 1.2M + Wall Bracket Magnetik)', 'slug' => 'paket-pro-tripod-huepar-4d', 'sku' => 'HUE-4D16-TRIPOD', 'price' => 980000, 'compare_at_price' => 1150000, 'image_path' => 'products/waterpass-laser-16-line-4d.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah laser ini bisa digunakan di luar ruangan?', 'answer' => 'Bisa, laser sinar hijau sangat terang dan jelas terlihat.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Gergaji Circular Saw Cordless 7-1/4 Inch 20V Masterforce Heavy Duty',
                'category_id' => $subCategories['gerinda-potong']->id,
                'brand_id' => $brands['masterforce']->id,
                'min' => 1150000,
                'max' => 1680000,
                'unit' => 'unit',
                'badge' => 'Top Precision',
                'rating_value' => 4.88,
                'rating_count' => 190,
                'image' => 'products/circular-saw-cordless-7-inch.jpg',
                'gallery' => ['products/circular-saw-cordless-7-inch.jpg'],
                'shipping_note' => 'Packing kayu & bubble tebal · Aman kirim ke seluruh nusantara',
                'guarantee_note' => 'Garansi Resmi 2 Tahun Masterforce Heavy Duty Power Tools',
                'summary' => 'Gergaji sirkular nirkabel 7-1/4 inci bertenaga baterai 20V Max Brushless dengan kecepatan 4.800 RPM. Dilengkapi pisau karbida 24T tajam, pelat dasar aluminium tebal tahan bengkok, dan sudut kemiringan potong 0° - 50°.',
                'description' => '<p><strong>Gergaji Circular Saw Cordless 7-1/4 Inch Masterforce</strong> menghadirkan tenaga potong setara circular saw listrik colokan. Sangat ideal untuk kayu balok, papan triplek, dan papan semen fiber.</p>',
                'specs' => [
                    'Diameter Mata Pisau' => '185 mm / 7-1/4 Inch (As Bore 20 mm)',
                    'Kecepatan Putaran' => '4.800 RPM',
                    'Sudut Bevel Cut' => '0° s.d. 50° dengan pengunci cepat',
                ],
                'variants' => [
                    ['option_group' => 'Pilihan Varian', 'name' => 'Bare Tool Unit Only (+ 1x Mata Pisau 24T)', 'slug' => 'bare-tool-circular-saw', 'sku' => 'MSF-CS7-SOLO', 'price' => 1150000, 'compare_at_price' => 1290000, 'image_path' => 'products/circular-saw-cordless-7-inch.jpg'],
                    ['option_group' => 'Pilihan Varian', 'name' => 'Full Set (1x Baterai 5.0Ah High Capacity + Fast Charger)', 'slug' => 'full-set-circular-saw-5ah', 'sku' => 'MSF-CS7-5AH', 'price' => 1680000, 'compare_at_price' => 1950000, 'image_path' => 'products/circular-saw-cordless-7-inch.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah gergaji ini sudah termasuk mata potong kayu?', 'answer' => 'Sudah termasuk 1 buah mata pisau 24T carbide tooth bawaan siap pakai.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Mesin Bor Rotary Hammer Drill SDS-Plus Maktec AVT 800W',
                'category_id' => $subCategories['bor-bobok']->id,
                'brand_id' => $brands['maktec']->id,
                'min' => 975000,
                'max' => 1150000,
                'unit' => 'unit',
                'badge' => 'Spesialis Beton',
                'rating_value' => 4.92,
                'rating_count' => 620,
                'image' => 'products/rotary-hammer-drill-maktec.jpg',
                'gallery' => ['products/rotary-hammer-drill-maktec.jpg'],
                'shipping_note' => 'Stok melimpah siap suplai kontraktor sipil dan proyek gedung bertingkat',
                'guarantee_note' => '100% Produk Maktec Asli Pabrik · Garansi Servis 2 Tahun',
                'summary' => 'Mesin bor rotary hammer SDS-Plus dengan teknologi AVT (Anti-Vibration Technology) untuk meredam getaran pada tangan operator. Memiliki 3 mode: Bor Kayu/Besi, Bor Hammer Beton, dan Bobok/Chipping Dinding.',
                'description' => '<p><strong>Mesin Bor Rotary Hammer SDS-Plus Maktec AVT 800W</strong> adalah mesin bor bobok beton bertenaga tinggi dengan mekanisme pneumatik piston bertenaga 2.8 Joule.</p>',
                'specs' => [
                    'Daya Listrik' => '800 Watt (220V - 50Hz)',
                    'Daya Pukul (Impact Energy)' => '2.8 Joule Pneumatic Impact',
                    'Kapasitas Bor' => 'Beton 26 mm, Core Bit 68 mm, Besi 13 mm, Kayu 32 mm',
                ],
                'variants' => [
                    ['option_group' => 'Kelengkapan Paket', 'name' => 'Paket Standar (Unit + Koper Hardcase + Depth Gauge + Handle)', 'slug' => 'paket-standar-maktec-rh', 'sku' => 'MKT-RH26-STD', 'price' => 975000, 'compare_at_price' => 1100000, 'image_path' => 'products/rotary-hammer-drill-maktec.jpg'],
                    ['option_group' => 'Kelengkapan Paket', 'name' => 'Paket Plus (+ 3 Mata Bor SDS Beton + 1 Pahat Pipih + 1 Pahat Lancip)', 'slug' => 'paket-plus-maktec-rh-pahat', 'sku' => 'MKT-RH26-SET', 'price' => 1150000, 'compare_at_price' => 1300000, 'image_path' => 'products/rotary-hammer-drill-maktec.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah bisa dipakai membongkar keramik lantai?', 'answer' => 'Sangat bisa dengan memutar tuas ke mode pahat/bobok murni.', 'sort_order' => 0],
                ],
            ],

            // --- Batch 2: Heavy Duty Construction & Pneumatics ---
            [
                'name' => 'Gergaji Mesin Chainsaw Bensin 22 Inch Forge Pro 65cc Heavy Duty',
                'category_id' => $subCategories['gerinda-potong']->id,
                'brand_id' => $brands['forge-pro']->id,
                'min' => 1450000,
                'max' => 1850000,
                'unit' => 'unit',
                'badge' => 'Tenaga Ekstra',
                'rating_value' => 4.88,
                'rating_count' => 210,
                'image' => 'products/gergaji-mesin-chainsaw-bensin-22-inch.jpg',
                'gallery' => ['products/gergaji-mesin-chainsaw-bensin-22-inch.jpg'],
                'shipping_note' => 'Packing kardus + peti kayu aman · Siap kirim ke proyek konstruksi & perkebunan',
                'guarantee_note' => 'Garansi Resmi Mesin 1 Tahun · Ketersediaan Rantai & Sparepart Terjamin',
                'summary' => 'Gergaji mesin chainsaw bensin 2-tak 65cc dengan panjang bar guide 22 inci. Dilengkapi sistem recoil starter ringan (Easy Starter) dan pelumasan rantai otomatis untuk tebang pohon dan balok kayu proyek.',
                'description' => '<p><strong>Gergaji Mesin Chainsaw Bensin 22 Inch Forge Pro 65cc</strong> dirancang untuk penebangan kayu keras, pemotongan balok kaso proyek jembatan dan dermaga, serta pembersihan lahan konstruksi.</p>
<h2>Keunggulan Utama</h2>
<ul>
    <li><strong>Mesin 2-Tak 65cc High Torque:</strong> Menghasilkan putaran rantai hingga 11.000 RPM untuk pemotongan cepat dan bersih.</li>
    <li><strong>Easy Recoil Starter:</strong> Tarikan tali starter ringan tanpa hentakan berat berkat sistem dekompresi otomatis.</li>
    <li><strong>Inertia Chain Brake:</strong> Rem pengaman otomatis menghentikan perputaran rantai seketika saat terjadi kickback.</li>
    <li><strong>Sistem Anti-Vibrasi 4 Titik:</strong> Pegas peredam getaran meminimalisir kelelahan tangan pada pemakaian durasi lama.</li>
</ul>',
                'specs' => [
                    'Kapasitas Silinder' => '65 cc (2-Stroke Air-Cooled Engine)',
                    'Panjang Bar Guide' => '22 Inci (55 cm) Laser Tipped',
                    'Kecepatan Rantai Maks' => '11.000 RPM',
                    'Kapasitas Tangki Bensin' => '550 ml (Campuran Bensin & Oli 2-Tak 25:1)',
                    'Kapasitas Tangki Oli Rantai' => '260 ml (Automatic Oiler System)',
                    'Fitur Keamanan' => 'Inertia Chain Brake, Safety Trigger Lock',
                ],
                'variants' => [
                    ['option_group' => 'Pilihan Paket', 'name' => 'Paket Standar (Unit + Bar 22" + 1 Rantai Baja + Toolkit)', 'slug' => 'standar-bar-22-inch', 'sku' => 'FGP-CS65-STD', 'price' => 1450000, 'compare_at_price' => 1650000, 'image_path' => 'products/gergaji-mesin-chainsaw-bensin-22-inch.jpg'],
                    ['option_group' => 'Pilihan Paket', 'name' => 'Paket Komplit (+ 1 Rantai Cadangan Oregon + Botol Campur Takar)', 'slug' => 'komplit-bar-22-inch', 'sku' => 'FGP-CS65-PRO', 'price' => 1850000, 'compare_at_price' => 2100000, 'image_path' => 'products/gergaji-mesin-chainsaw-bensin-22-inch.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Berapa takaran perbandingan bensin dan oli 2-tak?', 'answer' => 'Gunakan perbandingan 25:1 (1 liter bensin dicampur 40 ml oli samping 2-tak berkualitas).', 'sort_order' => 0],
                    ['question' => 'Apakah rantai chainsaw mudah didapatkan di pasaran?', 'answer' => 'Sangat mudah, rantai ukuran 22 inci standar pitch 0.325" tersedia luas di toko perkakas maupun marketplace.', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Sekop Pasir Baja Tempa Ultra-Steel Gagang Fiber Anti Patah',
                'category_id' => $subCategories['manual-proyek']->id,
                'brand_id' => $brands['ultra-steel']->id,
                'min' => 95000,
                'max' => 135000,
                'unit' => 'batang',
                'badge' => 'Baja Tempa',
                'rating_value' => 4.95,
                'rating_count' => 510,
                'image' => 'products/sekop-pasir-baja-tempa-gagang-fiber.jpg',
                'gallery' => ['products/sekop-pasir-baja-tempa-gagang-fiber.jpg'],
                'shipping_note' => 'Pengiriman armada toko & ekspedisi kargo · Pembelian grosir proyek tersedia',
                'guarantee_note' => 'Jaminan Garansi 1 Tahun Baja Tempa Tidak Melengkung atau Patah',
                'summary' => 'Sekop pasir proyek kelas berat terbuat dari baja karbon tempa (forged carbon steel) satu cetakan utuh tanpa las sambungan. Gagang fiberglass tubular tebal dengan D-grip ergonomis anti-pecah.',
                'description' => '<p><strong>Sekop Pasir Baja Tempa Ultra-Steel</strong> didesain untuk kebutuhan pemindahan pasir, kerikil cor, adukan semen, dan penggalian tanah padat tanpa rasa khawatir daun sekop melengkung.</p>
<h2>Keunggulan Utama</h2>
<ul>
    <li><strong>Baja Karbon Tempa Utuh (Forged Mn-Steel):</strong> Ketebalan 1.8 mm dengan proses hardening, anti-penyok saat menghantam batu keras.</li>
    <li><strong>Pijakan Kaki Berlipat (Rolled Foot Step):</strong> Memudahkan injakan kaki pekerja saat menancapkan sekop ke tumpukan material keras.</li>
    <li><strong>Gagang Fiberglass Reinforced:</strong> Jauh lebih kuat dibanding kayu, tahan cuaca panas dan air hujan tanpa risiko lapuk atau patah.</li>
    <li><strong>Ergonomic D-Grip Handle:</strong> Mengurangi tekanan pada telapak tangan dan pergelangan saat mengangkat material berat.</li>
</ul>',
                'specs' => [
                    'Material Daun Sekop' => 'Baja Karbon Tempa Mn-Steel (Tebal 1.8 mm)',
                    'Finishing Daun' => 'Black Powder Coat Anti-Karat & Gesekan',
                    'Tipe Gagang' => 'Solid Core Fiberglass Reinforced Tube',
                    'Model Grip' => 'Heavy Duty Ergonomic Polypropylene D-Grip',
                    'Panjang Total' => '102 cm',
                    'Dimensi Daun' => 'Lebar 24 cm x Panjang 30 cm (Model Kotak)',
                ],
                'variants' => [
                    ['option_group' => 'Bentuk Daun', 'name' => 'Sekop Kotak Pasir & Cor (Square Point)', 'slug' => 'sekop-kotak-pasir', 'sku' => 'ULS-SK-SQR', 'price' => 95000, 'compare_at_price' => 115000, 'image_path' => 'products/sekop-pasir-baja-tempa-gagang-fiber.jpg'],
                    ['option_group' => 'Bentuk Daun', 'name' => 'Sekop Lancip Gali Tanah (Round Point)', 'slug' => 'sekop-lancip-tanah', 'sku' => 'ULS-SK-RND', 'price' => 98000, 'compare_at_price' => 120000, 'image_path' => 'products/sekop-pasir-baja-tempa-gagang-fiber.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah sekop ini mudah berkarat jika kena adukan semen?', 'answer' => 'Daun sekop sudah dilapisi cat oven elektrostatik tahan korosi. Bersihkan dengan air setelah selesai dipakai mengaduk semen.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Palu Godam Sledge Hammer 10 LB ToughBuilt Baja Tempa Anti-Vibrasi',
                'category_id' => $subCategories['manual-proyek']->id,
                'brand_id' => $brands['toughbuilt']->id,
                'min' => 175000,
                'max' => 295000,
                'unit' => 'unit',
                'badge' => 'Heavy Impact',
                'rating_value' => 4.93,
                'rating_count' => 340,
                'image' => 'products/palu-godam-sledge-hammer-10-lb.jpg',
                'gallery' => ['products/palu-godam-sledge-hammer-10-lb.jpg'],
                'shipping_note' => 'Pengiriman instan & kargo seluruh Indonesia · Packing proteksi busa tebal',
                'guarantee_note' => 'Garansi Baja Seumur Hidup (Kepala Palu Tidak Pecah/Gompal)',
                'summary' => 'Palu godam (bodem) 10 lb berbahan baja tempa berkualitas tinggi dengan permukaan pukul cross-hatch bertekstur anti-slip. Dilengkapi gagang fiberglass berkepadatan tinggi penyerap getaran benturan.',
                'description' => '<p><strong>Palu Godam Sledge Hammer ToughBuilt</strong> diciptakan untuk pekerjaan demolition pembongkaran dinding tembok, pemecahan batu belah pondasi, dan pemancangan pasak proyek.</p>
<h2>Keunggulan Utama</h2>
<ul>
    <li><strong>Kepala Baja Tempa 10 LB (Forged Steel):</strong> Ditempa dan dikeraskan secara termal untuk daya tahan tumbukan ekstrem tanpa retak.</li>
    <li><strong>Permukaan Milled Cross-Hatch:</strong> Pola grid pada penampang pukul mencegah kepala palu terpeleset saat menghantam target.</li>
    <li><strong>Sambungan Resin Epoxy Permanen:</strong> Kepala palu terikat mati dengan gagang, tidak akan pernah terlepas atau longgar.</li>
    <li><strong>Gagang Fiberglass Anti-Vibrasi:</strong> Meredam hingga 70% getaran tumbukan balik ke pergelangan tangan tukang.</li>
</ul>',
                'specs' => [
                    'Bobot Kepala Palu' => '10 LB (± 4.54 kg) Baja Tempa Padat',
                    'Permukaan Pukul' => 'Milled Cross-Hatch Grid (Anti-Slip)',
                    'Material Gagang' => 'Fiberglass Core 70% + Selubung Karet TPR',
                    'Panjang Gagang' => '90 cm (Leverage Ayunan Maksimal)',
                    'Aplikasi' => 'Pembongkaran dinding, pemecah batu kali, tiang pancang',
                ],
                'variants' => [
                    ['option_group' => 'Ukuran Bobot', 'name' => 'Ukuran 8 LB (3.6 kg)', 'slug' => 'sledge-hammer-8lb', 'sku' => 'TB-SH-8LB', 'price' => 175000, 'compare_at_price' => 210000, 'image_path' => 'products/palu-godam-sledge-hammer-10-lb.jpg'],
                    ['option_group' => 'Ukuran Bobot', 'name' => 'Ukuran 10 LB (4.5 kg) Standar Proyek', 'slug' => 'sledge-hammer-10lb', 'sku' => 'TB-SH-10LB', 'price' => 225000, 'compare_at_price' => 260000, 'image_path' => 'products/palu-godam-sledge-hammer-10-lb.jpg'],
                    ['option_group' => 'Ukuran Bobot', 'name' => 'Ukuran 14 LB (6.3 kg) Ekstra Berat', 'slug' => 'sledge-hammer-14lb', 'sku' => 'TB-SH-14LB', 'price' => 295000, 'compare_at_price' => 340000, 'image_path' => 'products/palu-godam-sledge-hammer-10-lb.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah gagang palu tahan terhadap ayunan meleset (overstrike)?', 'answer' => 'Ya, leher gagang dilapisi pelindung benturan (overstrike collar) berbahan karet tebal tahan hantaman.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Mesin Demolition Breaker Jack Hammer 30 Kg Max-Pro 2.200W Hex 30mm',
                'category_id' => $subCategories['bor-bobok']->id,
                'brand_id' => $brands['max-pro']->id,
                'min' => 2450000,
                'max' => 2950000,
                'unit' => 'set',
                'badge' => 'Heavy Breaker',
                'rating_value' => 4.96,
                'rating_count' => 185,
                'image' => 'products/jack-hammer-demolition-breaker-30kg.jpg',
                'gallery' => ['products/jack-hammer-demolition-breaker-30kg.jpg'],
                'shipping_note' => 'Pengiriman peti besi beroda + bubble wrap tebal · Ekspedisi kargo darat/laut',
                'guarantee_note' => 'Garansi Servis 2 Tahun & Garansi Armature Tembaga Murni 1 Tahun',
                'summary' => 'Mesin jack hammer demolition breaker kelas 30 kg dengan tenaga motor 2.200 Watt dan daya tumbuk 65 Joule. Sangat tangguh untuk pembongkaran jalan aspal, pondasi cakar ayam, sloof beton bertulang, dan lantai tebal.',
                'description' => '<p><strong>Mesin Demolition Breaker Jack Hammer 30 Kg Max-Pro</strong> adalah monster penghancur beton bertulang, aspal jalan raya, dan struktur pondasi beton komersial.</p>
<h2>Keunggulan Utama</h2>
<ul>
    <li><strong>Motor 2.200 Watt Tembaga Murni:</strong> Menghasilkan daya tumbuk raksasa 65 Joule dengan frekuensi 1.450 pukulan per menit (BPM).</li>
    <li><strong>Chuck Hexagonal 30 mm Quick-Lock:</strong> Mengunci pahat bobok ekstra kokoh dengan sistem tuas pengunci cepat.</li>
    <li><strong>Sistem Suspensi Peredam Pegas:</strong> Gagang T-Handle dilengkapi peredam kejut pegas ganda untuk mereduksi getaran ke bahu operator.</li>
    <li><strong>Bodi Logam Cast Iron Tahan Banting:</strong> Rumah gear aluminium-magnesium cor padat tahan benturan proyek ekstrem.</li>
</ul>',
                'specs' => [
                    'Daya Listrik' => '2.200 Watt (220V - 50Hz Pure Copper)',
                    'Kekuatan Tumbukan' => '65 Joule Pneumatic Heavy Impact',
                    'Kecepatan Pukulan' => '1.450 BPM (Blows Per Minute)',
                    'Tipe Chuck Kunci' => 'Hexagonal 30 mm Quick-Lock Retainer',
                    'Sistem Pelumasan' => 'Oil Bath Lubrication (Awet & Mudah Isi Ulang)',
                    'Berat Bersih Mesin' => '± 28.5 kg',
                ],
                'variants' => [
                    ['option_group' => 'Pilihan Paket', 'name' => 'Paket Standar (Unit + Koper Plat Beroda + 1 Pahat Lancip + 1 Pahat Pipih)', 'slug' => 'standar-jack-hammer', 'sku' => 'MXP-JH30-STD', 'price' => 2450000, 'compare_at_price' => 2850000, 'image_path' => 'products/jack-hammer-demolition-breaker-30kg.jpg'],
                    ['option_group' => 'Pilihan Paket', 'name' => 'Paket Kontraktor (+ 2 Pahat Ekstra Panjang + Pelumas + Sarung Tangan Pro)', 'slug' => 'kontraktor-jack-hammer', 'sku' => 'MXP-JH30-PRO', 'price' => 2950000, 'compare_at_price' => 3350000, 'image_path' => 'products/jack-hammer-demolition-breaker-30kg.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Jenis oli apa yang digunakan untuk pelumasan mesin jack hammer ini?', 'answer' => 'Gunakan oli mesin hidrolik SAE 10W atau oli kompresor ISO VG 46 sebanyak 40-50 ml setiap 50 jam pemakaian.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Kompresor Angin Silent Oil-Less 24L AeroFlow 1.5 HP Ultra Quiet',
                'category_id' => $subCategories['pneumatik']->id,
                'brand_id' => $brands['aeroflow']->id,
                'min' => 1280000,
                'max' => 1650000,
                'unit' => 'unit',
                'badge' => 'Silent & Bebas Oli',
                'rating_value' => 4.91,
                'rating_count' => 430,
                'image' => 'products/kompresor-angin-silent-oilless-24l.jpg',
                'gallery' => ['products/kompresor-angin-silent-oilless-24l.jpg'],
                'shipping_note' => 'Pengiriman armada toko Medan & kargo ekspedisi se-Indonesia · Kardus tebal berlapis',
                'guarantee_note' => 'Garansi 1 Tahun Tabung & Dinamo Tembaga · Suara Halus di Bawah 65 dB',
                'summary' => 'Kompresor angin tanpa oli (oil-less) berkapasitas tabung 24 liter dengan motor ganda 1.5 HP bertenaga namun bersuara super senyap (silent). Udara keluaran 100% murni bebas residu oli, cocok untuk pengecatan duco, paku tembak, dan bengkel rumahan.',
                'description' => '<p><strong>Kompresor Angin Silent Oil-Less 24L AeroFlow</strong> adalah solusi kompresor modern bebas suara bising dan bebas repot ganti oli mesin.</p>
<h2>Keunggulan Utama</h2>
<ul>
    <li><strong>Suara Super Senyap (Hanya 62 dB):</strong> Bisa dipakai kerja malam di area perumahan padat atau klinik/laboratorium tanpa mengganggu sekitar.</li>
    <li><strong>Bebas Oli (100% Oil-Free Air):</strong> Udara yang dihasilkan bersih tanpa uap oli, sangat aman untuk pengecatan halus (duco/furnitur) dan alat pneumatik.</li>
    <li><strong>Pengisian Angin Cepat:</strong> Mengisi tabung 24 liter dari kosong hingga 8 Bar (116 PSI) hanya dalam waktu sekitar 90 detik.</li>
    <li><strong>Dual Output Coupler:</strong> Dilengkapi 2 kran output cepat kuningan untuk menjalankan dua alat sekaligus.</li>
</ul>',
                'specs' => [
                    'Daya Motor' => '1.5 HP (1.100 Watt) Pure Copper Motor',
                    'Kapasitas Tangki' => '24 Liter Tebal Pelat 2.5 mm Anti-Bocor',
                    'Tekanan Angin Maks' => '8 Bar (116 PSI) dengan Auto Pressure Switch',
                    'Debit Udara (Air Delivery)' => '150 Liter / Menit',
                    'Tingkat Kebisingan' => 'Hanya 62 dB (Ultra Silent)',
                ],
                'variants' => [
                    ['option_group' => 'Paket Pembelian', 'name' => 'Unit Only Kompresor 24L', 'slug' => 'unit-only-kompresor-24l', 'sku' => 'AF-AC24-SOLO', 'price' => 1280000, 'compare_at_price' => 1450000, 'image_path' => 'products/kompresor-angin-silent-oilless-24l.jpg'],
                    ['option_group' => 'Paket Pembelian', 'name' => 'Paket Lengkap (+ Selang Recoil 9M + Air Duster + Tire Inflator)', 'slug' => 'paket-lengkap-kompresor-selang', 'sku' => 'AF-AC24-SET', 'price' => 1480000, 'compare_at_price' => 1700000, 'image_path' => 'products/kompresor-angin-silent-oilless-24l.jpg'],
                    ['option_group' => 'Paket Pembelian', 'name' => 'Paket Pro Bengkel (+ Spray Gun F75 + Selang PU 12M + Filter Air)', 'slug' => 'paket-pro-spraygun-kompresor', 'sku' => 'AF-AC24-PRO', 'price' => 1650000, 'compare_at_price' => 1890000, 'image_path' => 'products/kompresor-angin-silent-oilless-24l.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah kompresor ini memerlukan penggantian oli rutin?', 'answer' => 'Tidak. Kompresor ini bertipe oil-less (bebas oli), menggunakan ring piston teflon tahan aus tanpa oli mesin.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Mesin Paku Tembak Angin Pneumatic Brad Nailer F30 PneuFast Industrial',
                'category_id' => $subCategories['pneumatik']->id,
                'brand_id' => $brands['pneufast']->id,
                'min' => 265000,
                'max' => 385000,
                'unit' => 'unit',
                'badge' => 'Presisi Mebel',
                'rating_value' => 4.89,
                'rating_count' => 380,
                'image' => 'products/mesin-paku-tembak-angin-f30-nail-gun.jpg',
                'gallery' => ['products/mesin-paku-tembak-angin-f30-nail-gun.jpg'],
                'shipping_note' => 'Ready stock siap kirim same-day · Free packing bubble wrap tebal',
                'guarantee_note' => 'Garansi Servis 1 Tahun · Piston Baja Tempa Tahan 100.000 Tembakan',
                'summary' => 'Air nailer gun pneumatic tipe F30 untuk paku lurus berkepala mini panjang 10mm hingga 30mm (18 Gauge). Dilengkapi pengatur kedalaman paku, moncong pengaman anti-tembak liar, dan bodi aluminium cor ringan yang kokoh.',
                'description' => '<p><strong>Mesin Paku Tembak Angin F30 PneuFast</strong> adalah alat wajib tukang kayu, instalator plafon PVC/Gypsum, pengrajin pigura, dan pembuat kabinet interior.</p>
<h2>Keunggulan Utama</h2>
<ul>
    <li><strong>Sistem Contact Safety Nose:</strong> Paku tidak akan meluncur jika moncong pengaman belum ditekan ke bidang kayu.</li>
    <li><strong>Rel Magazine Aluminium Hard-Anodized:</strong> Luncuran paku mulus tanpa macet (anti-jamming system) berkapasitas 100 batang paku.</li>
    <li><strong>Bodi Aluminium Die-Cast Ringan:</strong> Bobot hanya 1.2 kg, sangat ringan dan nyaman dipakai bermanuver di posisi plafon atas kepala.</li>
</ul>',
                'specs' => [
                    'Tipe Paku' => 'Straight Brad Nail 18 Gauge (F-10 s.d. F-30)',
                    'Panjang Paku' => '10 mm – 30 mm',
                    'Kapasitas Magazine' => '100 Batang Paku Lurus',
                    'Rentang Tekanan Kerja' => '60 – 100 PSI (4 – 7 Bar)',
                    'Bobot Mesin' => '1.2 kg',
                ],
                'variants' => [
                    ['option_group' => 'Paket Pembelian', 'name' => 'Unit Only Mesin Paku F30 (+ Kunci L + Botol Minyak Pelumas)', 'slug' => 'unit-only-f30', 'sku' => 'PNF-F30-SOLO', 'price' => 265000, 'compare_at_price' => 310000, 'image_path' => 'products/mesin-paku-tembak-angin-f30-nail-gun.jpg'],
                    ['option_group' => 'Paket Pembelian', 'name' => 'Paket Komplit (+ 1 Kotak Paku F30 Isi 5.000 Pcs + Selang Spiral 6 Meter)', 'slug' => 'paket-komplit-paku-f30', 'sku' => 'PNF-F30-SET', 'price' => 385000, 'compare_at_price' => 450000, 'image_path' => 'products/mesin-paku-tembak-angin-f30-nail-gun.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Kompresor ukuran berapa yang dibutuhkan untuk menjalankan paku tembak ini?', 'answer' => 'Cukup menggunakan kompresor mini 0.75 HP hingga 1 HP karena konsumsi udara paku tembak relatif sangat kecil.', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Gerobak Sorong Pasir Heavy Duty Pro-Wheelbarrow Bak Baja Tebal Roda Angin',
                'category_id' => $subCategories['manual-proyek']->id,
                'brand_id' => $brands['pro-wheel']->id,
                'min' => 460000,
                'max' => 540000,
                'unit' => 'unit',
                'badge' => 'Beban 400 Kg',
                'rating_value' => 4.94,
                'rating_count' => 670,
                'image' => 'products/gerobak-sorong-pasir-heavy-duty.jpg',
                'gallery' => ['products/gerobak-sorong-pasir-heavy-duty.jpg'],
                'shipping_note' => 'Pengiriman armada toko Medan sekitar · Luar kota kirim kargo posisi semi-knockdown',
                'guarantee_note' => 'Garansi Rangka Baja Kokoh · Ban Karet Asli Tidak Mudah Sobek',
                'summary' => 'Gerobak sorong pasir tipe Artco dengan bak plat baja press tanpa sambungan (deep-drawn), rangka pipa baja tebal dengan penyangga depan ganda, serta roda ban angin 13 inci yang ringan didorong di medan tanah bergelombang.',
                'description' => '<p><strong>Gerobak Sorong Pasir Pro-Wheelbarrow</strong> adalah armada angkut material andalan mandor dan kuli proyek konstruksi gedung, pengecoran jalan, dan lanskap taman.</p>
<h2>Keunggulan Utama</h2>
<ul>
    <li><strong>Bak Plat Baja Deep-Drawn Seamless:</strong> Plat baja 1.2 mm dicetak utuh tanpa sambungan las, mencegah kebocoran adukan semen basah.</li>
    <li><strong>Kapasitas Angkut Maksimal 400 Kg:</strong> Sanggup mengangkut pasir basah, kerikil, bata merah, dan adukan semen cor tanpa melengkung.</li>
    <li><strong>Rangka Pipa Baja Tebal Ø 32 mm:</strong> Dilengkapi penyangga hidung depan (front tray support) untuk kestabilan saat membuang muatan.</li>
    <li><strong>Roda Ban Angin 13 Inci:</strong> Bantalan bearing presisi membuat dorongan terasa enteng bahkan saat muatan penuh di jalanan berlumpur.</li>
</ul>',
                'specs' => [
                    'Kapasitas Beban Maksimal' => 'Hingga 400 Kg (Volume Basah 65 Liter)',
                    'Ketebalan Plat Bak' => 'Plat Baja 1.2 mm Deep Seamless Press',
                    'Lapisan Finishing' => 'High Quality Red Powder Coating Anti-Gores',
                    'Rangka Pipa' => 'Pipa Baja Galvanis Tebal Ø 32 mm',
                    'Tipe Roda' => 'Ban Angin Karet Asli 13" x 3.25-8 dengan Velg Baja & Bearing',
                ],
                'variants' => [
                    ['option_group' => 'Tipe Roda', 'name' => 'Tipe Roda Ban Angin (Pneumatic Tire - Nyaman di tanah)', 'slug' => 'ban-angin-gerobak', 'sku' => 'PWB-RD-AIR', 'price' => 460000, 'compare_at_price' => 510000, 'image_path' => 'products/gerobak-sorong-pasir-heavy-duty.jpg'],
                    ['option_group' => 'Tipe Roda', 'name' => 'Tipe Roda Ban Mati / Pejal (Solid Tire - Bebas Bocor & Paku)', 'slug' => 'ban-mati-gerobak', 'sku' => 'PWB-RD-SLD', 'price' => 540000, 'compare_at_price' => 610000, 'image_path' => 'products/gerobak-sorong-pasir-heavy-duty.jpg'],
                ],
                'faqs' => [
                    ['question' => 'Apakah gerobak dikirim dalam keadaan sudah dirakit?', 'answer' => 'Untuk pengiriman armada toko wilayah Medan dikirim sudah dirakit siap pakai. Untuk luar kota via ekspedisi kargo dikirim semi-knockdown lengkap dengan baut mur presisi.', 'sort_order' => 0],
                ],
            ],
        ];

        $channelUrls = [
            'shopee' => fn ($name) => 'https://shopee.co.id/search?keyword='.urlencode($name),
            'tokopedia' => fn ($name) => 'https://www.tokopedia.com/search?keyword='.urlencode($name),
            'lazada' => fn ($name) => 'https://www.lazada.co.id/catalog/?q='.urlencode($name),
            'tiktok-shop' => fn ($name) => 'https://shop.tiktok.com/search?q='.urlencode($name),
        ];

        foreach ($products as $item) {
            $product = Product::updateOrCreate(['slug' => Str::slug($item['name'])], [
                'name' => $item['name'],
                'category_id' => $item['category_id'],
                'brand_id' => $item['brand_id'],
                'summary' => $item['summary'],
                'description' => $item['description'],
                'specifications' => $item['specs'],
                'image' => $item['image'],
                'gallery' => $item['gallery'],
                'price_min' => $item['min'],
                'price_max' => $item['max'],
                'unit' => $item['unit'],
                'badge' => $item['badge'] ?? null,
                'shipping_note' => $item['shipping_note'],
                'guarantee_note' => $item['guarantee_note'],
                'rating_value' => $item['rating_value'],
                'rating_count' => $item['rating_count'],
                'rating_updated_at' => now()->subDays(rand(1, 7)),
                'price_display' => 'show',
                'show_quantity' => true,
                'is_published' => true,
                'is_featured' => true,
            ]);

            // Media
            $product->media()->delete();
            ProductMedia::create([
                'product_id' => $product->id,
                'type' => 'image',
                'path' => $item['image'],
                'provider' => 'upload',
                'alt_text' => $product->name,
                'sort_order' => 0,
            ]);

            // Variants
            if (! empty($item['variants'])) {
                $product->variants()->delete();
                foreach ($item['variants'] as $sort => $v) {
                    ProductVariant::create([
                        'product_id' => $product->id,
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

            // Sales Channels Links
            foreach ($channelUrls as $slug => $generator) {
                if (isset($channels[$slug])) {
                    ProductLink::updateOrCreate([
                        'product_id' => $product->id,
                        'sales_channel_id' => $channels[$slug]->id,
                    ], [
                        'url' => $generator($item['name']),
                        'is_active' => true,
                    ]);
                }
            }

            // FAQs
            $product->faqs()->delete();
            if (! empty($item['faqs'])) {
                $product->faqs()->createMany($item['faqs']);
            }
        }
    }
}
