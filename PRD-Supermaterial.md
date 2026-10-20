# PRD Supermaterial

| Informasi | Detail |
|---|---|
| Penyusun | Garies |
| Versi | 0.3 (Draft Revisi) |
| Tanggal | 20 Oktober 2026 |
| Domain target | supermaterial.jayaintero.com |
| Repository | github.com/garies77/supermaterial |
| Status proyek | Pengembangan baru dari nol |
| Framework backend | Laravel 12 |
| Panel admin | Filament 5 |
| Frontend publik | Blade + Tailwind CSS |

## Daftar Isi

1. [Ringkasan](#1-ringkasan)
2. [Kondisi proyek dan keputusan teknis](#2-kondisi-proyek-dan-keputusan-teknis)
3. [Latar belakang dan masalah](#3-latar-belakang-dan-masalah)
4. [Tujuan produk](#4-tujuan-produk)
5. [Bukan tujuan MVP](#5-bukan-tujuan-mvp)
6. [Pengguna](#6-pengguna)
7. [Ruang lingkup pengembangan](#7-ruang-lingkup-pengembangan)
8. [Kebutuhan fungsional](#8-kebutuhan-fungsional)
9. [Kebutuhan SEO](#9-kebutuhan-seo)
10. [Model data](#10-model-data)
11. [Arsitektur aplikasi](#11-arsitektur-aplikasi)
12. [Kebutuhan non-fungsional](#12-kebutuhan-non-fungsional)
13. [Metrik keberhasilan](#13-metrik-keberhasilan)
14. [Rencana implementasi dan rilis](#14-rencana-implementasi-dan-rilis)
15. [Risiko dan mitigasi](#15-risiko-dan-mitigasi)
16. [Pertanyaan terbuka](#16-pertanyaan-terbuka)

---

## 1. Ringkasan

Supermaterial adalah website katalog bahan bangunan dan platform konten berbasis SEO yang bertujuan mendapatkan trafik organik dari mesin pencari serta mengarahkan pengunjung ke kanal pembelian.

Pengunjung dapat menemukan produk melalui Google, halaman kategori, pencarian internal, atau artikel edukasi. Pembelian dilakukan melalui Shopee, Tokopedia, Lazada, TikTok Shop, atau WhatsApp.

Pada fase awal, website tidak menyediakan keranjang belanja, checkout, maupun pembayaran langsung. Seluruh transaksi berlangsung melalui kanal eksternal.

Supermaterial dikembangkan sebagai website baru dari nol, menggunakan repository dan fondasi aplikasi yang dirancang khusus untuk kebutuhan produk ini. Tidak ada proses migrasi atau upgrade dari proyek sebelumnya.

Keberhasilan MVP diukur berdasarkan pertumbuhan trafik organik, kualitas halaman yang terindeks, serta jumlah klik ke kanal pembelian.

## 2. Kondisi proyek dan keputusan teknis

### 2.1 Kondisi proyek

- Pengembangan dimulai dari nol.
- Repository GitHub digunakan sebagai sumber kode utama.
- Database dirancang berdasarkan kebutuhan Supermaterial.
- Panel admin dibangun menggunakan Filament 5.
- Frontend publik menggunakan server-side rendering.
- Tidak ada kewajiban mempertahankan struktur, kode, atau database proyek lama.

### 2.2 Prinsip pengembangan

1. Membangun fondasi aplikasi yang bersih dan mudah dikembangkan.
2. Mengutamakan fitur yang mendukung peluncuran MVP.
3. Menghindari dependensi yang tidak diperlukan.
4. Memilih package yang kompatibel dengan versi framework yang digunakan.
5. Menyediakan pengujian otomatis untuk fitur penting sejak awal.
6. Menggunakan Git untuk pengelolaan versi dan riwayat perubahan.

### 2.3 Keputusan teknologi

| Komponen | Keputusan |
|---|---|
| Backend | Laravel 12 |
| Bahasa backend | PHP 8.2 atau lebih baru, mengikuti persyaratan dependensi |
| Admin panel | Filament 5 |
| Interaktivitas admin | Livewire 4, sesuai kompatibilitas resmi |
| Frontend publik | Blade, Tailwind CSS, dan Vite |
| Interaktivitas ringan | Alpine.js atau JavaScript biasa |
| Database produksi | MySQL atau MariaDB |
| Database lokal | SQLite atau MySQL |
| Cache dan antrean | Database queue untuk awal; Redis jika diperlukan |
| Penyimpanan media | Local disk atau object storage |
| Version control | Git dan GitHub |

> Versi akhir setiap dependensi harus dikonfirmasi melalui dokumentasi resmi dan kompatibilitas Composer sebelum implementasi. Jika ada package yang belum kompatibel dengan Filament 5, gunakan alternatif yang didukung atau bangun komponen sendiri.

## 3. Latar belakang dan masalah

- Pembeli bahan bangunan sering mencari produk berdasarkan jenis, merek, ukuran, ketebalan, spesifikasi, dan harga.
- Halaman marketplace memiliki keterbatasan dalam pengelolaan konten SEO dan artikel edukasi.
- Website sendiri memungkinkan pengelolaan katalog, konten, struktur tautan internal, serta data klik.
- Pembeli proyek sering membutuhkan konsultasi dan penawaran melalui WhatsApp.
- Data klik perlu dikumpulkan untuk mengetahui produk, kategori, dan artikel yang mendorong minat pembelian.

## 4. Tujuan produk

1. Mendapatkan trafik organik melalui halaman produk, kategori, dan artikel.
2. Mempermudah pengunjung menemukan produk sesuai kebutuhan.
3. Mengarahkan pengunjung ke kanal pembelian yang relevan.
4. Mengelola ratusan hingga ribuan produk dan artikel melalui satu panel admin.
5. Mengukur klik ke setiap kanal pembelian dan mengidentifikasi halaman yang menghasilkan klik.
6. Menyiapkan struktur data yang memungkinkan penambahan fitur transaksi langsung pada fase berikutnya.
7. Membangun fondasi teknis yang mudah dipelihara tanpa ketergantungan pada proyek lama.

## 5. Bukan tujuan MVP

Fitur berikut tidak dikerjakan pada fase pertama:

- Keranjang belanja dan checkout internal.
- Payment gateway.
- Akun pelanggan dan riwayat pesanan.
- Sinkronisasi stok real-time dengan marketplace.
- Sinkronisasi harga otomatis.
- Perhitungan ongkir.
- Pelacakan pembelian aktual setelah pengunjung meninggalkan website.
- Aplikasi mobile native.
- Integrasi ERP atau sistem akuntansi.

Fitur tersebut hanya dipertimbangkan setelah MVP menghasilkan data yang cukup untuk mendukung keputusan pengembangan.

## 6. Pengguna

| Pengguna | Deskripsi | Perilaku yang diharapkan |
|---|---|---|
| Pencari informasi | Pengunjung yang mencari panduan, perbandingan, atau penjelasan bahan bangunan | Melanjutkan kunjungan ke kategori atau halaman produk |
| Pembeli siap beli | Pengunjung yang sudah mengetahui produk atau spesifikasi yang dibutuhkan | Mengklik kanal marketplace atau WhatsApp |
| Pembeli proyek | Kontraktor atau pemilik bangunan yang membutuhkan bahan dalam jumlah besar | Menghubungi WhatsApp dengan konteks produk yang jelas |
| Admin konten | Pengguna yang mengelola katalog, kategori, artikel, kanal penjualan, SEO, dan pengaturan website | - |

## 7. Ruang lingkup pengembangan

### Fase 1: MVP (prioritas utama)

Fondasi Laravel, Filament 5, autentikasi dan peran, katalog produk, kategori, artikel, kanal pembelian, pencatatan klik, SEO teknis dasar, serta halaman statis.

### Fase 2: Optimasi SEO dan operasional

Hub topik, tautan internal otomatis, produk dan artikel terkait, perbandingan produk, FAQ, impor massal, serta laporan performa lanjutan.

### Fase 3: Transaksi internal (opsional)

Keranjang, checkout, payment gateway, akun pelanggan, dan manajemen pesanan. Implementasi bergantung pada hasil evaluasi fase sebelumnya.

## 8. Kebutuhan fungsional

### 8.1 Katalog produk

#### Data produk

Setiap produk memiliki:

- Nama dan slug unik.
- Kategori utama.
- Merek.
- Ringkasan dan deskripsi.
- Spesifikasi terstruktur.
- Galeri gambar beserta teks alternatif.
- Harga atau rentang harga, bersifat opsional.
- Satuan penjualan.
- Varian produk opsional.
- Status draft atau terbit.
- Metadata SEO.
- Tautan kanal pembelian.
- FAQ, produk terkait, dan artikel terkait.

Varian dapat digunakan untuk ukuran, ketebalan, warna, atau spesifikasi lain yang relevan.

Harga dan SKU pada varian disimpan sebagai data katalog. Penyimpanan ini tidak berarti website sudah menyediakan sistem transaksi atau stok.

Kolom SEO meliputi judul meta, deskripsi meta, gambar Open Graph, URL kanonik, dan opsi noindex.

#### Pengalaman menjelajah katalog

Halaman katalog harus menyediakan pengalaman menjelajah produk yang lengkap, memudahkan pengunjung menemukan bahan bangunan berdasarkan kategori, merek, harga, dan spesifikasi.

Kebutuhan fungsional:

- Menampilkan daftar produk dalam bentuk grid yang responsif.
- Menyediakan sidebar katalog di desktop.
- Menampilkan jumlah produk yang sesuai dengan filter.
- Menampilkan indikator filter yang sedang aktif.
- Menyediakan paginasi untuk daftar produk.
- Menampilkan kondisi kosong jika tidak ada produk yang cocok, beserta opsi menghapus filter.
- Mempertahankan parameter filter ketika pengunjung berpindah halaman melalui paginasi.
- Filter harus bekerja berdasarkan data produk yang tersimpan di database, bukan hanya menyaring produk yang sudah dimuat di browser.

Sidebar katalog memuat:

| Elemen | Fungsi |
|---|---|
| Kategori | Kategori induk dan subkategori dalam struktur bertingkat |
| Filter merek | Memilih satu atau beberapa merek |
| Filter harga | Rentang harga minimum dan maksimum |
| Pengurutan | Relevansi, terbaru, harga terendah, dan harga tertinggi |
| Reset filter | Menghapus seluruh filter yang aktif |

**Tampilan desktop**

```text
+----------------------+-----------------------------------+
| Katalog Produk                                           |
+----------------------+-----------------------------------+
| Kategori             |  [Produk A]        [Produk B]     |
|  - Bahan Atap        |  Merek · Kategori  Merek · Kategori|
|  - Rangka Baja Ringan|  Harga produk      Harga produk   |
|  - Plafon            |                                   |
|  - Lantai Vinyl      |                                   |
|                      |                                   |
| Merek                |                                   |
|  [ ] Merek A         |                                   |
|  [ ] Merek B         |                                   |
|                      |                                   |
| Rentang Harga        |                                   |
|  Minimum | Maksimum  |                                   |
|                      |                                   |
| [Reset filter]       |                                   |
+----------------------+-----------------------------------+
```

*Ilustrasi struktur layout, bukan desain final.*

Sidebar berada di sisi kiri, sedangkan daftar produk berada di sisi kanan. Sidebar tetap mudah diakses saat pengunjung menjelajahi katalog.

**Tampilan mobile**

- Sidebar diubah menjadi panel filter yang dapat dibuka melalui tombol Filter.
- Panel filter dapat ditampilkan sebagai drawer atau bottom sheet.
- Tombol Terapkan Filter dan Reset Filter harus mudah ditemukan.
- Filter aktif ditampilkan sebagai chip atau indikator yang ringkas.
- Daftar produk menggunakan grid responsif sesuai lebar layar.
- Filter tidak boleh menutupi navigasi atau mengganggu penggunaan tombol pencarian.

### 8.2 Kategori

- Mendukung kategori induk dan anak hingga maksimal tiga level.
- Memiliki slug unik.
- Memiliki teks pengantar di atas daftar produk.
- Memiliki konten pendukung di bawah daftar produk.
- Menampilkan daftar produk dengan paginasi.
- Mendukung pengurutan dan filter dasar berdasarkan merek serta rentang harga.
- Memiliki metadata SEO tersendiri.
- Mendukung status aktif atau tidak aktif.

Parameter filter tidak boleh menghasilkan halaman duplikat yang diindeks secara tidak sengaja. Strategi canonical dan noindex ditetapkan berdasarkan jenis halaman serta manfaat SEO-nya.

### 8.3 Artikel

Setiap artikel memiliki:

- Judul dan slug.
- Ringkasan.
- Isi melalui editor teks kaya.
- Gambar utama.
- Penulis.
- Kategori artikel dan tag.
- Tanggal publikasi dan pembaruan.
- Metadata SEO.
- Status draft, terjadwal, atau terbit.
- Tautan eksplisit ke produk dan kategori.
- Daftar isi otomatis berdasarkan heading.
- Estimasi waktu baca.

Artikel dapat dijadwalkan untuk terbit pada waktu tertentu. Halaman produk menampilkan artikel yang ditautkan secara eksplisit ke produk tersebut.

Tanggal pembaruan hanya berubah saat konten benar-benar mengalami perubahan bermakna.

### 8.4 Kanal pembelian

Kanal awal:

- Shopee
- Tokopedia
- Lazada
- TikTok Shop
- WhatsApp

Daftar kanal dapat dikelola melalui panel admin tanpa perlu mengubah kode aplikasi untuk setiap penambahan kanal baru.

Setiap produk dapat mempunyai satu atau beberapa tautan untuk masing-masing kanal. Kanal tanpa tautan aktif tidak ditampilkan pada halaman produk.

Ketentuan implementasi:

- Tautan marketplace diarahkan melalui rute internal `/go/{produk}/{kanal}`.
- Tautan keluar menggunakan `rel="nofollow sponsored"` jika relevan, terutama untuk tautan afiliasi atau tautan bersponsor.
- Tautan eksternal dibuka di tab baru.
- WhatsApp menggunakan nomor default global yang dapat ditimpa sesuai konfigurasi produk atau kategori.
- Pesan WhatsApp otomatis berisi nama produk dan URL halaman produk.
- Tombol pembelian dibuat mudah ditemukan pada perangkat desktop dan mobile.
- Pada mobile, tombol beli dapat menggunakan sticky bar dengan tetap memperhatikan ruang layar dan aksesibilitas.

### 8.5 Pencarian dan navigasi

Pencarian merupakan elemen navigasi utama dan harus mudah ditemukan pada seluruh ukuran layar.

Navigasi:

- Menu utama berbasis kategori.
- Halaman statis: beranda, tentang, kontak, kebijakan privasi, dan syarat penggunaan.
- Breadcrumb pada halaman produk, kategori, dan artikel.
- Navigasi kategori yang mendukung struktur hierarki.

Pencarian:

1. Ikon pencarian tetap terlihat pada header meskipun website dibuka melalui perangkat mobile.
2. Ikon pencarian tidak boleh hilang hanya karena menu navigasi desktop berubah menjadi menu hamburger.
3. Ketika ikon diklik, website menampilkan kolom pencarian yang mudah digunakan, baik melalui overlay, drawer, maupun kolom pencarian pada header.
4. Pencarian mendukung nama produk, merek, kategori, dan kata kunci relevan.
5. Hasil pencarian menampilkan produk dan artikel yang sesuai, dengan halaman hasil menggunakan parameter URL.
6. Pencarian mendukung kondisi tanpa hasil dan memberikan saran untuk mencoba kata kunci lain.
7. Pencarian tetap mudah diakses menggunakan keyboard dan teknologi bantu.
8. Halaman hasil pencarian internal tidak diindeks oleh mesin pencari.

Ketentuan tampilan mobile: ikon pencarian harus tetap terlihat pada header tanpa harus membuka menu hamburger. Ukuran area klik harus nyaman untuk layar sentuh, dan posisi ikon konsisten di seluruh halaman publik.

### 8.6 Pelacakan klik pembelian

Pelacakan terdiri atas dua lapisan.

#### Lapisan 1: Pencatatan server

Ketika pengunjung mengklik tombol beli:

1. Browser membuka rute `/go/{produk}/{kanal}`.
2. Laravel memvalidasi produk, kanal, dan tautan tujuan.
3. Sistem mencatat event klik ke tabel `click_events`.
4. Pengunjung diarahkan menggunakan HTTP 302 ke tujuan yang valid.

Data yang dicatat meliputi:

- Produk.
- Varian jika tersedia.
- Kanal pembelian.
- Halaman asal.
- Referrer jika tersedia.
- Parameter UTM yang diizinkan.
- User agent yang diringkas.
- Indikator bot.
- Waktu klik.

Alamat IP mentah tidak disimpan. Pembatasan laju dan deteksi bot diterapkan untuk mengurangi klik otomatis serta penyalahgunaan.

#### Lapisan 2: Pelacakan browser

Event dikirim ke Google Analytics 4 dengan parameter produk, kanal, dan halaman asal. Meta Pixel bersifat opsional jika diperlukan untuk kampanye iklan.

Implementasi event harus mencegah duplikasi penghitungan yang tidak disengaja. Jika pengunjung menggunakan pemblokir pelacakan browser, pencatatan server tetap menjadi sumber data klik utama.

#### Batasan pengukuran

Sistem hanya mengukur klik keluar. Sistem tidak dapat memastikan apakah pengunjung benar-benar mengirim pesan WhatsApp atau menyelesaikan pembelian di marketplace tanpa integrasi atau data konversi tambahan.

#### Laporan admin

Dasbor menampilkan:

- Total klik per kanal.
- Produk dengan klik terbanyak.
- Kategori yang menghasilkan klik.
- Artikel yang mendorong klik.
- Tren klik harian.
- Filter tanggal, kanal, dan kategori.
- Ekspor CSV.

Rute `/go/` tidak dimasukkan ke sitemap dan diblokir dari perayapan melalui konfigurasi `robots.txt`. Namun, `robots.txt` bukan mekanisme keamanan atau jaminan bahwa URL tidak akan diindeks. Gunakan pula kontrol pengindeksan yang sesuai.

### 8.7 Panel admin Filament 5

Panel admin mencakup:

- Produk.
- Kategori.
- Merek.
- Varian produk.
- Galeri gambar.
- Artikel.
- Tag.
- Kanal pembelian.
- Halaman statis.
- FAQ.
- Pengguna dan peran.
- Pengaturan website.
- Redirect 301.
- Laporan klik.
- Impor dan ekspor CSV atau Excel.

Peran pengguna:

| Peran | Hak akses |
|---|---|
| Admin | Akses seluruh fitur dan pengaturan |
| Editor | Mengelola serta meninjau konten sesuai izin |
| Penulis | Membuat dan mengedit konten sesuai izin |

Hak akses harus ditegakkan pada backend, bukan hanya dengan menyembunyikan menu di panel admin.

Fitur tambahan:

- Pratinjau metadata Google.
- Indikator kelengkapan SEO.
- Pengelolaan redirect.
- Validasi URL kanal pembelian.
- Validasi slug unik.
- Impor produk beserta tautan kanal.
- Ringkasan performa klik.

## 9. Kebutuhan SEO

SEO menjadi bagian inti arsitektur sejak awal pengembangan.

### 9.1 Struktur URL

| Jenis | Pola |
|---|---|
| Kategori | `/kategori/{slug}` |
| Produk | `/produk/{slug}` |
| Artikel | `/artikel/{slug}` |

Slug dibuat otomatis berdasarkan judul, tetapi dapat diedit melalui panel admin.

Jika slug yang sudah dipublikasikan berubah, sistem membuat redirect 301 dari URL lama ke URL baru. Redirect berantai dan loop harus dicegah.

### 9.2 Metadata

Setiap halaman yang relevan memiliki:

- Title.
- Meta description.
- Canonical URL.
- Open Graph.
- Twitter Card.
- Pengaturan index atau noindex.

Metadata otomatis dibuat dari template jika kolom manual kosong. Halaman yang tidak memenuhi syarat untuk diindeks harus mendapatkan kebijakan yang sesuai.

### 9.3 Structured data

Jenis JSON-LD yang disediakan:

- Product dan Offer jika informasi harga memenuhi persyaratan.
- BreadcrumbList.
- Article.
- Organization.
- WebSite.
- FAQPage hanya jika memenuhi kebijakan dan persyaratan Google yang berlaku.

Data terstruktur harus sesuai dengan konten yang terlihat oleh pengunjung. Penambahan JSON-LD tidak menjamin rich results atau peringkat tertentu.

### 9.4 Sitemap dan robots.txt

- Sitemap XML dibuat otomatis.
- Sitemap dapat dipisahkan menurut jenis konten.
- Hanya URL kanonis yang layak diindeks dimasukkan.
- Sitemap diperbarui ketika konten berubah.
- `robots.txt` mengatur akses perayapan untuk area yang relevan.
- Sitemap didaftarkan ke Google Search Console.

### 9.5 Tautan internal

Fase 1 mencakup:

- Breadcrumb.
- Produk terkait.
- Artikel terkait.
- Tautan artikel ke produk dan kategori.

Fase 2 menambahkan rekomendasi tautan internal di editor artikel.

### 9.6 Filter dan paginasi

- Hasil filter tidak boleh menghasilkan ribuan URL indeks yang tidak berguna.
- Canonical dan noindex ditentukan berdasarkan pola URL serta nilai kontennya.
- Paginasi harus mempunyai URL yang dapat dirayapi.
- Tautan paginasi harus dapat diakses tanpa bergantung pada interaksi JavaScript.

### 9.7 Performa

- Target LCP di bawah 2,5 detik pada persentil ke-75 data lapangan.
- Gambar dikonversi ke WebP atau AVIF jika sesuai.
- Gambar utama tidak menggunakan lazy loading secara default.
- Gambar lain menggunakan lazy loading jika relevan.
- Atribut dimensi gambar ditentukan untuk mengurangi layout shift.
- Cache halaman publik diterapkan sesuai kebutuhan.
- CDN digunakan bila tersedia dan sesuai dengan hosting.

### 9.8 Kualitas konten

- Deskripsi produk tidak boleh sekadar menyalin marketplace atau pemasok.
- Spesifikasi harus terstruktur dan akurat.
- Template konten harus berbeda sesuai jenis halaman.
- Heading digunakan secara konsisten.
- Tanggal pembaruan tidak dimanipulasi untuk memberi kesan konten baru.
- Konten harus memberi informasi yang benar-benar berguna bagi calon pembeli.

### 9.9 Bahasa

Seluruh konten awal menggunakan bahasa Indonesia dengan atribut HTML `lang="id"`.

## 10. Model data

Struktur awal database terdiri dari tabel berikut.

| Tabel | Fungsi |
|---|---|
| `categories` | Kategori bertingkat, deskripsi, SEO, dan status |
| `brands` | Merek produk |
| `products` | Data utama produk dan metadata SEO |
| `product_variants` | Variasi ukuran, SKU, dan harga acuan |
| `product_images` | Galeri dan teks alternatif |
| `sales_channels` | Daftar kanal pembelian |
| `product_links` | Tautan produk ke kanal pembelian |
| `articles` | Konten artikel |
| `article_product` | Relasi artikel dan produk |
| `article_category` | Relasi artikel dan kategori |
| `tags` | Tag konten |
| `taggables` | Relasi polimorfik tag |
| `faqs` | FAQ produk, kategori, atau artikel |
| `click_events` | Riwayat klik pembelian |
| `redirects` | Pengalihan URL |
| `settings` | Konfigurasi website |
| `users` | Pengguna panel admin |

Relasi, indeks database, foreign key, aturan penghapusan, dan batasan keunikan ditetapkan ketika merancang migration.

Untuk fase berikutnya, tabel `orders`, `order_items`, dan `payments` dapat ditambahkan. Struktur MVP tidak perlu mengimplementasikan proses transaksi sebelum fase tersebut disetujui.

## 11. Arsitektur aplikasi

### 11.1 Backend

Laravel menjadi fondasi aplikasi, menangani:

- Routing.
- Model dan relasi database.
- Validasi.
- Autentikasi dan otorisasi.
- Pencatatan klik.
- Redirect.
- SEO metadata.
- Sitemap.
- Integrasi layanan eksternal.

### 11.2 Panel admin

Filament 5 digunakan untuk membangun panel pengelolaan konten dan operasional.

Livewire digunakan untuk interaktivitas panel admin sesuai arsitektur Filament. Package tambahan hanya digunakan jika kompatibel dengan versi yang dipilih.

### 11.3 Frontend publik

Blade, Tailwind CSS, dan Vite digunakan untuk menghasilkan halaman HTML di server.

Alpine.js atau JavaScript biasa dapat dipakai untuk interaksi ringan, seperti:

- Galeri gambar.
- Filter.
- Menu mobile.
- Sticky purchase bar.
- Daftar isi.
- Toggle FAQ.

Frontend publik tidak memerlukan SPA atau Livewire untuk kebutuhan MVP. Pendekatan ini mengurangi kompleksitas dan mendukung perenderan HTML yang mudah dirayapi mesin pencari.

### 11.4 Database dan penyimpanan

- MySQL atau MariaDB untuk produksi.
- Database lokal disesuaikan dengan lingkungan pengembangan.
- Penyimpanan gambar melalui Laravel filesystem.
- Queue digunakan untuk pekerjaan latar belakang seperti pemrosesan gambar dan pembuatan sitemap.
- Redis dapat ditambahkan jika kebutuhan cache dan antrean meningkat.

### 11.5 Infrastruktur produksi

- Hosting yang memenuhi persyaratan Laravel.
- HTTPS.
- CDN jika diperlukan.
- Pencadangan database dan media.
- Pengelolaan environment variable yang aman.
- Logging dan pemantauan error.
- Deployment dengan prosedur rollback.

## 12. Kebutuhan non-fungsional

| Aspek | Kebutuhan |
|---|---|
| Performa | Target LCP di bawah 2,5 detik pada persentil ke-75 data lapangan. Sasaran Core Web Vitals mencakup LCP, INP, dan CLS. |
| Keamanan | Autentikasi admin, pembatasan percobaan login, validasi input, sanitasi konten editor, pembatasan akses berbasis peran, dan validasi tujuan redirect untuk mencegah open redirect. |
| Skalabilitas | Arsitektur ditargetkan mampu menangani sekitar 10.000 produk dan 2.000 artikel dengan optimasi yang sesuai. Kinerja perlu divalidasi melalui pengujian. |
| Aksesibilitas | Kontras yang memadai, teks alternatif gambar, navigasi keyboard, dan tombol dengan label yang jelas. |
| Privasi | Minimisasi data klik, tidak menyimpan IP mentah, kebijakan privasi yang jelas, serta mekanisme persetujuan jika diperlukan untuk cookie atau pelacakan tertentu. |
| Pemeliharaan | Kode terstruktur, migration berversi, pengujian otomatis, dokumentasi instalasi, dan pencadangan. |

## 13. Metrik keberhasilan

Target numerik ditetapkan setelah website memperoleh data dasar.

Metrik yang dipantau:

- Sesi organik bulanan.
- Impresi dan klik Google Search Console.
- Jumlah halaman terindeks dibandingkan halaman yang layak diindeks.
- Jumlah kata kunci pada sepuluh hasil teratas.
- Klik kanal pembelian per sesi.
- Klik WhatsApp dibandingkan kunjungan halaman produk.
- Distribusi klik berdasarkan kanal.
- Performa halaman berdasarkan jenis konten.
- Core Web Vitals pada data lapangan.
- Error halaman, redirect, dan tautan pembelian yang rusak.

## 14. Rencana implementasi dan rilis

Urutan pengembangan disesuaikan dengan proyek baru yang dimulai dari nol.

### Tahap 1: Inisialisasi proyek

- Siapkan repository GitHub.
- Buat proyek Laravel baru.
- Konfigurasi environment lokal.
- Pasang Filament 5 dan dependensi yang kompatibel.
- Konfigurasi database, autentikasi, dan pengelolaan environment.
- Tetapkan struktur folder dan standar kode.

### Tahap 2: Fondasi aplikasi dan admin

- Rancang schema database dan migration.
- Implementasikan peran admin, editor, dan penulis.
- Buat pengaturan website.
- Siapkan layout publik dan panel admin.
- Tambahkan pengujian fondasi aplikasi.

### Tahap 3: Katalog dan kanal pembelian

- Implementasikan kategori, merek, produk, varian, dan galeri.
- Buat pengelolaan tautan kanal pembelian.
- Implementasikan halaman publik produk dan kategori.
- Buat rute redirect `/go/`.
- Implementasikan pencatatan klik server.

### Tahap 4: Sistem konten

- Implementasikan artikel, tag, dan FAQ.
- Buat relasi artikel dengan produk dan kategori.
- Buat halaman statis.
- Tambahkan pencarian internal dan navigasi.

### Tahap 5: SEO dan pengukuran

- Implementasikan metadata dan canonical.
- Tambahkan JSON-LD.
- Buat sitemap dan `robots.txt`.
- Implementasikan redirect 301.
- Integrasikan GA4.
- Optimalkan gambar, cache, dan performa.

### Tahap 6: Pengujian dan peluncuran

- Uji seluruh rute publik dan admin.
- Uji akses berbasis peran.
- Uji redirect dan pencatatan klik.
- Uji sitemap dan structured data.
- Impor produk awal.
- Hubungkan domain dan HTTPS.
- Daftarkan sitemap ke Search Console.
- Pantau error setelah peluncuran.

### Tahap 7: Optimasi fase 2

Evaluasi data trafik dan klik, lalu implementasikan hub topik, tautan internal lanjutan, impor massal, serta laporan performa sesuai prioritas.

> Estimasi durasi ditentukan setelah jumlah produk awal, kapasitas pengembang, dan kebutuhan desain dipastikan.

## 15. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Konten tipis atau duplikat | Template konten, spesifikasi terstruktur, dan artikel pendukung |
| Tautan marketplace rusak | Pemeriksaan tautan berkala dan pengelolaan tautan melalui admin |
| Harga tidak sesuai marketplace | Harga acuan, rentang harga, atau tanggal pembaruan |
| Pertumbuhan SEO lambat | Produksi konten bertahap dan pemantauan Search Console |
| Beban produksi konten tinggi | Workflow editorial, template, dan impor massal |
| Package tidak kompatibel | Verifikasi dependensi sebelum instalasi |
| Klik bot mendominasi laporan | Rate limiting, deteksi bot, dan analisis anomali |
| Redirect disalahgunakan | Validasi domain tujuan dan daftar domain yang diizinkan |
| Database atau media hilang | Backup otomatis dan pengujian pemulihan |
| Fitur melebar sebelum MVP selesai | Prioritas fitur berdasarkan ruang lingkup fase 1 |

## 16. Pertanyaan terbuka

Keputusan berikut masih perlu dikonfirmasi sebelum implementasi fitur terkait:

1. Apakah katalog memuat produk Jaya Intero saja atau produk dari banyak merek dan penjual?
2. Apakah tautan marketplace merupakan tautan toko resmi Jaya Intero atau tautan afiliasi?
3. Apakah harga produk ditampilkan, disembunyikan, atau ditampilkan sebagai rentang?
4. Berapa jumlah produk saat peluncuran, dan apakah data awal berasal dari Excel, marketplace, atau pemasok?
5. Apakah nomor WhatsApp hanya satu atau berbeda menurut kategori dan produk?
6. Siapa yang bertanggung jawab menulis dan meninjau artikel?
7. Apakah target wilayah nasional atau berfokus pada Sumatera Utara dan sekitarnya?
8. Apa indikator yang menentukan kelayakan pengembangan checkout dan payment gateway?
9. Apakah impor produk harus tersedia pada MVP atau dapat ditambahkan di fase 2?