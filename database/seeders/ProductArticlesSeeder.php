<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductArticlesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $author = $admin?->authorProfile() ?? Author::first();

        // 1. Tags Baru
        $newTags = [
            'Power Tools' => 'power-tools',
            'Pneumatik' => 'pneumatik',
            'Alat Ukur' => 'alat-ukur',
            'Alat Manual' => 'alat-manual',
            'Demolition' => 'demolition',
            'Keselamatan Kerja' => 'keselamatan-kerja',
            'Pengelasan' => 'pengelasan',
            'Tips Konstruksi' => 'tips-konstruksi',
            'Standar SNI' => 'sni',
        ];

        $tags = [];
        foreach ($newTags as $name => $slug) {
            $tags[$slug] = Tag::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        // 2. Kategori
        $categories = Category::all()->keyBy('slug');

        // Pastikan direktori artikel ada
        $articlesDir = storage_path('app/public/articles');
        if (! is_dir($articlesDir)) {
            mkdir($articlesDir, 0777, true);
        }

        // 3. Definisi 10 Artikel Lengkap
        $articles = [
            [
                'slug' => 'panduan-bor-cordless-vs-bor-kabel-proyek',
                'title' => 'Bor Cordless vs Bor Kabel Listrik: Mana yang Lebih Efisien untuk Proyek Konstruksi?',
                'summary' => 'Komparasi mendalam antara mesin bor baterai 20V brushless dengan bor listrik konvensional dari segi torsi, mobilitas di ketinggian, efisiensi waktu, dan biaya perawatan.',
                'category_slug' => 'mesin-bor-bobok',
                'badge' => 'KOMPARASI POWER TOOLS',
                'badge_color' => '#f59e0b',
                'bg_glow' => '#eab308',
                'title_l1' => 'BOR CORDLESS VS BOR KABEL',
                'title_l2' => 'EFISIENSI & FLEKSIBILITAS PROYEK',
                'sub_banner' => 'Perbandingan Torsi, Daya Tahan Baterai 20V & Mobilitas Kerja',
                'product_image' => 'products/bor-cordless-impact-drill-20v.jpg',
                'product_slugs' => ['bor-impact-cordless-brushless-20v-pro-power', 'mesin-bor-rotary-hammer-drill-sds-plus-maktec-800w'],
                'tag_slugs' => ['power-tools', 'tips-konstruksi'],
                'views_count' => 1840,
                'is_featured' => true,
                'days_ago' => 1,
                'content' => '<p>Dalam dekade terakhir, perdebatan antara memilih mesin bor nirkabel (cordless) bertenaga baterai lithium-ion atau mesin bor kabel listrik konvensional sering menjadi dilema para mandor dan kontraktor. Kemajuan teknologi motor brushless pada bor cordless modern telah mengubah peta persaingan ini secara dramatis.</p>
<h2>1. Kebebasan Mobilitas di Area Ketinggian dan Sudut Sempit</h2>
<p>Kelemahan terbesar bor kabel adalah ketergantungan pada stopkontak dan kabel rol gulung. Saat tukang harus bekerja di atas perancah (scaffolding), dak atap, atau sudut plafon, kabel yang melintang berisiko tersangkut, terlilit, atau bahkan terputus akibat terinjak material tajam.</p>
<p>Mesin bor cordless 20V seperti <strong>Pro-Power Brushless</strong> memberikan kebebasan manuver 100%. Bobotnya yang seimbang dengan baterai terpasang di pangkal pegangan justru memberikan titik tumpu ergonomis yang lebih nyaman saat mengebor posisi vertikal.</p>
<h2>2. Tenaga & Torsi: Mitos Bor Baterai Kurang Bertenaga</h2>
<p>Dahulu, bor baterai 12V memang memiliki torsi terbatas dan hanya sanggup untuk sekrup kayu lunak. Namun, hadirnya sistem 20V Max yang dipadukan dengan motor brushless mampu menghasilkan torsi nyata hingga 65 Nm. Angka ini setara atau bahkan melampaui bor listrik rumahan kelas 500 Watt.</p>
<p>Motor brushless tidak lagi menggunakan carbon brush (arang). Hasilnya, tidak ada gesekan mekanis yang menimbulkan percikan api dan panas berlebih, sehingga efisiensi energi melonjak hingga 85%.</p>
<h2>3. Kapan Bor Kabel Masih Diperlukan?</h2>
<p>Meskipun bor cordless unggul dalam fleksibilitas, bor kabel tetap memegang peranan penting pada pekerjaan bobok nonstop atau pengeboran beton diameter besar di atas 20mm secara terus-menerus. Untuk pekerjaan struktural berat seperti ini, <strong>Rotary Hammer Drill SDS-Plus 800W</strong> tetap menjadi pilihan utama karena tidak perlu mengkhawatirkan siklus penggantian baterai.</p>
<h2>4. Kesimpulan & Rekomendasi Investasi</h2>
<p>Untuk kontraktor interior, instalatur baja ringan, mebel, dan pekerjaan pemeliharaan gedung harian, berinvestasi pada bor impact cordless 20V dengan paket dual-battery adalah keputusan paling menguntungkan yang memangkas waktu kerja hingga 35% di lapangan.</p>',
            ],
            [
                'slug' => 'kapan-waktunya-menggunakan-jack-hammer-beton',
                'title' => 'Kapan Waktunya Menggunakan Jack Hammer? Panduan Pembongkaran Beton Tanpa Merusak Struktur',
                'summary' => 'Pelajari kriteria ketebalan beton yang memerlukan mesin demolition breaker jack hammer 30kg, teknik pemahatan bersudut 70 derajat, dan cara mencegah retak merambat ke kolom utama.',
                'category_slug' => 'mesin-bor-bobok',
                'badge' => 'TEKNIK DEMOLITION',
                'badge_color' => '#ef4444',
                'bg_glow' => '#dc2626',
                'title_l1' => 'PANDUAN JACK HAMMER BETON',
                'title_l2' => 'TEKNIK BOBOK TANPA RETAK STRUKTUR',
                'sub_banner' => 'Kriteria Daya Tumbuk 65 Joule, Sudut Pahat & Mitigasi Getaran',
                'product_image' => 'products/jack-hammer-demolition-breaker-30kg.jpg',
                'product_slugs' => ['mesin-demolition-breaker-jack-hammer-30kg-max-pro', 'palu-godam-sledge-hammer-10-lb-toughbuilt'],
                'tag_slugs' => ['demolition', 'tips-konstruksi', 'power-tools'],
                'views_count' => 1420,
                'is_featured' => true,
                'days_ago' => 2,
                'content' => '<p>Pembongkaran elemen beton bertulang (demolition) bukan sekadar memukul sekeras mungkin dengan alat terberat. Diperlukan kalkulasi teknis agar getaran tumbukan tidak merusak balok penyangga atau kolom struktur utama bangunan yang tetap dipertahankan.</p>
<h2>1. Batasan Ketebalan: Kapan Palu Manual Tak Lagi Memadai?</h2>
<p>Untuk ketebalan rabat beton di bawah 5 cm atau dinding bata hebel, palu godam manual (sledgehammer) 10 lb masih dapat digunakan. Namun, ketika berhadapan dengan lantai dak tebal di atas 10 cm, pondasi cakar ayam bertulang kawat wiremesh, atau aspal jalan raya, memaksakan alat manual hanya akan membuang tenaga dan waktu pekerja.</p>
<p>Mesin demolition breaker kelas 30 kg dengan daya 2.200 Watt menghasilkan energi kinetik tumbukan hingga 65 Joule dengan kecepatan 1.450 pukulan per menit. Tenaga ini mampu memecah beton mutu K-300 menjadi bongkahan kecil dalam hitungan detik.</p>
<h2>2. Sudut Penetrasi Pahat: Hindari Menusuk Tegak Lurus 90°</h2>
<p>Kesalahan fatal yang sering dilakukan operator pemula adalah memposisikan mata pahat (bull point chisel) tegak lurus 90 derajat ke tengah lempengan beton. Hal ini menyebabkan mata pahat macet terjepit dan energi tumbukan terpantul balik ke mesin.</p>
<ul>
    <li>Posisikan mata pahat pada kemiringan <strong>60 hingga 70 derajat</strong>.</li>
    <li>Mulailah dari tepi lempengan beton terluar dengan jarak sekitar 5-10 cm dari tepian.</li>
    <li>Biarkan bobot mesin yang menekan ke bawah; operator cukup mengarahkan keseimbangan tanpa perlu menekan tubuh terlalu berlebihan.</li>
</ul>
<h2>3. Mencegah Retak Merambat ke Elemen Bersebelahan</h2>
<p>Sebelum memulai pemecahan dengan jack hammer, buatlah alur pemutus (relief cut) menggunakan mesin gerinda potong beton atau circular saw bermata diamond sedalam 3-5 cm di sepanjang batas area yang akan dibongkar. Alur ini bertindak sebagai peredam getaran agar retak rambut tidak menjalar ke keramik atau dinding tetangga.</p>',
            ],
            [
                'slug' => 'keunggulan-kompresor-angin-silent-oilless-interior',
                'title' => 'Kompresor Angin Silent Bebas Oli: Mengapa Pilihan Terbaik untuk Pengecatan Duco & Bengkel Interior?',
                'summary' => 'Ulasan teknologi kompresor tanpa oli (oil-less) beroperasi di bawah 65 dB, kebersihan semprotan udara tanpa bercak minyak, dan kenyamanan kerja di area perumahan padat.',
                'category_slug' => 'pneumatik-kompresor',
                'badge' => 'PNEUMATIK MODERN',
                'badge_color' => '#06b6d4',
                'bg_glow' => '#0891b2',
                'title_l1' => 'KOMPRESOR SILENT BEBAS OLI',
                'title_l2' => 'HASIL PENGECATAN DUCO SEMPURNA',
                'sub_banner' => 'Udara Murni 100% Bebas Minyak & Kebisingan Rendah 62 dB',
                'product_image' => 'products/kompresor-angin-silent-oilless-24l.jpg',
                'product_slugs' => ['kompresor-angin-silent-oilless-24l-aeroflow', 'mesin-paku-tembak-angin-pneumatic-brad-nailer-f30-pneufast'],
                'tag_slugs' => ['pneumatik', 'tips-konstruksi'],
                'views_count' => 1290,
                'is_featured' => true,
                'days_ago' => 2,
                'content' => '<p>Bagi pengrajin mebel, aplikator cat duco, instalator interior, dan pemilik bengkel di area perumahan, suara bising kompresor bensin atau kompresor van-belt konvensional sering memicu komplain dari tetangga. Masalah kedua yang tak kalah merugikan adalah uap oli mesin yang ikut tersemprot dan merusak hasil finishing cat.</p>
<h2>1. Prinsip Kerja Oil-Less: Udara Bersih Tanpa Kontaminasi</h2>
<p>Kompresor konvensional memerlukan oli pelumas pada silinder piston. Seiring pemakaian, oli akan teruapkan dan bercampur dengan udara bertekanan di dalam tabung. Saat dipakai menyemprot cat duco atau pernis melamik, partikel oli ini menimbulkan cacat "mata ikan" (fish-eye defect) berupa bintik kawah pada permukaan cat yang baru mengering.</p>
<p>Kompresor <strong>AeroFlow Silent Oil-Less 24L</strong> menggunakan piston berpelapis teflon komposit tahan gesekan tinggi tanpa setetes pun oli pelumas. Udara yang keluar dari tabung 100% murni dan kering, menjamin kilap cat duco mulus rata seperti kaca.</p>
<h2>2. Tingkat Kebisingan di Bawah 65 dB: Setara Suara Percakapan Normal</h2>
<p>Kompresor silent menggunakan motor sinkron berkutub 4 dengan peredam intake ganda. Kebisingan yang dihasilkan hanya berkisar 60-62 dB. Anda dapat berbicara santai tepat di sebelah mesin yang sedang mengisi angin tanpa perlu berteriak.</p>
<h2>3. Perawatan Super Ringan</h2>
<p>Tidak ada jadwal ganti oli, tidak ada saringan oli yang kotor, dan tidak ada risiko ceceran oli di lantai proyek. Pengguna cukup rutin membuang air kondensasi lewat kran pembuangan di bawah tabung seminggu sekali untuk mencegah korosi internal tabung.</p>',
            ],
            [
                'slug' => 'rahasia-kecepatan-pasang-plafon-paku-tembak-f30',
                'title' => 'Rahasia Kecepatan Pasang Plafon Gypsum & Rangka Kayu dengan Air Nailer F30',
                'summary' => 'Tingkatkan produktivitas pemasangan lis dinding, rangka partisi, dan kitchen set hingga 3 kali lebih cepat dibanding palu manual menggunakan mesin paku tembak pneumatic F30.',
                'category_slug' => 'pneumatik-kompresor',
                'badge' => 'TIPS FINISHING',
                'badge_color' => '#10b981',
                'bg_glow' => '#059669',
                'title_l1' => 'AIR NAILER F30 PNEUMATIK',
                'title_l2' => 'PASANG PLAFON & KABINET 3X CEPAT',
                'sub_banner' => 'Presisi Paku Lurus 18GA, Tanpa Bekas Cacat Pukulan Palu',
                'product_image' => 'products/mesin-paku-tembak-angin-f30-nail-gun.jpg',
                'product_slugs' => ['mesin-paku-tembak-angin-pneumatic-brad-nailer-f30-pneufast', 'kompresor-angin-silent-oilless-24l-aeroflow'],
                'tag_slugs' => ['pneumatik', 'tips-konstruksi'],
                'views_count' => 1150,
                'is_featured' => false,
                'days_ago' => 3,
                'content' => '<p>Memaku lis plafon gypsum atau lis profil kayu di atas tangga dengan satu tangan memegang kayu dan satu tangan memegang palu sering berujung pada paku bengkok atau kepala kayu yang gompal terkena salah hantam palu. Air nailer pneumatik hadir sebagai pengubah permainan di dunia interior.</p>
<h2>1. Paku Tertanam Sempurna dalam 0.1 Detik</h2>
<p>Dengan paku tembak jenis Brad Nailer F30, sebuah paku lurus berkepala kecil ukuran 10mm hingga 30mm (18 Gauge) dapat ditancapkan secara instan hanya dengan menarik pelatuk. Kepala paku langsung tenggelam 1 mm di bawah permukaan kayu, siap langsung ditutup kompon atau dempul tanpa meninggalkan lubang besar.</p>
<h2>2. Akurasi dan Keamanan Contact Safety Tip</h2>
<p>Mesin paku tembak modern seperti <strong>PneuFast F30</strong> dilengkapi sistem pengaman hidung sentuh (contact safety nose). Alat tidak akan bisa menembak ke udara kosong meskipun pelatuk ditekan, kecuali moncong pengaman telah ditekan kuat menempel pada permukaan bidang kerja.</p>
<h2>3. Tekanan Kerja Optimal</h2>
<p>Atur regulator tekanan kompresor pada rentang <strong>70 hingga 85 PSI</strong>. Tekanan yang terlalu tinggi dapat membuat paku menembus terlalu dalam pada kayu lunak, sedangkan tekanan terlalu rendah akan menyisakan kepala paku yang menonjol.</p>',
            ],
            [
                'slug' => 'tips-aman-efisien-mengoperasikan-chainsaw-proyek',
                'title' => 'Panduan Keselamatan Kerja & Efisiensi Mengoperasikan Gergaji Mesin Chainsaw 22 Inch',
                'summary' => 'Langkah-langkah vital persiapan rasio bahan bakar 2-tak 25:1, pengecekan ketegangan rantai, pengoperasian rem inersia, dan postur tubuh aman saat memotong kayu balok proyek.',
                'category_slug' => 'mesin-pemotong-gerinda',
                'badge' => 'KESELAMATAN KERJA K3',
                'badge_color' => '#f97316',
                'bg_glow' => '#ea580c',
                'title_l1' => 'PANDUAN OPERASI CHAINSAW',
                'title_l2' => 'TEKNIK MEMOTONG AMAN & RAWAT RANTAI',
                'sub_banner' => 'Rasio Bensin 2-Tak, Rem Inersia Otomatis & Ketegangan Rantai Bar',
                'product_image' => 'products/gergaji-mesin-chainsaw-bensin-22-inch.jpg',
                'product_slugs' => ['gergaji-mesin-chainsaw-bensin-22-inch-forge-pro', 'gergaji-circular-saw-cordless-7-inch-20v-masterforce'],
                'tag_slugs' => ['keselamatan-kerja', 'power-tools', 'tips-konstruksi'],
                'views_count' => 980,
                'is_featured' => false,
                'days_ago' => 3,
                'content' => '<p>Gergaji mesin (chainsaw) adalah salah satu alat bertenaga paling produktif sekaligus berisiko tinggi di lokasi proyek bila dioperasikan tanpa pemahaman prosedur keselamatan kerja (K3). Pemotongan balok kayu kaso besar, tiang pancang kayu, dan pembersihan dahan pohon membutuhkan kedisiplinan tinggi.</p>
<h2>1. Menyetel Ketegangan Rantai Sebelum Menyalakan Mesin</h2>
<p>Rantai yang terlalu kendur berisiko lepas dari alur bar saat berputar kencang, sedangkan rantai yang terlalu kencang akan membebani kopling dan mempercepat keausan bar. Uji ketegangan yang benar: tarik rantai ke atas di bagian tengah bar dengan sarung tangan; kaki rantai (drive link) tidak boleh keluar sepenuhnya dari alur bar, namun rantai tetap bisa diputar manual dengan jari.</p>
<h2>2. Campuran Bahan Bakar Bensin dan Oli 2-Tak</h2>
<p>Mesin 2-tak 65cc seperti pada <strong>Forge Pro Heavy Duty</strong> membutuhkan pelumasan internal lewat campuran bensin dan oli samping berkualitas standar JASO FC/FD dengan perbandingan <strong>25:1</strong>. Jangan pernah menyalakan chainsaw dengan bensin murni, karena piston akan macet (jammed) dalam hitungan menit akibat ketiadaan pelumasan.</p>
<h2>3. Memahami Bahaya Kickback Zone</h2>
<p>Ujung seperempat bagian atas bar guide adalah zona paling berbahaya (kickback danger zone). Jika area ini menyentuh kayu secara mendadak, gaya reaksi akan melontarkan bar gergaji ke arah atas menghadap operator. Pastikan kedua tangan selalu menggenggam erat gagang wrap-around dan rem inersia rantai dalam kondisi prima.</p>',
            ],
            [
                'slug' => 'revolusi-pasang-keramik-waterpass-laser-4d',
                'title' => 'Revolusi Pasang Keramik & Granit Rata Sempurna Menggunakan Waterpass Laser 4D 16 Line',
                'summary' => 'Tinggalkan selang timbang air dan benang tukang yang melorot. Temukan bagaimana sinar hijau laser level 360 derajat menciptakan elevasi lantai dan dinding dengan akurasi 1 mm per 7 meter.',
                'category_slug' => 'alat-ukur-digital-las',
                'badge' => 'AKURASI KONSTRUKSI',
                'badge_color' => '#14b8a6',
                'bg_glow' => '#0d9488',
                'title_l1' => 'WATERPASS LASER LEVEL 4D',
                'title_l2' => 'PASANG KERAMIK & GRANIT SIKU 90°',
                'sub_banner' => '16 Garis Hijau Osram Jerman 360°, Akurasi 1 mm & Remote Wireless',
                'product_image' => 'products/waterpass-laser-16-line-4d.jpg',
                'product_slugs' => ['waterpass-laser-16-line-4d-green-beam-huepar-pro', 'granit-tile-mulia-60x60-abu', 'keramik-lantai-mulia-40x40-putih'],
                'tag_slugs' => ['alat-ukur', 'tips-konstruksi'],
                'views_count' => 1620,
                'is_featured' => true,
                'days_ago' => 4,
                'content' => '<p>Pemasangan granit tile ukuran besar 60x60 cm atau 80x80 cm menuntut presisi mutlak. Kesalahan elevasi 2 milimeter saja pada sudut keramik akan menimbulkan tepian tajam (lippage) yang mengganggu langkah kaki dan merusak estetika hunian mewah.</p>
<h2>1. Keterbatasan Cara Tradisional (Selang Air & Tali Benang)</h2>
<p>Metode konvensional menggunakan selang timbang air rawan kesalahan akibat adanya gelembung udara terjepit di dalam selang atau pembacaan meniskus air yang meleset. Selain itu, tali benang sering melendut di bentangan ruangan yang lebar di atas 6 meter.</p>
<h2>2. Proyeksi 360 Derajat di 4 Bidang Sekaligus</h2>
<p>Waterpass laser level 4D seperti <strong>Huepar Pro 16 Line</strong> memproyeksikan dua bidang garis horizontal (atas dan bawah dekat lantai) serta dua bidang vertikal yang saling berpotongan siku sempurna 90 derajat di lantai dan plafon. Sinar laser garis bawah yang berada hanya 1.5 cm di atas lantai memudahkan tukang memeriksa ketebalan spesi mortar secara langsung.</p>
<h2>3. Keunggulan Dioda Sinar Hijau Osram Jerman</h2>
<p>Mata manusia jauh lebih peka terhadap spektrum cahaya hijau (panjang gelombang 515-532 nm) dibandingkan sinar merah. Sinar hijau tampak hingga 4 kali lebih terang di ruangan proyek yang bermandikan cahaya jendela siang hari tanpa memerlukan kacamata penegas laser.</p>',
            ],
            [
                'slug' => 'teknik-las-inverter-450-watt-anti-lengket',
                'title' => 'Teknik Las Inverter Daya Rendah 450 Watt: Cara Mengatasi Kawat Las Sering Menempel',
                'summary' => 'Panduan praktis pengelasan teralis dan rangka besi pipa hollow pada meteran listrik rumah 450W - 900W tanpa membuat MCB anjlok, memanfaatkan fitur Arc Force dan Hot Start.',
                'category_slug' => 'alat-ukur-digital-las',
                'badge' => 'TUTORIAL PENGELASAN',
                'badge_color' => '#8b5cf6',
                'bg_glow' => '#7c3aed',
                'title_l1' => 'TEKNIK LAS INVERTER 450W',
                'title_l2' => 'ANTI LENGKET PADA DAYA LISTRIK RUMAH',
                'sub_banner' => 'Modul IGBT Modern, Setelan Ampere Presisi & Pilihan Kawat Las',
                'product_image' => 'products/mesin-las-inverter-digital-arcweld.jpg',
                'product_slugs' => ['mesin-las-inverter-digital-mini-arcweld-pro-mma-160', 'besi-beton-polos-10-mm'],
                'tag_slugs' => ['pengelasan', 'tips-konstruksi', 'power-tools'],
                'views_count' => 1340,
                'is_featured' => false,
                'days_ago' => 4,
                'content' => '<p>Banyak pemilik rumah dan bengkel las pemula mengeluhkan kawat las yang sering menempel mati (sticking) pada pelat besi saat menyalakan busur pertama kali, yang kemudian disusul dengan anjloknya sekring MCB PLN daya 450 Watt atau 900 Watt.</p>
<h2>1. Pilih Diameter Kawat Las yang Tepat</h2>
<p>Untuk daya listrik rendah 450W hingga 900W, hindari menggunakan kawat las diameter 2.6mm atau 3.2mm. Gunakan kawat las tipe <strong>E6013 diameter 1.6 mm atau 2.0 mm</strong> (seperti Nikko Steel RD-260). Kawat berdiameter kecil membutuhkan arus yang jauh lebih rendah (40 - 65 Ampere) untuk meleleh sempurna.</p>
<h2>2. Pemanfaatan Fitur Canggih Modul IGBT</h2>
<p>Mesin las modern <strong>Arcweld Pro MMA-160</strong> telah menyematkan teknologi cerdas yang sangat membantu pemula:</p>
<ul>
    <li><strong>Hot Start Otomatis:</strong> Memberikan lonjakan arus sesaat pada detik pertama kawat disentuhkan agar busur api menyala spontan tanpa mengetuk berkali-kali.</li>
    <li><strong>Anti-Stick Technology:</strong> Menurunkan arus listrik menjadi nol secara otomatis saat kawat menempel, sehingga kawat tidak merah membara dan mudah dicabut dengan tangan tanpa korsleting.</li>
    <li><strong>Arc Force:</strong> Menjaga stabilitas nyala api saat jarak ujung kawat ke cairan las sedikit berubah-ubah.</li>
</ul>
<h2>3. Teknik Goresan Seperti Menyalakan Korek Api</h2>
<p>Alih-alih mematuk kawat las secara vertikal ke permukaan besi, gunakan gerakan menggores melengkung tipis menyerupai cara menyalakan korek api kayu. Segera setelah percikan api muncul, angkat sedikit kawat setinggi 2-3 mm dan pertahankan jarak busur api tersebut secara stabil.</p>',
            ],
            [
                'slug' => 'panduan-memilih-mata-gerinda-potong-ampelas',
                'title' => 'Panduan Memilih Mata Gerinda 4 Inci: Perbedaan Cutting Disc Besi, Diamond Wheel, dan Flap Disc',
                'summary' => 'Kenali kode warna, ketebalan, dan material abrasive pada mata gerinda tangan agar proses potong besi beton, amplas karat, dan potong keramik berjalan cepat dan aman.',
                'category_slug' => 'mesin-pemotong-gerinda',
                'badge' => 'PANDUAN AKSESORI',
                'badge_color' => '#3b82f6',
                'bg_glow' => '#2563eb',
                'title_l1' => 'PANDUAN MATA GERINDA 4 INCI',
                'title_l2' => 'BEDAKAN CUTTING DISC, DIAMOND & FLAP',
                'sub_banner' => 'Spesifikasi Pemotongan Besi Beton, Keramik Keras & Finishing Las',
                'product_image' => 'products/gerinda-tangan-cordless-4-inch.jpg',
                'product_slugs' => ['mesin-gerinda-tangan-cordless-4-inch-brushless-powertek', 'besi-beton-ulir-13-mm'],
                'tag_slugs' => ['power-tools', 'tips-konstruksi'],
                'views_count' => 1080,
                'is_featured' => false,
                'days_ago' => 5,
                'content' => '<p>Mesin gerinda tangan 4 inci adalah salah satu perkakas paling fleksibel di bengkel dan proyek bangunan. Namun, memasang jenis mata gerinda yang salah tidak hanya menurunkan efisiensi kerja, tetapi juga berisiko tinggi menyebabkan batu gerinda pecah berkeping-keping saat berputar pada 8.500 RPM.</p>
<h2>1. Cutting Disc Logam & Stainless Steel (Tebal 1.0 - 1.2 mm)</h2>
<p>Untuk memotong pipa besi, holo galvanis, dan besi beton ulir, gunakan mata potong tipis (ultra-thin cutting wheel) berketebalan 1.0mm hingga 1.2mm dengan anyaman jaring fiberglass ganda. Jangan pernah menggunakan batu potong tipis ini untuk mengikis atau mengampelas lasan, karena gaya gesek samping akan merobek serat penguatnya.</p>
<h2>2. Grinding Disc Tebal (Tebal 6.0 mm)</h2>
<p>Batu gerinda asah tebal 6mm dirancang khusus untuk meratakan tonjolan sisa las (welding seam), menghaluskan chamfer sudut besi tajam, dan membersihkan karat tebal. Permukaannya tahan terhadap tekanan samping yang tinggi.</p>
<h2>3. Diamond Wheel (Pisau Intan untuk Keramik & Granit)</h2>
<p>Memotong keramik dinding dan granit tile membutuhkan mata pisau intan berkonsentrasi tinggi. Tipe continuous rim (lingkar mulus) menghasilkan potongan keramik paling halus tanpa gompal (chipping), sedangkan tipe segmented (bercelah) sangat cocok untuk memotong batu alam dan bata merah dalam keadaan kering.</p>
<h2>4. Flap Disc Kertas Ampelas Susun</h2>
<p>Flap disc terdiri dari lembaran amplas abrasif yang disusun bertingkat. Sangat ideal untuk proses penghalusan kayu, finishing bodi logam sebelum pengecatan dasar, dan menghilangkan kerak sisa las tanpa mengikis material dasar secara berlebihan.</p>',
            ],
            [
                'slug' => 'keunggulan-gagang-fiberglass-sekop-palu-godam',
                'title' => 'Mengapa Gagang Fiberglass Menggeser Gagang Kayu pada Sekop dan Palu Godam Modern?',
                'summary' => 'Investigasi ketahanan mekanis material: mengapa alat proyek manual bergagang fiberglass tahan terhadap cuaca ekstrem, anti-lapuk, dan meredam getaran hingga 70%.',
                'category_slug' => 'alat-proyek-perkakas-manual',
                'badge' => 'MATERIAL INSIGHT',
                'badge_color' => '#d97706',
                'bg_glow' => '#b45309',
                'title_l1' => 'FIBERGLASS VS GAGANG KAYU',
                'title_l2' => 'KETAHANAN ALAT PROYEK KELAS BERAT',
                'sub_banner' => 'Anti Patah, Meredam Getaran Hantaman & Tahan Panas Hujan',
                'product_image' => 'products/palu-godam-sledge-hammer-10-lb.jpg',
                'product_slugs' => ['sekop-pasir-baja-tempa-ultra-steel-gagang-fiber', 'palu-godam-sledge-hammer-10-lb-toughbuilt'],
                'tag_slugs' => ['alat-manual', 'tips-konstruksi'],
                'views_count' => 890,
                'is_featured' => false,
                'days_ago' => 5,
                'content' => '<p>Selama beberapa dekade, gagang kayu jati atau kayu mahoni menjadi standar alat perkakas manual seperti sekop pasir dan palu godam. Namun di proyek konstruksi skala besar saat ini, kontraktor beralih secara masif ke gagang berbahan fiberglass padat dengan selubung karet polimer.</p>
<h2>1. Ketahanan Terhadap Cuaca dan Kelembapan Ekstrem</h2>
<p>Gagang kayu rentan memuai saat terkena air hujan dan menyusut rapuh saat terpanggang terik matahari. Penyusutan ini membuat ikatan antara kepala palu dan gagang longgar, berisiko meluncur lepas saat diayunkan. Sebaliknya, fiberglass adalah material komposit anorganik yang sama sekali tidak terpengaruh oleh air, semen asam, maupun panas matahari.</p>
<h2>2. Mengeliminasi Patah Akibat Hantaman Meleset (Overstrike)</h2>
<p>Saat menggunakan palu godam 10 lb, benturan yang sedikit meleset dari sasaran (leher gagang menghantam batu) akan mematahkan serat gagang kayu seketika. Gagang fiberglass modern dilengkapi inti serat searah berkepadatan tinggi yang diperkuat lapisan pelindung karet TPR tahan hantaman.</p>
<h2>3. Penyerapan Getaran (Vibration Damping)</h2>
<p>Hantaman kepala baja 10 lb pada beton keras memicu gelombang kejut berkecepatan tinggi yang merambat ke sendi siku dan bahu operator. Fiberglass memiliki koefisien redaman getaran 3 kali lebih efektif dibanding kayu keras, menjaga stamina dan kesehatan persendian tukang sepanjang hari kerja.</p>',
            ],
            [
                'slug' => 'efisiensi-gerobak-sorong-adukan-semen-proyek',
                'title' => 'Manajemen Angkut Adukan Semen: Mengapa Pemilihan Gerobak Sorong Mempengaruhi Biaya Proyek?',
                'summary' => 'Analisis alur kerja pengecoran: bagaimana gerobak sorong bak baja seamless 400 kg dengan ban angin presisi menekan waktu siklus pemindahan adukan beton hingga 25%.',
                'category_slug' => 'alat-proyek-perkakas-manual',
                'badge' => 'MANAJEMEN PROYEK',
                'badge_color' => '#64748b',
                'bg_glow' => '#475569',
                'title_l1' => 'LOGISTIK COR DI LAPANGAN',
                'title_l2' => 'EFISIENSI ANGKUT GEROBAK SORONG PRO',
                'sub_banner' => 'Bak Baja Seamless 400 Kg, Siklus Cepat & Pilihan Ban Lapangan',
                'product_image' => 'products/gerobak-sorong-pasir-heavy-duty.jpg',
                'product_slugs' => ['gerobak-sorong-pasir-heavy-duty-pro-wheelbarrow', 'semen-tiga-roda-50-kg', 'mortar-instan-perekat-bata-ringan-40-kg'],
                'tag_slugs' => ['alat-manual', 'tips-konstruksi', 'semen'],
                'views_count' => 950,
                'is_featured' => false,
                'days_ago' => 6,
                'content' => '<p>Pada pekerjaan pengecoran dak lantai atau jalan lingkungan, mixer molen beton biasanya berada di titik tetap dekat tumpukan material pasir dan semen. Kecepatan dan kelancaran aliran adukan cor dari mulut molen ke titik pengecoran sangat ditentukan oleh armada gerobak sorong yang digunakan.</p>
<h2>1. Bak Plat Seamless Deep-Drawn: Mencegah Kebocoran Air Semen</h2>
<p>Gerobak sorong murah yang dibuat dari plat potongan las-lasan sering mengalami kebocoran pasta semen cair melalui celah las saat diguncang di perjalanan. Kehilangan air semen (pasta semen) ini menurunkan rasio air-semen (FAS) dan mengurangi kekuatan akhir beton.</p>
<p>Gerobak sorong standar proyek seperti <strong>Pro-Wheelbarrow</strong> dicetak utuh menggunakan mesin press hidrolik dari lembaran baja tebal 1.2 mm tanpa jahitan sambungan, menjaga mutu adukan semen tetap homogen sempurna hingga ditumpahkan.</p>
<h2>2. Ban Angin vs Ban Pejal: Sesuaikan dengan Karakter Medan</h2>
<ul>
    <li><strong>Roda Ban Angin (Pneumatic Tire):</strong> Pilihan terbaik untuk medan tanah gembur, pasir bergelombang, dan lantai kerja berlapis papan kayu. Bantalan udara meredam guncangan sehingga adukan semen tidak mudah tumpah.</li>
    <li><strong>Roda Ban Mati / Pejal (Solid PU Tire):</strong> Pilihan ideal untuk lokasi proyek pembongkaran yang dipenuhi serpihan paku, kawat bendrat, dan pecahan kaca tajam yang dapat membuat ban angin kempes mendadak.</li>
</ul>
<h2>3. Penyangga Depan (Front Tray Support) untuk Penuangan Cepat</h2>
<p>Fitur penyangga hidung depan pada rangka gerobak memungkinkan pekerja menumpahkan seluruh muatan adukan semen hanya dengan satu hentakan dorong vertikal, tanpa risiko gerobak tergelincir ke samping atau menabrak bekisting cor.</p>',
            ],
        ];

        // 4. Generate SVG Banners & Create Articles
        foreach ($articles as $art) {
            $bannerJpgName = 'banner-'.$art['slug'].'.jpg';
            $bannerSvgName = 'banner-'.$art['slug'].'.svg';

            $bannerImage = file_exists($articlesDir.'/'.$bannerJpgName)
                ? 'articles/'.$bannerJpgName
                : 'articles/'.$bannerSvgName;

            $article = Article::updateOrCreate(['slug' => $art['slug']], [
                'title' => $art['title'],
                'summary' => $art['summary'],
                'content' => $art['content'],
                'image' => $bannerImage,
                'image_caption' => $art['title'].' - Panduan Lengkap Supermaterial.',
                'image_credit' => 'Foto: Dok. Editorial Supermaterial Pro',
                'author_id' => $author->id,
                'editor_name' => 'Gema Suaria',
                'author_initials' => 'gar',
                'editor_initials' => 'ed',
                'dateline' => 'Medan, Supermaterial Review --',
                'views_count' => $art['views_count'],
                'is_featured' => $art['is_featured'],
                'published_at' => now()->subDays($art['days_ago']),
            ]);

            // Sync Kategori
            if (isset($categories[$art['category_slug']])) {
                $article->categories()->syncWithoutDetaching([$categories[$art['category_slug']]->id]);
            }

            // Sync Tags
            $tagIds = [];
            foreach ($art['tag_slugs'] as $tSlug) {
                if (isset($tags[$tSlug])) {
                    $tagIds[] = $tags[$tSlug]->id;
                }
            }
            if (! empty($tagIds)) {
                $article->tags()->syncWithoutDetaching($tagIds);
            }

            // Sync Relasi Produk Terkait
            $prodIds = Product::whereIn('slug', $art['product_slugs'])->pluck('id');
            if ($prodIds->isNotEmpty()) {
                $article->products()->syncWithoutDetaching($prodIds);
            }
        }
    }

    private function createArticleSvgBanner(
        string $filePath,
        string $badge,
        string $titleLine1,
        string $titleLine2,
        string $subtitle,
        string $productImgPath,
        string $badgeColor,
        string $bgGlow
    ): void {
        $safeBadge = htmlspecialchars($badge, ENT_XML1, 'UTF-8');
        $safeT1 = htmlspecialchars($titleLine1, ENT_XML1, 'UTF-8');
        $safeT2 = htmlspecialchars($titleLine2, ENT_XML1, 'UTF-8');
        $safeSub = htmlspecialchars($subtitle, ENT_XML1, 'UTF-8');

        $productImageElement = '';
        if (file_exists($productImgPath)) {
            $base64 = base64_encode(file_get_contents($productImgPath));
            $productImageElement = '<image href="data:image/jpeg;base64,'.$base64.'" x="680" y="85" width="480" height="480" preserveAspectRatio="xMidYMid meet" filter="url(#prodShadow)" />';
        }

        $badgeLen = strlen($badge);
        $badgeWidth = max(180, $badgeLen * 11 + 36);

        $svg = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL.
'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 675" width="1200" height="675">'.
    '<defs>'.
        '<linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">'.
            '<stop offset="0%" stop-color="#0a0f1d"/>'.
            '<stop offset="50%" stop-color="#0f172a"/>'.
            '<stop offset="100%" stop-color="#020617"/>'.
        '</linearGradient>'.
        '<radialGradient id="haloGlow" cx="50%" cy="50%" r="50%">'.
            '<stop offset="0%" stop-color="'.$bgGlow.'" stop-opacity="0.32"/>'.
            '<stop offset="60%" stop-color="'.$bgGlow.'" stop-opacity="0.08"/>'.
            '<stop offset="100%" stop-color="#000000" stop-opacity="0"/>'.
        '</radialGradient>'.
        '<filter id="prodShadow" x="-20%" y="-20%" width="140%" height="140%">'.
            '<feDropShadow dx="0" dy="24" stdDeviation="30" flood-color="#000000" flood-opacity="0.6"/>'.
        '</filter>'.
        '<pattern id="techGrid" width="40" height="40" patternUnits="userSpaceOnUse">'.
            '<path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.025)" stroke-width="1"/>'.
        '</pattern>'.
    '</defs>'.

    '<!-- Background -->'.
    '<rect width="1200" height="675" fill="url(#bgGrad)"/>'.
    '<rect width="1200" height="675" fill="url(#techGrid)"/>'.

    '<!-- Glowing Orb behind Product -->'.
    '<circle cx="920" cy="325" r="280" fill="url(#haloGlow)"/>'.
    '<circle cx="920" cy="325" r="230" fill="none" stroke="'.$badgeColor.'" stroke-opacity="0.2" stroke-width="2" stroke-dasharray="6 6"/>'.

    '<!-- Left Column Content -->'.
    '<!-- Badge -->'.
    '<g transform="translate(80, 100)">'.
        '<rect width="'.$badgeWidth.'" height="36" rx="18" fill="'.$badgeColor.'" fill-opacity="0.15" stroke="'.$badgeColor.'" stroke-width="1.5"/>'.
        '<circle cx="18" cy="18" r="4" fill="'.$badgeColor.'"/>'.
        '<text x="32" y="23" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="12" font-weight="800" fill="'.$badgeColor.'" letter-spacing="1.2">'.$safeBadge.'</text>'.
    '</g>'.

    '<!-- Headline Line 1 -->'.
    '<text x="80" y="215" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="36" font-weight="900" fill="#ffffff" letter-spacing="-0.5">'.$safeT1.'</text>'.

    '<!-- Headline Line 2 -->'.
    '<text x="80" y="268" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="34" font-weight="900" fill="'.$badgeColor.'" letter-spacing="-0.5">'.$safeT2.'</text>'.

    '<!-- Accent Line -->'.
    '<line x1="80" y1="310" x2="240" y2="310" stroke="'.$badgeColor.'" stroke-width="3" stroke-linecap="round"/>'.

    '<!-- Subtitle / Key Takeaway -->'.
    '<text x="80" y="360" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="18" font-weight="500" fill="#94a3b8">'.$safeSub.'</text>'.

    '<!-- Checklist Highlights -->'.
    '<g transform="translate(80, 420)">'.
        '<circle cx="10" cy="10" r="10" fill="'.$badgeColor.'" fill-opacity="0.2"/>'.
        '<path d="M 6 10 L 9 13 L 14 7" fill="none" stroke="'.$badgeColor.'" stroke-width="2" stroke-linecap="round"/>'.
        '<text x="32" y="15" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="15" font-weight="600" fill="#cbd5e1">Rekomendasi Ahli Konstruksi & Praktisi Lapangan</text>'.
    '</g>'.

    '<g transform="translate(80, 465)">'.
        '<circle cx="10" cy="10" r="10" fill="'.$badgeColor.'" fill-opacity="0.2"/>'.
        '<path d="M 6 10 L 9 13 L 14 7" fill="none" stroke="'.$badgeColor.'" stroke-width="2" stroke-linecap="round"/>'.
        '<text x="32" y="15" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="15" font-weight="600" fill="#cbd5e1">Panduan Penggunaan Aman & Verifikasi Kualitas</text>'.
    '</g>'.

    '<!-- Footer Watermark Bar -->'.
    '<rect x="80" y="555" width="560" height="1" fill="rgba(255,255,255,0.1)"/>'.
    '<text x="80" y="585" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="12" font-weight="700" fill="#64748b" letter-spacing="1.5">SUPERMATERIAL TECH INSIGHTS · EDISI 2026</text>'.

    '<!-- Right Product Element -->'.
    $productImageElement.

    '<!-- Verified Badge overlay -->'.
    '<g transform="translate(730, 520)">'.
        '<rect width="210" height="38" rx="19" fill="#0f172a" fill-opacity="0.88" stroke="rgba(255,255,255,0.18)" stroke-width="1"/>'.
        '<text x="25" y="24" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="12" font-weight="700" fill="#f8fafc">⭐ Review & Uji Lapangan</text>'.
    '</g>'.
'</svg>';

        file_put_contents($filePath, $svg);
    }
}
