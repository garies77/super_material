# PRD Supermaterial

| Informasi | Detail |
|---|---|
| Penyusun | Garies |
| Versi | 0.11 (Draft Revisi) |
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
16. [Keputusan dan pertanyaan terbuka](#16-keputusan-dan-pertanyaan-terbuka)

---

## 1. Ringkasan

Supermaterial adalah website katalog bahan bangunan dan platform konten berbasis SEO yang bertujuan mendapatkan trafik organik dari mesin pencari serta mengarahkan pengunjung ke kanal pembelian.

Pengunjung dapat menemukan produk melalui Google, halaman kategori, pencarian internal, atau artikel edukasi. Pembelian dilakukan melalui Shopee, Tokopedia, Lazada, TikTok Shop, atau WhatsApp.

Pada fase awal, website tidak menyediakan keranjang belanja, checkout, maupun pembayaran langsung. Seluruh transaksi berlangsung melalui kanal eksternal.

Supermaterial dikembangkan sebagai website baru dari nol, menggunakan repository dan fondasi aplikasi yang dirancang khusus untuk kebutuhan produk ini. Tidak ada proses migrasi atau upgrade dari proyek sebelumnya.

Website dibangun sebagai white label: nama website, palet warna, logo, dan identitas visual lain dapat diubah sepenuhnya dari panel admin tanpa mengubah kode. Perubahan nama website dan palet warna memerlukan konfirmasi password ulang karena termasuk keputusan besar (bagian 8.10).

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
| Backend | Laravel 13 |
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
- Jaringan iklan pihak ketiga (misalnya AdSense) dan penjualan ruang iklan otomatis.
- Akun pelanggan dan riwayat pesanan.
- Sinkronisasi stok real-time dengan marketplace.
- Sinkronisasi harga otomatis.
- Perhitungan ongkir.
- Pelacakan pembelian aktual setelah pengunjung meninggalkan website.
- Aplikasi mobile native.
- Integrasi ERP atau sistem akuntansi.
- Mode multi-brand (multi-tenant) dalam satu instalasi.

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

Fondasi Laravel, Filament 5, autentikasi dan peran, katalog produk, kategori, artikel, iklan artikel, banner kampanye, kanal pembelian, pencatatan klik, SEO teknis dasar, serta halaman statis.

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

Tampilan dan perilaku halaman detail produk (galeri dengan video, harga coret, varian bergambar, dan tombol aksi) dijelaskan di bagian 8.11.

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

Tampilan artikel mengikuti pola halaman berita CNN Indonesia: tata letak satu kolom bacaan yang bersih, foto utama dengan keterangan, metadata penulis dan waktu terbit yang jelas, serta rangkaian blok rujukan di sekitar isi artikel. Yang ditiru adalah struktur dan perilaku. Logo, tipografi khas, dan aset merek CNN Indonesia tidak dipakai.

#### Data artikel

Setiap artikel memiliki:

- Judul (H1) dan slug.
- Ringkasan, dipakai sebagai meta description dan teks kartu.
- Isi melalui editor teks kaya, termasuk blok HTML kustom (HTML, CSS, dan JavaScript).
- Gambar utama dengan keterangan foto dan kredit foto.
- Penulis dan editor (nama lengkap dan inisial).
- Dateline, contoh: "Medan, Supermaterial --". Dapat diubah per artikel.
- Kategori artikel dan tag.
- Tanggal terbit dan tanggal pembaruan, ditampilkan dengan hari dan zona waktu, contoh: "Selasa, 20 Okt 2026 10:04 WIB".
- Metadata SEO.
- Status draft, terjadwal, atau terbit.
- Tautan eksplisit ke produk dan kategori.
- Daftar isi otomatis berdasarkan heading.
- Estimasi waktu baca.

Artikel dapat dijadwalkan untuk terbit pada waktu tertentu. Halaman produk menampilkan artikel yang ditautkan secara eksplisit ke produk tersebut.

Tanggal pembaruan hanya berubah saat konten benar-benar mengalami perubahan bermakna.

#### Struktur halaman artikel (desktop)

Urutan dari atas ke bawah:

1. Header dan menu kategori utama.
2. Breadcrumb dua tingkat: Kategori Artikel > Subkategori (contoh: Panduan > Atap).
3. Judul artikel.
4. Baris metadata: nama penulis, lalu tanggal dan jam terbit.
5. Tombol Bagikan: salin tautan, Facebook, X, dan WhatsApp. Setelah salin tautan berhasil, muncul pesan "URL berhasil disalin".
6. Gambar utama dengan keterangan foto bercetak miring dan kredit foto di bawahnya.
7. Daftar Isi (dapat dilipat).
8. Isi artikel.
9. Inisial penulis dan editor di akhir isi, contoh: (gar/ed).
10. Tombol Bagikan kedua di bawah isi.
11. Topik Terkait.
12. Artikel Terkait.
13. Rekomendasi Untukmu.
14. Produk Terkait.

Slot iklan internal ditempatkan di beberapa posisi pada urutan ini sesuai bagian 8.8.

```text
+------------------------------------------------------------------+
| Panduan > Atap                                                   |
| Judul Artikel Satu Sampai Tiga Baris                             |
| Penulis                                                          |
| Selasa, 20 Okt 2026 10:04 WIB                                    |
| Bagikan: [salin] [FB] [X] [WA]                                   |
| +--------------------------------------------+  +-------------+ |
| |  Gambar utama                              |  | Terpopuler  | |
| +--------------------------------------------+  | 1. ...      | |
| Keterangan foto miring. (Foto: Kredit)          | 2. ...      | |
| Daftar Isi [v]                                  | 3. ...      | |
| Medan, Supermaterial --                         |             | |
| Paragraf isi ...                                |             | |
| +- Lihat Juga: Judul artikel atau produk -----+ |             | |
| Paragraf isi ...                                +-------------+ |
| (gar/ed)                                                         |
| Topik Terkait: [tag] [tag] [tag]                                 |
| Artikel Terkait | Rekomendasi Untukmu | Produk Terkait         |
+------------------------------------------------------------------+
```

*Ilustrasi struktur, bukan desain final.*

#### Elemen halaman

**Judul dan metadata**

- Satu H1 per halaman, tanpa pemotongan.
- Penulis ditampilkan sebagai teks, ditautkan ke halaman penulis jika tersedia.
- Waktu terbit memakai zona waktu situs (WIB). Jika artikel pernah diperbarui, tanggal pembaruan ditampilkan terpisah di samping tanggal terbit.

**Gambar utama**

- Rasio 16:9, lebar penuh kolom bacaan.
- Keterangan foto dan kredit wajib diisi untuk artikel dengan gambar utama.
- Gambar utama tidak lazy loading dan menjadi elemen LCP.

**Daftar isi**

- Dibuat otomatis dari heading H2 dan H3.
- Tampil sebagai kotak yang dapat dilipat di atas isi, dengan tautan anchor ke tiap bagian.
- Disembunyikan jika artikel memiliki kurang dari tiga heading.

**Isi artikel**

- Lebar kolom bacaan sekitar 650 sampai 720 piksel, ukuran huruf nyaman untuk bacaan panjang.
- Paragraf pembuka diawali dateline berhuruf tebal.
- Tautan inline pada kata kunci ke tag, kategori, produk, atau artikel lain.
- Blok sitasi (kutipan langsung) memiliki gaya tersendiri.
- Gambar dalam isi dilengkapi keterangan dan teks alternatif.

**Blok Lihat Juga**

- Kotak rujukan yang disisipkan di tengah isi artikel, berisi label "Lihat Juga" dan tautan ke artikel lain.
- Dua varian: hanya teks, atau dengan gambar kecil di kiri dan label kategori (contoh: PANDUAN, TIPS) di atas judul.
- Varian ketiga untuk produk: kartu mini berisi gambar, nama, harga, dan tombol "Lihat Produk" yang menuju halaman produk. Ini menjadi jalur utama dari artikel ke klik pembelian.
- Disisipkan oleh penulis melalui editor, bukan otomatis.

**Embed**

- Mendukung embed video (YouTube) dan Instagram di dalam isi.
- Embed dimuat setelah interaksi atau saat mendekati area layar (lazy) agar tidak membebani LCP.
- Jika embed gagal dimuat, tampil tautan teks ke sumber.

**Blok HTML kustom**

Editor artikel menyediakan blok "HTML Kustom" untuk menyisipkan elemen yang tidak tersedia di editor teks kaya, misalnya tabel perbandingan bergaya khusus, kalkulator kebutuhan material, kotak promosi, atau widget interaktif. Cara kerjanya mengikuti editor kode pada WordPress.

Antarmuka editor:

- Blok dibuka dalam modal besar dengan tiga tab: **HTML**, **CSS**, dan **JavaScript**.
- Setiap tab berisi kolom kode berfont monospasi dengan penanda placeholder (contoh: "Write HTML...") dan nomor baris.
- Tombol layar penuh di pojok kanan atas untuk memperluas area kerja.
- Panel pratinjau langsung di samping kolom kode yang memperlihatkan hasil render.
- Tombol **Update** menyimpan blok ke dalam artikel, dan **Cancel** menutup modal tanpa menyimpan. Jika ada perubahan yang belum disimpan, muncul konfirmasi sebelum modal ditutup.
- Di dalam artikel, blok ditampilkan sebagai kotak dengan label "HTML Kustom" dan tombol Edit. Pratinjau penuh muncul saat artikel dipratinjau.

Mode tampil, dipilih saat membuat blok:

| Mode | Isi yang diizinkan | Cara render |
|---|---|---|
| Sebaris (default) | HTML dan CSS | HTML disanitasi lalu dimasukkan langsung ke dalam isi artikel. CSS dibatasi pada blok tersebut. |
| Terisolasi | HTML, CSS, dan JavaScript | Dirender di dalam iframe ber-`sandbox` dengan tinggi menyesuaikan isi. Skrip tidak dapat mengakses cookie, sesi, atau halaman utama. |

Hak akses:

| Peran | Akses |
|---|---|
| Admin | HTML, CSS, JavaScript, dan kedua mode tampil |
| Editor | HTML dan CSS pada mode Sebaris |
| Penulis | Tidak dapat membuat atau mengubah blok HTML kustom. Blok yang sudah ada tetap terlihat dan tidak berubah saat artikel disunting. |

Hak akses ditegakkan di backend. Permintaan yang memuat JavaScript dari akun tanpa izin ditolak, bukan hanya disembunyikan di antarmuka.

Aturan keamanan:

- Mode Sebaris: HTML melewati allow-list tag dan atribut. Tag `script`, atribut `on*` (seperti `onclick`), tautan `javascript:`, dan `iframe` selain sumber yang diizinkan (YouTube) dihapus.
- CSS pada mode Sebaris otomatis diberi pembungkus unik per blok agar tidak memengaruhi bagian lain halaman, dan tidak boleh memuat `@import` ke domain luar.
- Mode Terisolasi: iframe memakai `sandbox="allow-scripts"` tanpa `allow-same-origin`. Skrip eksternal hanya boleh dari host yang diizinkan (cdnjs.cloudflare.com dan cdn.jsdelivr.net), dengan versi tertentu dan bukan versi terbaru otomatis.
- Permintaan jaringan dari blok ke domain luar diblokir melalui kebijakan keamanan konten (Content Security Policy).
- Tautan keluar di dalam blok ditambahi `rel="noopener noreferrer"` secara otomatis.
- Setiap pembuatan dan perubahan blok dicatat di log (pelaku, waktu, dan ringkasan perubahan).

Dampak SEO dan performa:

- Konten mode Sebaris menjadi bagian dari HTML artikel dan dapat dirayapi. Konten mode Terisolasi berada di dalam iframe dan tidak dihitung sebagai isi artikel, sehingga dipakai untuk elemen interaktif, bukan untuk teks yang ingin diindeks.
- Blok Terisolasi dimuat saat mendekati area layar (lazy) dan ruangnya dipesan sejak awal (tinggi minimum) untuk mencegah pergeseran tata letak (CLS).
- Blok tidak boleh menyisipkan tautan atau teks tersembunyi untuk memanipulasi peringkat. Kebijakan ini berlaku untuk semua konten artikel.
- Ukuran gabungan kode per blok dibatasi (bawaan 100 KB).

**Topik Terkait**

- Deretan tag berbentuk chip di bawah isi, masing-masing menuju halaman tag.
- Tag utama artikel ditandai berbeda dari tag lain.
- Menampilkan sebagian tag lebih dulu, dengan tombol "Lihat Selengkapnya" untuk sisanya.

**Artikel Terkait dan Rekomendasi Untukmu**

- Artikel Terkait: ditentukan dari tag dan kategori yang sama, atau dipilih manual.
- Rekomendasi Untukmu: artikel terbaru dan populer dari kategori lain, dalam bentuk daftar bergambar kecil.
- Keduanya berupa tautan biasa agar dapat dirayapi.

**Terpopuler**

- Sidebar kanan di desktop berisi daftar bernomor artikel terpopuler.
- Dihitung dari jumlah kunjungan dalam periode tertentu (misalnya 7 hari), dengan pembatasan bot.
- Pada mobile, bagian ini dipindah ke bawah isi.

**Produk Terkait**

- Carousel atau grid produk dari produk yang ditautkan eksplisit ke artikel, dilengkapi harga dan harga coret mengikuti kartu produk pada bagian 8.11.5.4.
- Tampil setelah Topik Terkait agar tidak mengganggu bacaan.

**Bagikan**

- Salin tautan, Facebook, X, dan WhatsApp.
- Tautan dibagikan tanpa parameter pelacakan tambahan.
- Pesan WhatsApp berisi judul dan URL artikel.

#### Halaman daftar artikel

- Setiap kategori artikel memiliki halaman daftar di `/artikel/kategori/{slug}`.
- Bagian atas menampilkan satu artikel unggulan dengan gambar besar, di bawahnya daftar artikel terbaru.
- Setiap baris berisi gambar kecil, label kategori, judul, dan waktu relatif (contoh: "2 jam yang lalu"), diganti tanggal jika lebih dari 24 jam.
- Paginasi memakai URL yang dapat dirayapi.
- Halaman tag memakai pola yang sama dan memiliki metadata SEO sendiri.

#### Yang tidak ditiru dari CNN Indonesia

- Iklan pihak ketiga. Iklan di artikel dikelola sendiri oleh admin (lihat bagian 8.8).
- Kolom komentar dan sistem login pembaca.
- Login, For You, dan fitur riwayat pencarian.
- Aplikasi mobile dan ajakan mengunduh aplikasi.

#### SEO dan data terstruktur artikel

- JSON-LD `Article` (atau `NewsArticle` bila relevan) memuat judul, gambar, tanggal terbit, tanggal pembaruan, penulis, dan penerbit.
- `BreadcrumbList` sesuai breadcrumb yang tampil.
- Meta, Open Graph, dan Twitter Card memakai ringkasan serta gambar utama.
- Daftar isi, Lihat Juga, dan Topik Terkait berada di HTML awal sebagai tautan biasa.

#### Kriteria penerimaan

1. Halaman artikel memiliki urutan elemen sesuai struktur di atas pada desktop dan mobile.
2. Keterangan foto dan kredit foto tampil di bawah gambar utama.
3. Daftar isi terbentuk otomatis dan tautan anchor berfungsi.
4. Blok Lihat Juga (teks, bergambar, dan produk) dapat disisipkan dari editor dan tampil sesuai varian.
5. Klik pada kartu produk di dalam artikel tercatat sebagai klik dengan artikel sebagai halaman asal.
6. Tombol Bagikan berfungsi dan menampilkan pesan setelah tautan tersalin.
7. Topik Terkait, Artikel Terkait, dan Terpopuler tampil dan dapat dirayapi.
8. Blok HTML kustom dapat dibuat dari modal berisi tab HTML, CSS, dan JavaScript dengan pratinjau langsung, tombol Update dan Cancel, serta mode layar penuh.
9. Hanya Admin yang dapat menyimpan JavaScript, dan Penulis tidak dapat membuat atau mengubah blok HTML kustom, termasuk jika permintaan dikirim langsung ke server.
10. HTML pada mode Sebaris tidak dapat menjalankan skrip, dan JavaScript pada mode Terisolasi tidak dapat mengakses cookie atau halaman utama.
11. LCP halaman artikel di bawah 2,5 detik pada data lapangan.

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
- Seluruh tautan marketplace adalah tautan toko resmi Jaya Intero, bukan tautan afiliasi. Tautan keluar menggunakan `rel="noopener noreferrer"`. Atribut `nofollow` dan `sponsored` hanya dipakai jika kelak ada tautan afiliasi atau bersponsor.
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

Rute `/go/` tidak dimasukkan ke sitemap dan diblokir dari perayapan melalui konfigurasi `robots.txt`. Rute teknis ini tidak dimaksudkan untuk diindeks, sehingga cukup diblokir lewat `robots.txt` tanpa `noindex`, karena Googlebot tidak membaca `noindex` pada URL yang diblokir (lihat 9.11.3). `robots.txt` bukan mekanisme keamanan.

### 8.7 Panel admin Filament 5

Panel admin mencakup:

- Produk.
- Kategori.
- Merek.
- Varian produk.
- Galeri gambar.
- Artikel (termasuk blok Lihat Juga, blok HTML kustom, embed, keterangan dan kredit foto).
- Tag.
- Kanal pembelian.
- Halaman statis.
- FAQ.
- Pengguna dan peran.
- Pengaturan website dan branding (nama, palet warna, logo, dengan konfirmasi password).
- Redirect 301.
- Iklan artikel (slot, jadwal, penargetan, dan laporan).
- Banner kampanye modal (jadwal, penargetan, frekuensi, dan laporan).
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

### 8.8 Iklan internal pada artikel

Admin dapat menempatkan iklan buatan sendiri (promosi produk, kampanye, atau iklan mitra) pada slot tertentu di halaman artikel. Pada MVP tidak ada jaringan iklan pihak ketiga (seperti AdSense), seluruh iklan dikelola penuh oleh admin.

#### Slot iklan

| Slot | Posisi |
|---|---|
| Atas artikel | Di bawah baris Bagikan, sebelum gambar utama |
| Dalam isi 1 | Setelah paragraf ke-N (N diatur admin, bawaan 3) |
| Dalam isi 2 | Setelah paragraf ke-M (bawaan 8), hanya tampil jika artikel cukup panjang |
| Sidebar | Di atas daftar Terpopuler (desktop) |
| Bawah artikel | Setelah inisial penulis, sebelum Topik Terkait |

Aturan tampilan:

- Setiap iklan diberi label "Iklan" atau "Sponsor" yang terlihat jelas agar tidak menyerupai isi artikel.
- Pada slot dalam isi, iklan muncul bersama teks "Scroll untuk melanjutkan" bila diaktifkan, mengikuti pola CNN Indonesia.
- Ruang iklan dipesan sejak awal (tinggi tetap) untuk mencegah pergeseran tata letak (CLS).
- Iklan tidak boleh menutupi isi, tombol bagikan, atau daftar isi.
- Pada mobile, slot sidebar dipindah ke bawah isi.

#### Format iklan

- Gambar dengan tautan (beberapa ukuran untuk desktop dan mobile).
- Kartu produk (mengambil data dari katalog: gambar, nama, harga, harga coret).
- Teks dengan tombol ajakan (CTA).
- Kode HTML kustom, hanya dapat dibuat oleh peran Admin dan disanitasi.

#### Pengelolaan di admin

- Admin membuat iklan, memilih slot, format, tautan tujuan, dan materi gambar atau teks.
- Penjadwalan: tanggal mulai dan selesai, serta status aktif atau nonaktif.
- Penargetan: semua artikel, kategori artikel tertentu, tag tertentu, atau artikel tertentu.
- Prioritas dan rotasi: jika beberapa iklan berebut satu slot, iklan berprioritas lebih tinggi tampil lebih dulu, dan iklan setara dirotasi bergantian.
- Pratinjau slot sebelum aktif.

#### Pelacakan

- Impresi dicatat saat iklan terlihat sedikitnya 50 persen di layar selama 1 detik.
- Klik dicatat melalui rute internal `/ad/{iklan}` dengan pengalihan HTTP 302, serupa rute `/go/`.
- Data per iklan: impresi, klik, CTR, slot, dan halaman asal.
- Alamat IP mentah tidak disimpan, dengan pembatasan laju dan deteksi bot.
- Laporan admin menampilkan performa per iklan dan per slot, dengan filter tanggal dan ekspor CSV.

#### Ketentuan tautan dan SEO

- Iklan yang mengarah ke produk atau halaman Supermaterial sendiri menggunakan tautan biasa.
- Iklan yang mengarah ke situs mitra atau marketplace menggunakan `rel="sponsored nofollow"` dan dibuka di tab baru.
- Isi iklan dimuat sebagai bagian terpisah dari isi artikel dan tidak dimasukkan ke structured data artikel.

### 8.9 Banner kampanye (modal)

Banner kampanye adalah modal promosi yang muncul di atas halaman, dapat ditutup pengunjung, dan ditampilkan terbatas. Admin membuat banner sesuai kampanye yang berjalan, misalnya promo bulanan, diskon musiman, atau peluncuran produk.

#### Perilaku tampil

- Modal muncul di tengah layar dengan latar gelap transparan.
- Muncul setelah jeda singkat (bawaan 5 detik) atau setelah pengunjung menggulir sekitar 30 persen halaman, sesuai pengaturan per banner. Tidak muncul saat halaman pertama kali dimuat agar tidak mengganggu LCP dan pengalaman pembaca.
- Pengunjung dari hasil pencarian Google tidak langsung dihadapkan pada modal di kunjungan awal halaman. Modal diatur muncul setelah interaksi atau jeda, agar tidak dinilai sebagai interstitial yang mengganggu.
- Hanya satu modal aktif pada satu waktu. Jika ada beberapa kampanye aktif, yang berprioritas tertinggi tampil lebih dulu.
- Modal tidak tampil pada rute teknis (`/go/`, `/ad/`), halaman hasil pencarian, dan halaman kebijakan.

#### Cara menutup

- Tombol tutup (X) yang jelas di pojok modal, dengan area sentuh yang nyaman.
- Klik di area gelap di luar modal menutup modal.
- Tombol Esc menutup modal pada desktop.
- Tombol ajakan (CTA) pada modal menuju halaman tujuan dan sekaligus menutup modal.
- Opsi tambahan "Jangan tampilkan lagi" bila diaktifkan admin.

#### Frekuensi tampil

Admin memilih salah satu aturan per banner:

| Aturan | Keterangan |
|---|---|
| Sekali saja | Satu kali per pengunjung selama kampanye berlangsung |
| Sekali per sesi | Muncul lagi pada sesi kunjungan berikutnya |
| Setiap N hari | Muncul lagi setelah N hari sejak ditutup (bawaan 7) |

- Status "sudah ditutup" disimpan di browser pengunjung (localStorage atau cookie) beserta ID kampanye dan waktu penutupan. Kampanye baru dengan ID berbeda tetap muncul.
- Pengunjung yang menutup modal tidak melihat modal yang sama pada halaman lain dalam periode pembatasan.
- Penyimpanan ini hanya untuk membatasi tampilan dan tidak berisi data pribadi. Hal ini dicantumkan di kebijakan privasi.

#### Konten banner

- Gambar utama (rasio dan ukuran terpisah untuk desktop dan mobile).
- Judul, teks singkat, dan tombol CTA dengan teks serta tautan yang ditentukan admin.
- Tata letak: hanya gambar, atau gambar dengan teks.
- Tautan CTA dapat ke produk, kategori, artikel, WhatsApp, atau URL luar.
- Teks alternatif gambar wajib diisi.

#### Pengelolaan di admin

- Admin membuat banner kapan saja dan menentukan tanggal mulai serta selesai. Banner tampil dan berhenti secara otomatis sesuai jadwal.
- Status: draft, terjadwal, aktif, selesai, atau dinonaktifkan manual.
- Penargetan halaman: seluruh situs, beranda saja, kategori tertentu, halaman produk, atau halaman artikel.
- Penargetan perangkat: desktop, mobile, atau keduanya.
- Pengaturan jeda tampil, aturan frekuensi, dan prioritas.
- Duplikasi banner untuk mempermudah pembuatan kampanye berikutnya.
- Pratinjau modal dalam tampilan desktop dan mobile sebelum aktif.

#### Pelacakan

- Impresi (modal tampil), klik CTA, dan penutupan dicatat per kampanye.
- Parameter UTM otomatis ditambahkan pada tautan CTA (misalnya `utm_campaign` sesuai nama kampanye) agar terbaca di Google Analytics 4.
- Laporan admin menampilkan impresi, klik, CTR, dan tingkat penutupan per banner dengan filter tanggal.

#### Aksesibilitas dan teknis

- Modal memakai `role="dialog"` dan `aria-modal="true"` dengan judul yang terbaca pembaca layar.
- Fokus pindah ke modal saat dibuka, terkunci di dalamnya, lalu kembali ke elemen sebelumnya saat ditutup.
- Gulir halaman di belakang modal dikunci selama modal terbuka.
- Gambar banner dimuat setelah halaman utama selesai dan tidak mempengaruhi LCP.
- Perilaku animasi menghormati pengaturan `prefers-reduced-motion`.

#### Kriteria penerimaan

1. Admin dapat membuat banner, memilih jadwal, halaman target, dan aturan frekuensi, lalu banner tampil otomatis pada periode yang ditentukan.
2. Modal muncul setelah jeda atau gulir, bukan saat halaman dimuat.
3. Modal dapat ditutup dengan tombol X, klik di luar modal, dan tombol Esc.
4. Setelah ditutup, modal tidak muncul lagi sesuai aturan frekuensi yang dipilih.
5. Kampanye baru tetap tampil pada pengunjung yang sebelumnya menutup kampanye lain.
6. Impresi, klik, dan penutupan tercatat dan muncul di laporan admin.
7. Modal dapat dioperasikan dengan keyboard dan pembaca layar.
8. Banner tidak mengubah nilai LCP halaman secara signifikan.

### 8.10 White label dan pengaturan branding

Supermaterial dibangun sebagai website white label: identitas visual dan nama situs tidak ditulis langsung di kode, melainkan diatur penuh dari panel admin. Satu basis kode dapat dipakai ulang untuk brand lain hanya dengan mengganti pengaturan, tanpa mengubah kode atau membangun ulang tampilan.

Nama Supermaterial dan palet warna awal adalah nilai bawaan (default), bukan nilai yang tertanam.

#### Prinsip

1. Tidak ada nama brand, warna, atau logo yang ditulis permanen di template, kode, atau stylesheet. Semuanya dibaca dari pengaturan.
2. Pengaturan branding hanya dapat diubah oleh peran Admin. Editor dan Penulis tidak memiliki akses.
3. Perubahan pada nama website dan palet warna tergolong keputusan besar dan wajib konfirmasi password (lihat bagian konfirmasi password).
4. Satu instalasi melayani satu brand. Setiap brand baru menggunakan instalasi dan database sendiri. Mode multi-brand dalam satu instalasi tidak termasuk MVP.

#### Pengaturan identitas

| Pengaturan | Keterangan | Konfirmasi password |
|---|---|---|
| Nama website | Nama utama situs, contoh: Supermaterial | Ya |
| Nama singkat | Dipakai pada ruang sempit seperti judul tab dan tombol | Ya |
| Tagline | Kalimat pendek di bawah logo atau di beranda | Tidak |
| Nama perusahaan | Untuk footer, kebijakan privasi, dan structured data Organization | Tidak |
| Logo (terang dan gelap) | Untuk header dan footer, dengan teks alternatif | Tidak |
| Favicon | Ikon tab browser | Tidak |
| Gambar Open Graph bawaan | Cadangan jika halaman tidak punya gambar sendiri, hanya untuk berbagi di media sosial dan tidak dimasukkan ke data terstruktur | Tidak |
| Kontak default | Email, nomor WhatsApp bawaan, alamat, dan tautan media sosial | Tidak |

Daftar pengaturan yang memerlukan konfirmasi password disimpan sebagai konfigurasi dan dapat diperluas (misalnya menambahkan logo) tanpa mengubah kode.

#### Palet warna

Admin dapat mengatur token warna berikut:

| Token | Fungsi |
|---|---|
| Primary | Warna utama brand: header, tautan penting, dan tombol utama |
| Secondary | Warna pendamping: tombol sekunder, bidang, dan label |
| Accent | Penekanan: badge, harga jual, dan sorotan |
| Latar halaman | Warna dasar halaman |
| Permukaan | Latar kartu, kotak, dan blok harga |
| Teks utama | Warna isi dan judul |
| Teks pendukung | Keterangan, label, dan teks sekunder |
| Garis | Batas kartu dan pemisah |
| Sukses | Status berhasil |
| Peringatan | Status peringatan |
| Bahaya | Pesan kesalahan |

Aturan teknis:

- Admin mengisi warna dasar dengan pemilih warna atau kode HEX. Variasi turunan (terang, gelap, dan kondisi hover) dibuat otomatis dari warna dasar.
- Warna diterapkan melalui CSS variable yang dipetakan ke Tailwind, sehingga seluruh komponen publik (tombol, badge diskon, blok harga, carousel, modal kampanye, kartu artikel) mengikuti palet tanpa kompilasi ulang.
- Pengaturan lain yang dapat diubah: font dari daftar pilihan yang sudah disiapkan, radius sudut (tajam, sedang, bulat), dan gaya tombol.
- Tersedia tombol "Kembalikan ke bawaan".

#### Validasi kontras

- Sistem menghitung rasio kontras antara teks dan latar, serta antara teks tombol dan warna tombol.
- Pasangan warna di bawah rasio 4,5:1 (WCAG 2.1 AA) menampilkan peringatan jelas. Penyimpanan tetap diizinkan dengan peringatan, atau ditolak jika opsi "blokir kontras rendah" diaktifkan.
- Pratinjau menampilkan contoh nyata: tombol, badge diskon, blok harga, dan kartu artikel.

#### Konfirmasi password

Perubahan pada nama website, nama singkat, dan palet warna hanya dapat diterapkan setelah admin memasukkan ulang password akunnya.

- Setelah admin menekan Simpan, muncul modal konfirmasi password. Perubahan baru disimpan jika password benar.
- Konfirmasi berlaku untuk satu tindakan simpan dan tidak memiliki masa toleransi. Perubahan berikutnya meminta password lagi.
- Pemeriksaan dilakukan di backend. Menyembunyikan tombol atau modal saja tidak cukup, dan permintaan tanpa konfirmasi yang sah ditolak.
- Percobaan password yang salah dibatasi (rate limit). Setelah beberapa kali gagal, tindakan dikunci sementara dan dicatat di log.
- Pemulihan versi lama (rollback) dan "Kembalikan ke bawaan" juga memerlukan konfirmasi password.
- Akun yang login lewat metode tanpa password lokal harus menetapkan password terlebih dahulu sebelum dapat mengubah pengaturan ini.

#### Pratinjau sebelum diterapkan

- Perubahan nama dan palet dapat dilihat dalam mode pratinjau yang hanya terlihat oleh admin yang sedang login.
- Pengunjung tetap melihat versi aktif sampai admin menekan Terapkan dan menyelesaikan konfirmasi password.
- Pratinjau tersedia untuk beranda, halaman produk, halaman kategori, halaman artikel, dan modal kampanye, dalam tampilan desktop dan mobile.

#### Dampak penggantian nama website

Nama website dibaca dari pengaturan di semua tempat berikut, sehingga tidak ada nama lama yang tertinggal:

- Judul halaman dan template meta title.
- Header, footer, dan halaman statis (tentang, kebijakan privasi, syarat penggunaan).
- Structured data `Organization` dan `WebSite`.
- Open Graph, Twitter Card, dan `site_name`.
- Teks pada email sistem dan pesan otomatis.
- Label iklan, banner kampanye, dan hasil pencarian.

Saat admin mengubah nama website, sistem menampilkan peringatan bahwa perubahan dapat memengaruhi tampilan nama di hasil mesin pencari sampai halaman dirayapi ulang. Setelah nama diterapkan, cache halaman publik dan sitemap diperbarui otomatis.

Domain, alamat repository, dan konfigurasi hosting tidak diubah melalui pengaturan branding. Hal tersebut dilakukan melalui konfigurasi environment.

#### Riwayat dan audit

- Setiap perubahan branding dicatat: pelaku, waktu, nilai lama, nilai baru, dan hasil (berhasil atau gagal konfirmasi).
- Log tidak menyimpan alamat IP mentah.
- Admin dapat melihat riwayat versi dan memulihkan versi sebelumnya (dengan konfirmasi password).
- Setelah nama atau palet diterapkan, email pemberitahuan dikirim ke seluruh akun Admin.

#### Kriteria penerimaan

1. Nama website, palet warna, logo, dan favicon dapat diubah dari panel admin dan langsung tercermin di seluruh halaman publik setelah diterapkan, tanpa perubahan kode.
2. Tidak ada nama brand atau kode warna yang tertanam di template publik. Pencarian kode tidak menemukan nama "Supermaterial" selain sebagai nilai bawaan.
3. Mengubah nama website atau palet warna tidak dapat disimpan tanpa password yang benar, termasuk jika permintaan dikirim langsung ke server.
4. Password salah berulang memicu pembatasan sementara dan tercatat.
5. Editor dan Penulis tidak dapat membuka atau mengubah pengaturan branding.
6. Pasangan warna dengan kontras rendah memunculkan peringatan.
7. Pratinjau menampilkan perubahan hanya kepada admin sampai diterapkan.
8. Setelah nama diubah, judul halaman, structured data, Open Graph, dan email sistem memakai nama baru.
9. Riwayat perubahan tercatat dan versi lama dapat dipulihkan.

### 8.11 Halaman detail produk

Halaman `/produk/{slug}` meniru tata letak dan pola interaksi halaman produk Shopee versi desktop. Bagian ini melengkapi 8.1 (katalog), 8.4 (kanal pembelian), dan 9.3 (data terstruktur).

#### 8.11.1 Tujuan

Halaman produk meniru tata letak dan pola interaksi halaman produk Shopee agar pengunjung langsung mengenali pola tampilannya: galeri dengan video, judul, harga coret, varian bergambar, dan tombol aksi. Perbedaannya, tombol aksi mengarah ke kanal pembelian (marketplace atau WhatsApp), bukan ke keranjang internal.

Yang ditiru adalah struktur, urutan elemen, dan perilaku. Logo, ikon, dan aset merek Shopee tidak dipakai. Warna aksen mengikuti identitas Jaya Intero.

#### 8.11.2 Struktur halaman (desktop)

Urutan dari atas ke bawah:

1. Header situs (logo, kolom pencarian, menu).
2. Breadcrumb: Beranda > Kategori > Subkategori > Nama Produk.
3. Kartu produk dua kolom:
   - Kiri: galeri media.
   - Kanan: informasi dan aksi pembelian.
4. Kartu spesifikasi produk.
5. Kartu deskripsi produk.
6. Artikel terkait dan FAQ.
7. Produk terkait (carousel).

```text
+------------------------------------------------------------------+
| Beranda > Kategori > Subkategori > Nama Produk                   |
+------------------------------------------------------------------+
| +------------------------+  [Badge] Nama produk lengkap          |
| |                        |  4.7 ★★★★★ | 33,5RB Penilaian         |
| |   Media utama          |  +---------------------------------+ |
| |   (gambar / video)     |  | Rp28.000  Rp70.000  -60%        | |
| |                        |  +---------------------------------+ |
| |                        |  Pengiriman     Info pengiriman      |
| +------------------------+  Jaminan        Keterangan jaminan   |
| < [v][1][2][3][4] >         VARIAN  [img Varian A] [img Varian B]|
|                             Kuantitas  [-] 1 [+]                 |
|                             [Chat WhatsApp] [Beli Sekarang]      |
+------------------------------------------------------------------+
```

*Ilustrasi struktur, bukan desain final.*

#### 8.11.3 Galeri media

##### 8.11.3.1 Media utama

- Menampilkan gambar atau video dalam satu area berukuran persegi (rasio 1:1).
- Media pertama pada urutan galeri dapat berupa video.
- Klik pada media utama membuka lightbox layar penuh dengan navigasi kiri dan kanan.
- Video diputar di area yang sama, dengan kontrol putar, jeda, suara, dan layar penuh.
- Video tidak diputar otomatis dengan suara. Pemutaran otomatis hanya diizinkan dalam kondisi senyap jika ditetapkan di pengaturan.

##### 8.11.3.2 Strip thumbnail

- Berada di bawah media utama, menampilkan sekitar lima thumbnail sekaligus.
- Thumbnail video diberi ikon putar di tengahnya dan selalu berada di posisi pertama jika ada.
- Panah kiri dan kanan muncul di tepi strip untuk menggeser thumbnail, dan hanya tampil jika thumbnail lebih banyak dari yang muat.
- Thumbnail aktif diberi bingkai berwarna.
- Mengarahkan kursor ke thumbnail (desktop) atau mengetuknya (mobile) mengganti media utama.
- Pada mobile, galeri menjadi carousel geser horizontal dengan indikator posisi (contoh: 2/8).

##### 8.11.3.3 Sumber video

- Unggah file MP4 melalui panel admin, atau tempel tautan video (YouTube).
- Gambar sampul (poster) wajib untuk setiap video dan dipakai sebagai thumbnail.
- Batas ukuran dan durasi file video ditetapkan di pengaturan.
- Video dimuat setelah interaksi pengunjung (klik putar), bukan saat halaman dibuka, agar LCP tidak terganggu.
- Gambar pertama, bukan video, menjadi elemen LCP dan tidak menggunakan lazy loading.

##### 8.11.3.4 Aturan media

- Jumlah media maksimal per produk: 20 (gambar dan video digabung).
- Urutan media diatur dengan drag and drop di admin.
- Setiap gambar memiliki teks alternatif.
- Gambar dikonversi ke WebP atau AVIF dengan beberapa ukuran (responsive srcset).

#### 8.11.4 Informasi produk (kolom kanan)

##### 8.11.4.1 Judul

- Menampilkan nama produk lengkap, maksimal tiga baris.
- Badge opsional di depan judul (contoh: Resmi, Pilihan, Baru). Jenis badge dikelola di admin.
- Satu H1 per halaman.

##### 8.11.4.2 Rating dan penilaian

- Menampilkan nilai rata-rata, bintang, dan jumlah penilaian.
- Bagian ini opsional dan hanya tampil jika produk memiliki data penilaian yang nyata.
- Sumber data pada MVP: diisi manual dari rekap marketplace dengan tanggal pembaruan, atau disembunyikan. Data ini tampil di halaman, tetapi tidak otomatis masuk JSON-LD (lihat 9.10.2).
- Data rating tidak boleh dikarang. Jika dimasukkan ke JSON-LD (AggregateRating), nilainya harus sesuai dengan yang terlihat di halaman.
- Tautan "Laporkan" tidak disertakan pada MVP.

##### 8.11.4.3 Blok harga

Blok harga berlatar abu-abu muda dan berisi:

| Elemen | Aturan |
|---|---|
| Harga jual | Ukuran terbesar, warna aksen. Diambil dari varian terpilih. |
| Harga coret | Harga normal sebelum diskon, dicoret, ukuran lebih kecil. Tampil hanya jika lebih tinggi dari harga jual. |
| Badge diskon | Persentase otomatis: (harga coret - harga jual) / harga coret, dibulatkan ke bilangan bulat. Contoh: -60%. |
| Rentang harga | Jika varian berbeda harga dan belum ada varian dipilih, tampil "Rp25.000 - Rp40.000". Setelah varian dipilih, tampil harga varian tersebut. |
| Harga disembunyikan | Jika produk diatur tanpa harga, blok diganti teks "Hubungi kami untuk harga" dengan tombol WhatsApp. |

Aturan tambahan:

- Harga coret dan harga jual disimpan per varian.
- Tanggal pembaruan harga ditampilkan kecil di bawah blok harga bila diaktifkan.
- Harga coret hanya boleh diisi jika memang pernah berlaku, agar tidak menyesatkan pembeli.
- Masa berlaku diskon opsional (tanggal mulai dan selesai). Setelah berakhir, harga coret hilang otomatis.

##### 8.11.4.4 Baris informasi

Dua baris label dan nilai, mengikuti pola Shopee:

- **Pengiriman:** keterangan area dan metode pengiriman (teks dari admin, bisa per produk atau global).
- **Jaminan:** keterangan jaminan produk atau kebijakan penukaran (teks dari admin, dengan ikon).

Keduanya opsional dan hanya tampil jika terisi.

##### 8.11.4.5 Varian

- Label "VARIAN" di kiri, pilihan di kanan.
- Setiap opsi berupa tombol berbingkai dengan gambar kecil di kiri dan nama varian di kanan. Jika varian tidak punya gambar, tombol hanya berisi teks.
- Opsi dibungkus ke baris berikutnya jika tidak muat.
- Opsi terpilih diberi bingkai dan teks berwarna aksen, ditambah tanda centang kecil di sudut.
- Opsi yang tidak aktif ditampilkan pudar dan tidak dapat dipilih.
- Mendukung lebih dari satu dimensi varian (contoh: Ukuran dan Ketebalan), masing-masing dengan barisnya sendiri.
- Memilih varian memperbarui secara langsung, tanpa memuat ulang halaman:
  - media utama (ke gambar varian jika ada),
  - harga jual, harga coret, dan badge diskon,
  - tautan tujuan tombol beli,
  - teks pesan WhatsApp otomatis.
- Varian terpilih tercermin di URL melalui parameter `?varian={slug}` agar dapat dibagikan. Canonical tetap menunjuk ke URL produk tanpa parameter.
- Pada kunjungan pertama, tidak ada varian terpilih (mengikuti Shopee), kecuali produk hanya memiliki satu varian.
- Jika tombol beli ditekan tanpa varian terpilih, halaman menggulir ke bagian varian dan menandainya dengan pesan "Silakan pilih varian".

##### 8.11.4.6 Kuantitas

- Kontrol stepper: tombol minus, kolom angka, tombol plus. Nilai awal 1, minimal 1.
- Karena tidak ada keranjang pada MVP, kuantitas dipakai untuk melengkapi pesan WhatsApp (contoh: "Saya ingin pesan 10 lembar").
- Untuk tautan marketplace, kuantitas tidak diteruskan dan hanya bersifat informasi.
- Satuan penjualan ditampilkan di samping stepper (contoh: batang, lembar, m2).
- Kontrol ini dapat dinonaktifkan per produk di admin.

##### 8.11.4.7 Tombol aksi

Dua tombol berdampingan, mengikuti posisi dan gaya tombol Shopee:

| Posisi | Tombol Shopee | Tombol Supermaterial | Perilaku |
|---|---|---|---|
| Kiri (outline) | Masukkan Keranjang | Chat WhatsApp | Membuka WhatsApp dengan pesan otomatis berisi nama produk, varian, kuantitas, dan URL halaman |
| Kanan (solid) | Beli Sekarang | Beli Sekarang | Membuka daftar kanal (Shopee, Tokopedia, Lazada, TikTok Shop) atau langsung ke kanal tunggal |

Aturan:

- Jika produk hanya memiliki satu kanal marketplace aktif, tombol "Beli Sekarang" langsung menuju kanal tersebut. Label dapat berubah menjadi "Beli di Shopee".
- Jika ada lebih dari satu kanal, tombol "Beli Sekarang" membuka popover atau modal kecil berisi tombol per kanal.
- Seluruh tautan melalui rute `/go/{produk}/{kanal}` dengan pencatatan klik (lihat bagian 8.6), membawa varian dan kuantitas sebagai parameter.
- Tautan dibuka di tab baru.
- Kanal tanpa tautan aktif tidak ditampilkan.
- Pada mobile, kedua tombol menempel di bagian bawah layar (sticky bar) dengan ukuran sentuh yang nyaman.

#### 8.11.5 Bagian di bawah kartu produk

##### 8.11.5.1 Spesifikasi produk

- Tabel dua kolom: label di kiri, nilai di kanan.
- Baris pertama menampilkan kategori dalam bentuk breadcrumb.
- Berisi merek, material, ukuran, ketebalan, dan atribut lain dari spesifikasi terstruktur.
- Atribut kosong tidak ditampilkan.

##### 8.11.5.2 Deskripsi produk

- Teks kaya dengan heading, daftar, dan gambar.
- Jika panjang, ditampilkan terpotong dengan tombol "Lihat selengkapnya". Teks lengkap tetap ada di HTML agar dapat dirayapi.

##### 8.11.5.3 Konten pendukung

- Artikel terkait yang ditautkan eksplisit.
- FAQ produk dalam bentuk accordion.

##### 8.11.5.4 Carousel produk terkait

- Judul seksi: "Produk Terkait" atau "Produk Lain dari Kategori Ini".
- Carousel horizontal dengan kartu produk: gambar, nama (maksimal dua baris), harga jual, harga coret, dan badge diskon.
- Menampilkan lima kartu per layar di desktop, dua hingga tiga di mobile.
- Panah kiri dan kanan di desktop, geser sentuh di mobile.
- Sumber data: produk dalam kategori yang sama, atau pilihan manual dari admin.
- Setiap kartu adalah tautan biasa ke halaman produk agar dapat dirayapi.

#### 8.11.6 Perubahan model data

##### 8.11.6.1 Tabel `product_media` (menggantikan `product_images`)

| Kolom | Keterangan |
|---|---|
| `id` | Primary key |
| `product_id` | Relasi ke produk |
| `type` | `image` atau `video` |
| `path` | Lokasi file atau tautan video |
| `provider` | `upload` atau `youtube` (untuk video) |
| `poster_path` | Gambar sampul video |
| `alt_text` | Teks alternatif |
| `sort_order` | Urutan tampil |

##### 8.11.6.2 Perubahan `product_variants`

| Kolom | Keterangan |
|---|---|
| `name` | Nama varian |
| `slug` | Untuk parameter `?varian=` |
| `sku` | Opsional |
| `image_id` | Relasi opsional ke `product_media` |
| `price` | Harga jual |
| `compare_at_price` | Harga coret (opsional) |
| `discount_starts_at`, `discount_ends_at` | Masa berlaku diskon (opsional) |
| `option_group` | Nama dimensi varian (contoh: Ukuran) |
| `is_active` | Status aktif |
| `sort_order` | Urutan tampil |

##### 8.11.6.3 Perubahan `products`

| Kolom | Keterangan |
|---|---|
| `badge` | Badge judul (opsional) |
| `shipping_note` | Teks pengiriman (opsional) |
| `guarantee_note` | Teks jaminan (opsional) |
| `rating_value`, `rating_count`, `rating_updated_at` | Data penilaian manual (opsional) |
| `show_quantity` | Menampilkan stepper kuantitas |
| `price_display` | `show`, `range`, atau `hidden` |

#### 8.11.7 Panel admin

- Pengelola media dengan unggah banyak file sekaligus, drag and drop urutan, penambahan video, dan unggah poster.
- Repeater varian dengan kolom nama, gambar, harga, harga coret, dan status.
- Persentase diskon dihitung otomatis dan ditampilkan sebagai pratinjau, tidak diinput manual.
- Validasi: harga coret harus lebih besar dari harga jual, jika tidak peringatan muncul dan harga coret tidak ditampilkan.
- Pratinjau halaman produk dari admin sebelum terbit.

#### 8.11.8 SEO dan data terstruktur

- JSON-LD `Product` memuat gambar, `offers` berupa satu `Offer` dengan `price` harga jual dan `priceCurrency` `IDR`, serta `VideoObject` jika ada video. `AggregateOffer` tidak dipakai untuk varian. Aturan lengkap ada di bagian 9.10.2.
- `AggregateRating` hanya disertakan jika rating tampil di halaman dan datanya sah.
- Harga di JSON-LD harus sama dengan harga yang terlihat.
- Parameter `?varian=` tidak diindeks sebagai halaman terpisah dan memakai canonical ke URL produk.
- Konten tab, accordion, dan teks "Lihat selengkapnya" tetap ada di HTML awal.

#### 8.11.9 Performa dan aksesibilitas

- Gambar utama dimuat lebih dulu (preload) dan tanpa lazy loading. Thumbnail dan produk terkait memakai lazy loading.
- Dimensi gambar ditentukan untuk mencegah pergeseran tata letak (CLS), termasuk ruang untuk blok harga dan strip thumbnail.
- Video memakai `preload="none"` dan poster.
- Semua kontrol (thumbnail, varian, stepper, panah carousel) dapat dioperasikan dengan keyboard dan memiliki label yang terbaca pembaca layar.
- Varian terpilih ditandai dengan atribut `aria-pressed` atau `aria-checked`, bukan hanya warna.
- Kontras teks harga dan badge memenuhi WCAG 2.1 AA.

#### 8.11.10 Perilaku mobile

- Galeri tampil penuh lebar dengan geser horizontal dan penanda 1/N.
- Blok harga, judul, dan varian tersusun vertikal.
- Pilihan varian dan kuantitas dapat dibuka dalam bottom sheet saat tombol sticky ditekan, mengikuti pola Shopee.
- Sticky bar berisi tombol Chat WhatsApp dan Beli Sekarang, tidak menutupi konten penting.
- Carousel produk terkait menggunakan geser sentuh tanpa panah.

#### 8.11.11 Kriteria penerimaan

1. Halaman memiliki urutan elemen sesuai bagian 8.11.2 pada desktop dan mobile.
2. Galeri dapat menampilkan video dan gambar, thumbnail video diberi ikon putar, dan panah muncul saat thumbnail melebihi ruang.
3. Harga coret dan badge diskon hanya tampil jika harga coret lebih besar dari harga jual, dan persentase dihitung otomatis.
4. Memilih varian mengubah media, harga, tautan beli, dan pesan WhatsApp tanpa memuat ulang halaman.
5. Menekan tombol beli tanpa memilih varian (pada produk bervarian) menggulir ke bagian varian dengan pesan peringatan.
6. Semua klik tombol beli tercatat di `click_events` beserta varian.
7. Video tidak memuat data sebelum pengunjung menekan putar, dan LCP tetap di bawah 2,5 detik.
8. Carousel produk terkait dapat digeser dengan panah (desktop) dan sentuhan (mobile), dan setiap kartu berupa tautan yang dapat dirayapi.
9. JSON-LD sesuai dengan konten yang terlihat.

#### 8.11.12 Di luar cakupan

- Keranjang dan checkout internal.
- Ulasan dan penilaian dari pengunjung langsung di website.
- Tombol "Laporkan", "Suka", dan "Bagikan" ala Shopee.
- Sinkronisasi harga dan stok otomatis dari marketplace.
- Voucher, flash sale, dan penghitung mundur.

### 8.12 API

#### 8.12.1 Tujuan dan pengguna

Supermaterial menyediakan REST API versi 1 agar data katalog, artikel, dan laporan klik dapat dipakai oleh sistem lain tanpa membuka panel admin atau menyalin data manual.
Pengguna API
Kebutuhan
Frontend lain (aplikasi mobile atau situs headless di fase berikutnya)
Membaca produk, kategori, artikel, dan pencarian
Otomatisasi internal (misalnya n8n)
Mengambil laporan klik, membuat atau memperbarui produk, mengimpor data
Dasbor eksternal (Google Sheets, Looker Studio)
Menarik ringkasan klik per kanal, produk, dan artikel
Admin konten
Mengelola token akses dari panel Filament
API memakai model, validasi, dan aturan bisnis yang sama dengan panel admin dan halaman publik. Tidak ada logika bisnis kedua. Pada MVP API hanya dipakai internal, terutama untuk otomatisasi n8n milik pengelola website.

#### 8.12.2 Prinsip

1. REST dengan JSON, awalan /api/v1. GraphQL tidak dipakai pada MVP.
2. Dua permukaan: API publik (baca saja, tanpa token) dan API admin (token, baca dan tulis sesuai izin).
3. API publik hanya mengembalikan konten berstatus terbit. Draf, terjadwal, dan produk nonaktif tidak pernah keluar.
4. Aturan konten yang berlaku di halaman juga berlaku di API: harga disembunyikan tidak dikirim, harga coret hanya muncul jika lebih tinggi dari harga jual, availability hanya ada jika admin mengisinya, dan data rating hanya ada jika tampil di halaman.
5. Tautan pembelian selalu berupa rute pelacak /go/{produk}/{kanal}, bukan URL marketplace mentah, agar klik tetap tercatat (bagian 8.6).
6. Pengaturan branding hanya dapat dibaca lewat API, tidak dapat diubah (bagian 8.10 mensyaratkan konfirmasi password).
7. Seluruh pemeriksaan izin dilakukan di backend.

#### 8.12.3 Autentikasi dan hak akses

- API publik: tanpa autentikasi, dengan pembatasan laju per alamat (alamat IP mentah tidak disimpan, dipakai dalam bentuk hash sementara).
- API admin: Laravel Sanctum, token pribadi dengan abilities. Token dikirim lewat header Authorization: Bearer {token}.
- Token dibuat oleh peran Admin di panel Filament, terikat ke satu pengguna. Hak akses efektif adalah irisan antara abilities token dan peran pengguna tersebut (Admin, Editor, Penulis).
- Token ditampilkan sekali saat dibuat dan disimpan dalam bentuk hash.
- Setiap token memiliki nama, tanggal kedaluwarsa (bawaan 90 hari), waktu terakhir dipakai, dan dapat dicabut kapan saja.
- Membuat token dengan ability tulis meminta konfirmasi password ulang, mengikuti pola keputusan besar di bagian 8.10.
- Pengguna yang dinonaktifkan atau diturunkan perannya langsung kehilangan akses token miliknya.
Ability
Cakupan
catalog:read
Membaca produk, varian, kategori, merek, tautan kanal, termasuk draf
catalog:write
Membuat dan mengubah produk, varian, media, tautan kanal
import:write
Menjalankan impor CSV atau Excel
reports:read
Membaca laporan klik
articles:read
Membaca artikel termasuk draf (Fase 2 untuk tulis)
Token tidak memiliki ability untuk pengaturan branding, pengguna, peran, redirect, atau iklan.

#### 8.12.4 Konvensi

Format dan tipe data
- Waktu dalam ISO 8601 beserta zona waktu situs, contoh 2026-10-22T10:22:00+07:00.
- Harga berupa bilangan bulat rupiah tanpa pemisah (contoh 28000), dengan currency bernilai IDR.
- Persentase diskon dihitung server, tidak diterima dari klien.
- Bahasa konten Indonesia. Kunci JSON memakai bahasa Inggris dalam snake_case.
Respons daftar
```json
{
  "data": [ ],
  "links": { "next": "https://.../api/v1/products?cursor=abc", "prev": null },
  "meta": { "per_page": 20, "total": 1284 }
}
```
- Paginasi berbasis cursor untuk daftar besar. per_page bawaan 20, maksimal 50 (publik) dan 100 (admin).
- Filter lewat filter[category]=slug, filter[brand]=slug, filter[price_min], filter[price_max].
- Urutan lewat sort=-published_at, sort=price, dan sejenisnya, hanya pada kolom yang diizinkan.
- Relasi lewat include=category,brand,variants,media. Kolom terbatas lewat fields.
Kesalahan
```json
{
  "error": {
    "code": "validation_failed",
    "message": "Data tidak valid.",
    "fields": { "compare_at_price": ["Harga coret harus lebih besar dari harga jual."] }
  }
}
```
Kode HTTP: 200, 201, 204, 304, 401, 403, 404, 409, 422, 429, 503. Saat mode pemeliharaan (9.11.6), API juga mengembalikan 503 dengan Retry-After.
Versi
Versi ada di URL (/api/v1). Perubahan yang merusak kompatibilitas masuk versi baru. Endpoint yang akan dihentikan diberi header Deprecation dan Sunset, dengan masa peralihan minimal 6 bulan.
Idempotensi
Permintaan POST pada API admin menerima header Idempotency-Key. Permintaan ulang dengan kunci yang sama dalam 24 jam mengembalikan hasil semula dan tidak membuat data ganda. Ini penting untuk otomatisasi yang mengulang permintaan saat gagal.

#### 8.12.5 Endpoint publik (baca saja)

Metode dan jalur
Fungsi
GET /api/v1/site
Nama, nama singkat, tagline, logo, favicon, palet warna, dan kontak dari pengaturan branding
GET /api/v1/categories
Pohon kategori (maksimal tiga level)
GET /api/v1/categories/{slug}
Detail kategori, teks pengantar, dan konten pendukung
GET /api/v1/brands
Daftar merek
GET /api/v1/products
Daftar produk dengan filter, urutan, dan paginasi
GET /api/v1/products/{slug}
Detail produk: spesifikasi, media, varian, FAQ, produk dan artikel terkait
GET /api/v1/products/{slug}/links
Tautan pembelian aktif per kanal dalam bentuk go_url, dengan parameter variant dan quantity
GET /api/v1/sales-channels
Kanal pembelian yang aktif
GET /api/v1/articles
Daftar artikel terbit, filter kategori dan tag
GET /api/v1/articles/{slug}
Detail artikel, penulis, tag, dan produk tertaut
GET /api/v1/tags, GET /api/v1/authors/{slug}
Tag dan profil penulis
GET /api/v1/search?q=
Pencarian produk dan artikel
Isi artikel dikirim sebagai HTML yang sudah disanitasi. Blok HTML kustom mode Terisolasi dikirim sebagai penanda blok beserta alamat iframe, bukan sebagai skrip mentah.

#### 8.12.6 Endpoint admin (token)

Metode dan jalur
Ability
Fungsi
GET /api/v1/admin/products
catalog:read
Daftar produk semua status
POST /api/v1/admin/products
catalog:write
Membuat produk
PATCH /api/v1/admin/products/{id}
catalog:write
Mengubah produk (perubahan slug memicu redirect 301 seperti di 9.16)
PUT /api/v1/admin/products/{id}/variants
catalog:write
Mengganti set varian beserta harga dan harga coret
POST /api/v1/admin/products/{id}/media
catalog:write
Unggah gambar atau video, atau tautan YouTube, dengan teks alternatif
PUT /api/v1/admin/products/{id}/links
catalog:write
Mengatur tautan kanal pembelian
POST /api/v1/admin/imports
import:write
Mengunggah berkas CSV atau Excel, diproses lewat antrean
GET /api/v1/admin/imports/{id}
import:write
Status impor dan daftar baris yang gagal
GET /api/v1/admin/reports/clicks
reports:read
Ringkasan klik, filter tanggal, kanal, kategori, dan pengelompokan per produk, kategori, atau artikel
GET /api/v1/admin/reports/clicks/export
reports:read
Ekspor CSV
Aturan tambahan:
- Validasi sama dengan Filament: harga coret lebih besar dari harga jual, tanggal diskon wajib bila ada harga coret, slug unik, URL kanal divalidasi, dan teks alternatif gambar wajib. Data yang dikirim lewat API langsung terbit tanpa tahap draf. Karena itu data yang gagal validasi ditolak seluruhnya dengan kode 422 dan tidak disimpan sebagian. Pengirim dapat mengirim status draft secara eksplisit bila ingin menyimpan tanpa menayangkan.
- Unggah gambar menjalankan pipeline yang sama: ganti nama file otomatis, tiga turunan rasio, dan batas minimum 50.000 piksel (9.13.3).
- Pembuatan dan perubahan lewat API tercatat di audit_logs dengan pelaku, token, waktu, dan ringkasan perubahan. Alamat IP mentah tidak disimpan.
- Laporan klik hanya berisi data agregat dan tidak memuat data pribadi.
- Penulisan artikel lewat API ditunda ke Fase 2, karena blok HTML kustom dan pembatasan JavaScript per peran (8.3) perlu uji tersendiri.

#### 8.12.7 Contoh bentuk data produk

```json
{
  "data": {
    "id": 412,
    "slug": "rangka-baja-ringan-c75",
    "name": "Rangka Baja Ringan C75",
    "badge": "Resmi",
    "brand": { "slug": "merek-a", "name": "Merek A" },
    "category": { "slug": "rangka-baja-ringan", "name": "Rangka Baja Ringan" },
    "summary": "Ringkasan produk.",
    "price": { "mode": "range", "min": 25000, "max": 40000, "currency": "IDR", "updated_at": "2026-10-20T09:00:00+07:00" },
    "availability": "InStock",
    "media": [
      { "type": "image", "url": "https://.../rangka-baja-ringan-c75-1.webp", "alt": "Rangka baja ringan C75 tampak depan", "width": 800, "height": 800 }
    ],
    "variants": [
      { "slug": "c75-070", "name": "C75 0,70 mm", "sku": "C75-070", "price": 28000, "compare_at_price": 70000, "discount_percent": 60, "discount_ends_at": "2026-10-31T23:59:00+07:00", "is_active": true }
    ],
    "links": [
      { "channel": "shopee", "go_url": "https://.../go/rangka-baja-ringan-c75/shopee" },
      { "channel": "whatsapp", "go_url": "https://.../go/rangka-baja-ringan-c75/whatsapp" }
    ],
    "canonical_url": "https://.../produk/rangka-baja-ringan-c75",
    "updated_at": "2026-10-20T09:00:00+07:00"
  }
}
```
Bila price_display produk bernilai hidden, objek price bernilai null dan varian tidak memuat harga.

#### 8.12.8 Keamanan, laju, dan cache

Aspek
Ketentuan
Pembatasan laju
Publik 60 permintaan per menit per alamat. Admin 120 permintaan per menit per token. Header X-RateLimit-Limit, X-RateLimit-Remaining, dan Retry-After dikirim. Nilai dapat diatur di konfigurasi.
CORS
Daftar asal yang diizinkan diatur di konfigurasi environment. API admin tidak membuka CORS untuk asal bebas.
Transport
HTTPS dan HSTS (9.11.1).
Validasi
Parameter filter dan urutan memakai daftar putih. Unggahan dibatasi jenis dan ukuran.
Konten
Semua HTML melewati sanitasi yang sama dengan halaman. API tidak mengembalikan JavaScript dari blok HTML kustom.
Cache
API publik memakai Cache-Control, ETag, dan Last-Modified, serta mendukung respons 304. Cache dibersihkan oleh observer model yang sama dengan sitemap dan cache halaman (9.16).
Perayapan
/api/ didaftarkan Disallow di robots.txt dan tidak masuk sitemap, mengikuti pola /go/ (9.11.3).
Privasi
Tidak ada data pengunjung yang keluar lewat API. Log permintaan memakai hash alamat, bukan IP mentah.
Pemantauan
Jumlah permintaan, galat 4xx dan 5xx, dan latensi dicatat per endpoint.

#### 8.12.9 Dokumentasi dan pengujian

- Spesifikasi OpenAPI 3.1 dihasilkan dari kode dan tersedia di /api/docs. Dokumentasi endpoint admin hanya terlihat oleh admin yang sedang login.
- Koleksi Postman atau berkas OpenAPI dapat diunduh untuk dipakai di n8n dan alat lain.
- Paket pembuat dokumentasi (kandidat: dedoc/scramble) wajib diverifikasi kompatibel dengan versi Laravel yang dipakai sebelum dipasang, sesuai prinsip 2.2.
- Setiap endpoint memiliki pengujian fitur: akses tanpa token, token salah, ability kurang, peran kurang, data valid, data tidak valid, dan batas laju.
- Pengujian kontrak mencocokkan respons dengan spesifikasi OpenAPI.
- Pengujian khusus: API publik tidak pernah mengembalikan draf, harga tersembunyi, atau tautan marketplace mentah.

#### 8.12.10 Di luar cakupan

- GraphQL.
- OAuth untuk pihak ketiga dan akun pengunjung.
- Perubahan pengaturan branding, pengguna, peran, redirect, dan iklan lewat API.
- Penulisan artikel lewat API (Fase 2).
- Webhook keluar (Fase 2): peristiwa product.published, product.price_changed, dan article.published, dengan tanda tangan HMAC dan percobaan ulang.
- Perjanjian tingkat layanan (SLA) untuk pihak luar.
- Sinkronisasi stok dan harga dari marketplace (tetap mengikuti bagian 5).

#### 8.12.11 Kriteria penerimaan

1. Seluruh endpoint di 8.12.5 dan 8.12.6 tersedia di /api/v1 dan sesuai spesifikasi OpenAPI.
2. API publik hanya mengembalikan konten terbit dan aktif.
3. Produk dengan harga disembunyikan tidak mengirim harga di API.
4. Tautan pembelian di API selalu berupa go_url, dan klik lewat go_url tercatat di click_events.
5. Token dapat dibuat, dicabut, dan kedaluwarsa dari Filament. Token dengan ability tulis tidak dapat dibuat tanpa konfirmasi password.
6. Permintaan dengan token yang abilitiy atau perannya kurang ditolak dengan 403, termasuk bila dikirim langsung ke server.
7. Perubahan lewat API menjalankan validasi, pipeline gambar, redirect 301, dan pembersihan cache yang sama dengan panel admin. Data yang gagal validasi ditolak seluruhnya, dan data yang lolos langsung terbit.
8. Permintaan POST dengan Idempotency-Key yang sama tidak membuat data ganda.
9. Respons melewati batas laju mengembalikan 429 dengan Retry-After.
10. Respons API publik mendukung ETag dan 304, dan latensi persentil ke-95 di bawah 300 ms untuk respons dari cache.
11. /api/ ada di Disallow robots.txt dan tidak ada di sitemap.
12. Pengaturan branding tidak dapat diubah lewat API.
Perubahan pada bagian lain PRD v0.11
Bagian
Perubahan
Header
Versi menjadi 0.12
1 Ringkasan
Tambahkan: "Supermaterial menyediakan REST API versi 1 untuk membaca katalog dan artikel, serta untuk otomatisasi pengelolaan produk dan pengambilan laporan klik."

### 2.3 Keputusan teknologi

Tambahkan baris: API memakai Laravel API Resources, Laravel Sanctum untuk token, dan OpenAPI 3.1 untuk dokumentasi
4 Tujuan produk
Tambahkan tujuan 8: membuka akses data katalog dan laporan klik bagi sistem lain melalui API
5 Bukan tujuan MVP
Tambahkan: GraphQL, OAuth pihak ketiga, penulisan artikel lewat API, webhook, dan SLA API untuk pihak luar
7 Ruang lingkup
Fase 1: API publik baca saja, manajemen token, API admin untuk produk, varian, media, tautan kanal, impor, dan laporan klik. Fase 2: penulisan artikel lewat API dan webhook

### 8.7 Panel admin

Tambahkan "Token API" (nama, ability, kedaluwarsa, terakhir dipakai, cabut) dan akses ke dokumentasi API

### 9.4 dan 9.11.3

Tambahkan /api/ ke daftar Disallow robots.txt, tanpa noindex, dan keluarkan dari sitemap
10 Model data
Tambahkan personal_access_tokens (bawaan Sanctum), idempotency_keys, dan import_jobs. Tabel audit_logs juga mencatat perubahan lewat API
12 Non-fungsional
Tambahkan baris API: latensi p95 di bawah 300 ms untuk respons cache, pembatasan laju, dan pengujian kontrak OpenAPI
14 Rencana implementasi
Tahap 2: Sanctum, token, dan kerangka API. Tahap 3: endpoint katalog, go_url, impor, dan laporan klik. Tahap 4: endpoint artikel, tag, penulis, dan pencarian. Tahap 5: cache dan ETag, pembersihan cache oleh observer. Tahap 6: pengujian fitur, kontrak, dan keamanan API
15 Risiko
Tambahkan: kebocoran token (hash, kedaluwarsa, pencabutan, konfirmasi password untuk token tulis) dan data draf atau harga tersembunyi bocor lewat API (pengujian khusus dan Resource yang sama dengan halaman)
Keputusan dan pertanyaan terbuka tambahan (bagian 16)
1. Ditetapkan: API hanya untuk pemakaian internal dan akan disambungkan ke n8n setelah website selesai. Token per mitra dan batas laju bertingkat tidak dikerjakan.
2. Apakah ada rencana aplikasi mobile atau situs headless dalam waktu dekat? Jika ada, endpoint pencarian, kategori, dan artikel perlu diprioritaskan sebelum endpoint admin.
3. Ditetapkan: data yang masuk lewat API langsung terbit di website, dengan validasi yang sama seperti panel admin.

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
- FAQPage bersifat opsional dan tidak dipakai untuk mengejar hasil kaya karena tampilan FAQ di Google berhenti sejak 7 Mei 2026 (lihat 9.12).
- `SearchAction` dan `HowTo` tidak dipakai (lihat 9.12.2).

Data terstruktur harus sesuai dengan konten yang terlihat oleh pengunjung. Penambahan JSON-LD tidak menjamin rich results atau peringkat tertentu. Aturan rinci properti, harga, varian, dan rating ada di bagian 9.10.2.

### 9.4 Sitemap dan robots.txt

- Sitemap XML dibuat otomatis.
- Sitemap dapat dipisahkan menurut jenis konten, termasuk peta situs gambar (9.10.1).
- Hanya URL kanonis yang layak diindeks dimasukkan.
- Sitemap diperbarui ketika konten berubah, dengan `lastmod` dari tanggal pembaruan bermakna (lihat 9.11.6).
- `robots.txt` mengatur akses perayapan untuk area yang relevan.
- Sitemap didaftarkan ke Google Search Console.

### 9.5 Tautan internal

Fase 1 mencakup:

- Breadcrumb.
- Produk terkait.
- Artikel terkait.
- Blok Lihat Juga dan Topik Terkait pada artikel.
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

### 9.10 Rencana SEO berbasis dokumentasi Google Search Central dan Open Graph

Bagian ini menurunkan dokumentasi resmi menjadi aturan yang dapat diimplementasikan dan diuji. Aturan di bagian 9.1 sampai 9.9 tetap berlaku, dan bagian ini memperinci SEO gambar, data terstruktur produk, serta Open Graph.

| Sumber | Dipakai untuk | Pembaruan terakhir sumber |
|---|---|---|
| Praktik terbaik SEO Google Gambar (Google Search Central) | SEO gambar (9.10.1) | 3 Maret 2026 |
| Product snippet structured data (Google Search Central) | Data terstruktur produk (9.10.2) | 8 September 2026 |
| The Open Graph protocol (ogp.me) | Metadata Open Graph (9.10.3) | - |

Dokumentasi diakses pada 22 Oktober 2026. Karena dapat berubah, tim memeriksa ulang ketiga sumber sebelum implementasi dan sebelum peluncuran.

#### 9.10.1 SEO gambar

1. **Semua gambar konten memakai elemen `<img>` dengan atribut `src`.** Google menemukan gambar dari atribut `src` pada `<img>` (termasuk yang berada di dalam `<picture>`) dan tidak mengindeks gambar CSS. Gambar produk, varian, thumbnail galeri, gambar artikel, dan kartu produk terkait tidak boleh dipasang lewat `background-image`. CSS background hanya dipakai untuk dekorasi.

   ```html
   <picture>
     <source type="image/avif" srcset="/img/rangka-baja-ringan-c75-1.avif">
     <source type="image/webp" srcset="/img/rangka-baja-ringan-c75-1.webp">
     <img src="/img/rangka-baja-ringan-c75-1.jpg"
          alt="Rangka baja ringan C75 tampak depan"
          width="800" height="800">
   </picture>
   ```

2. **Gambar responsif selalu memiliki `src` pengganti.** Saat memakai `srcset` atau `<picture>`, elemen `<img>` wajib memuat `src` karena sebagian browser dan crawler tidak memahami `srcset`.
3. **Format yang didukung:** BMP, GIF, JPEG, PNG, WebP, SVG, dan AVIF. Format utama WebP atau AVIF dengan JPEG atau PNG sebagai pengganti. Ekstensi file harus sesuai jenis file.
4. **Nama file deskriptif dan otomatis.** Saat unggah, sistem mengganti nama file menjadi `{slug-produk}-{varian}-{nomor}.{ext}`, huruf kecil dengan tanda hubung. Nama generik seperti `IMG00023.JPG`, `image1.jpg`, atau `1.jpg` tidak dipakai. Karakter non-Latin dienkode sesuai pedoman URL.
5. **Teks alternatif wajib dan deskriptif.**
   - Template awal: "{Nama produk} {varian}, tampak {sudut}", dapat disunting admin.
   - Memuat informasi yang berguna dan kata kunci yang relevan secara wajar. Penjejalan kata kunci dilarang karena berdampak buruk pada pengalaman pengguna dan dapat ditandai spam.
   - Batas panjang 125 karakter (standar internal).
   - Gambar dekoratif memakai `alt=""`.
   - Gambar yang menjadi tautan memakai alt sebagai teks tautan.
6. **Konteks halaman.** Gambar ditempatkan dekat teks yang relevan dan pada halaman yang relevan dengan isi gambar. Gambar artikel dilengkapi keterangan foto di dekat paragraf terkait.
7. **URL gambar yang konsisten.** Gambar yang sama selalu memakai URL yang sama di semua halaman (galeri, kartu produk terkait, hasil pencarian, artikel) agar dapat di-cache dan menghemat anggaran crawl. URL gambar tidak diberi parameter acak atau token yang berubah.
8. **Gambar pilihan per halaman.** Google memilih pratinjau gambar secara otomatis, dan pilihan dapat diarahkan lewat metadata:
   - `og:image`, dan
   - properti `image` pada JSON-LD (atau `primaryImageOfPage` pada `WebPage`).

   Gambar yang dipilih harus relevan dan mewakili halaman, beresolusi tinggi, tidak berrasio ekstrem (terlalu sempit atau terlalu lebar), serta bukan logo situs atau gambar bertulis. Produk memakai gambar utama produk, artikel memakai gambar utama artikel. Gambar Open Graph bawaan dari pengaturan branding (8.10) hanya menjadi cadangan untuk berbagi di media sosial dan tidak dimasukkan ke JSON-LD.
9. **Peta situs gambar.** Sistem membuat peta situs gambar otomatis (lewat queue) yang memuat seluruh gambar produk dan gambar utama artikel. Peta situs gambar boleh memuat URL dari domain lain pada `<image:loc>`, sehingga CDN dapat dipakai. Jika gambar dihosting di domain CDN, kepemilikan domain CDN diverifikasi di Search Console agar error crawl terlihat.
10. **Kecepatan dan kualitas.** Gambar tajam pada ukuran thumbnail, dikompresi dengan teknik terbaru, dan diuji dengan PageSpeed Insights. Gambar elemen LCP tidak memakai lazy loading (sesuai 9.7).
11. **Data URI** hanya untuk ikon kecil. Gambar produk dan artikel tidak disisipkan sebagai data URI karena memperbesar ukuran halaman.
12. **Standar resolusi minimum (internal).** Gambar produk sisi terpanjang minimal 800 piksel, gambar utama artikel dan gambar Open Graph minimal 1200 piksel lebar.

#### 9.10.2 Data terstruktur produk (Product snippet)

**Cakupan dan penempatan**

- Markup `Product` hanya dipasang pada halaman detail produk (satu produk atau variannya). Halaman kategori, hasil pencarian, dan daftar produk tidak diberi markup `Product`.
- JSON-LD ditulis di sisi server pada HTML awal (Blade), tidak dihasilkan lewat JavaScript. Markup yang dibuat JavaScript dapat membuat crawl lebih jarang dan kurang andal, padahal harga dan ketersediaan sering berubah.
- Nilai pada markup harus sama dengan yang tampil di halaman.
- Syarat minimum Google: `name` ditambah salah satu dari `review`, `aggregateRating`, atau `offers`.

**Properti yang dihasilkan**

| Properti | Status | Sumber di sistem | Aturan |
|---|---|---|---|
| `name` | Wajib | Nama produk | Sama dengan H1 |
| `image` | Disarankan | Galeri produk | Beberapa URL dengan rasio berbeda (1:1, 4:3, 16:9) bila tersedia, gambar utama di urutan pertama |
| `description` | Disarankan | Ringkasan produk | Teks asli, bukan salinan marketplace |
| `sku` | Disarankan | SKU varian atau produk | - |
| `mpn` | Opsional | Kolom MPN | Diisi jika ada |
| `brand` | Disarankan | Merek | Tipe `Brand` dengan `name` |
| `offers` | Disarankan | Harga dan ketersediaan | Satu `Offer` (lihat di bawah) |
| `aggregateRating`, `review` | Opsional | Data ulasan | Hanya jika memenuhi syarat di bawah |

**Aturan `Offer` dan harga**

- `price` berisi angka tanpa simbol dan tanpa pemisah ribuan (contoh: `28000`), dan `priceCurrency` selalu diisi `IDR`.
- `price` adalah harga jual saat ini. Harga coret tidak dimasukkan ke `price`.
- Untuk produk yang memiliki harga coret, dipakai `Offer`, bukan `AggregateOffer`, karena fitur penurunan harga (price drop) mensyaratkan `Offer`.
- `AggregateOffer` tidak dipakai untuk menggambarkan varian atau rentang harga varian.
- Penulisan harga coret dengan `priceSpecification` dan masa berlaku diskon mengikuti 9.14.3.
- `priceValidUntil` diisi dari tanggal akhir diskon, atau dari tanggal verifikasi harga ditambah masa berlaku yang diatur admin (bawaan 30 hari). Tanggal yang sudah lewat dapat membuat snippet tidak tampil. Pekerjaan terjadwal harian menghapus properti yang sudah lewat dan menandai produk untuk ditinjau.
- `availability` dikelola manual lewat kolom status ketersediaan (Tersedia, Stok terbatas, Pre-order, Habis) karena stok tidak disinkronkan pada MVP. Status dipetakan ke `InStock`, `LimitedAvailability`, `PreOrder`, dan `OutOfStock`. Jika admin tidak mengisi, properti `availability` tidak ditulis. Nilai `InStock` tidak dipakai sebagai default.
- Jika harga disembunyikan atau ditampilkan sebagai "hubungi kami", `offers` tidak dibuat.
- Satu mata uang, satu URL: karena hanya rupiah yang dipakai, tidak diperlukan URL terpisah per mata uang.

**Varian**

- Halaman dengan banyak varian memakai `ProductGroup` dengan pola halaman tunggal (single-page) sesuai panduan Varian Google. Rincian ada di 9.14.2.
- `?varian=` memiliki canonical ke URL produk (bagian 8.11.4.5). Markup memuat semua varian dan tidak berubah mengikuti pilihan pengunjung.

**Rating dan ulasan**

- Bersifat opsional dan hanya dibuat jika datanya nyata, tampil di halaman, dan memenuhi pedoman Cuplikan Ulasan Google.
- Nama pengulas pada `review` harus berupa nama `Person` atau `Team` yang valid, bukan kalimat promosi (contoh yang tidak dianjurkan: "Diskon 50% Black Friday").
- Data rekap dari marketplace tidak otomatis dimasukkan ke JSON-LD. Opsi markup rating bawaannya nonaktif dan hanya diaktifkan admin setelah memastikan datanya memenuhi pedoman.

**Pros and cons (kelebihan dan kekurangan)**

- Hanya untuk halaman ulasan produk editorial, bukan halaman produk toko atau ulasan pelanggan.
- Minimal dua pernyataan dari kombinasi `positiveNotes` dan `negativeNotes` (berupa `ItemList` dengan `ListItem` berisi `name` dan `position`).
- Disediakan pada template artikel "Ulasan produk" di Fase 2, dengan kolom Kelebihan dan Kekurangan di editor.

**Merchant listing**

- Tidak dipakai pada MVP karena pembelian berlangsung di marketplace. Dievaluasi lagi jika transaksi internal (Fase 3) disetujui.
- Laporan Search Console yang dipantau untuk halaman produk adalah laporan Product snippets.

#### 9.10.3 Open Graph

Setiap halaman publik menghasilkan tag Open Graph di `<head>`, dengan atribut `prefix="og: https://ogp.me/ns#"` pada elemen `<html>`.

| Properti | Status | Nilai |
|---|---|---|
| `og:title` | Wajib | Judul halaman (template atau isian manual) |
| `og:type` | Wajib | Lihat pemetaan di bawah |
| `og:image` | Wajib | Gambar pilihan halaman (9.10.1 poin 8) |
| `og:url` | Wajib | URL kanonis halaman |
| `og:description` | Disarankan | Satu sampai dua kalimat dari ringkasan halaman |
| `og:site_name` | Disarankan | Nama website dari pengaturan branding (8.10) |
| `og:locale` | Disarankan | `id_ID` (bawaan spesifikasi adalah `en_US`, sehingga harus diubah) |
| `og:image:width`, `og:image:height`, `og:image:type` | Disarankan | Dimensi dan tipe MIME gambar |
| `og:image:alt` | Wajib jika ada `og:image` | Teks alternatif gambar, bukan keterangan |
| `og:image:secure_url` | Opsional | URL HTTPS gambar |

Pemetaan `og:type`:

| Halaman | `og:type` |
|---|---|
| Beranda, kategori, daftar artikel, halaman tag, halaman statis | `website` |
| Produk | `website` (spesifikasi Open Graph tidak memiliki tipe global khusus produk, dan halaman tanpa tipe khusus diperlakukan sebagai `website`) |
| Artikel | `article` |

Properti tambahan untuk `article`:

- `article:published_time` dan `article:modified_time` dalam format ISO 8601. `modified_time` hanya berubah saat konten benar-benar berubah secara bermakna (sesuai 8.3).
- `article:author`, hanya jika halaman penulis tersedia.
- `article:section` diisi kategori artikel.
- `article:tag` diisi tag artikel, satu tag per elemen `<meta>`.

Aturan teknis:

- `og:url` sama dengan canonical dan tidak memuat parameter `?varian=`, filter, paginasi tambahan, atau UTM.
- Jika ada lebih dari satu gambar, setiap gambar ditulis sebagai tag `og:image` terpisah dan gambar utama ditaruh paling atas karena tag pertama diprioritaskan. Properti berstruktur (`width`, `height`, `alt`) ditulis tepat setelah tag `og:image` yang bersangkutan.
- Nilai berasal dari template dan dapat ditimpa manual (9.2). Setiap nama brand dibaca dari pengaturan branding.
- Twitter Card tetap dihasilkan sebagai pelengkap.

#### 9.10.4 Judul dan deskripsi

Google membuat link judul dan cuplikan otomatis dari `<title>`, tag `meta`, dan isi halaman. Template berikut dipakai bila kolom manual kosong (usulan, dapat disesuaikan):

| Halaman | Template judul | Deskripsi meta |
|---|---|---|
| Produk | `{Nama produk} \| {Merek} \| {Nama website}` | Ringkasan produk, maksimal 155 karakter |
| Kategori | `{Nama kategori}: Daftar Produk \| {Nama website}` | Teks pengantar kategori |
| Artikel | `{Judul artikel} \| {Nama website}` | Ringkasan artikel |

Pedoman: setiap halaman memiliki judul unik, tanpa penjejalan kata kunci, dan nama website selalu dibaca dari pengaturan branding. Hasil akhir yang tampil di Google dapat berbeda dari template karena Google dapat menulis ulang.

#### 9.10.5 Validasi, rilis bertahap, dan pemantauan

**Alur validasi (mengikuti dokumentasi Google)**

1. Tambahkan properti wajib dan rekomendasi pada template.
2. Pastikan sesuai pedoman umum data terstruktur, Search Essentials, dan panduan teknis dan konten Product snippet.
3. Validasi kode dengan Rich Results Test dan perbaiki seluruh error kritis. Peringatan non-kritis diperbaiki jika layak.
4. Rilis pada beberapa halaman contoh (misalnya 10 sampai 20 produk dan 5 artikel), uji dengan URL Inspection, pastikan halaman tidak diblokir `robots.txt`, `noindex`, atau login, lalu minta Google meng-crawl ulang.
5. Kirim sitemap dan otomatiskan lewat Search Console Sitemap API agar perubahan di masa depan terpantau.
6. Validasi tag Open Graph dengan Facebook Object Debugger.
7. Setelah halaman contoh dinyatakan baik, template dirilis ke seluruh produk dan artikel.

Perlu waktu beberapa hari sampai Google menemukan, meng-crawl, dan mengindeks ulang halaman, sehingga hasil rilis dinilai setelah jeda tersebut.

**Pemantauan berkelanjutan (Search Console)**

- Setelah pertama kali memasang data terstruktur: pantau laporan status hasil kaya (item valid naik, item tidak valid tidak naik) dan perbaiki dengan alur Inspect URL, perbaiki, lalu minta validasi.
- Setelah rilis template baru atau perubahan kode: pantau kenaikan item tidak valid (kemungkinan template bermasalah) dan penurunan item valid (kemungkinan markup hilang dari halaman).
- Berkala: analisis laporan Performance (tayangan, klik, posisi rata-rata) dan tarik data otomatis lewat Search Console API untuk dasbor internal.
- Laporan Product snippets dipantau untuk halaman produk, dan laporan terkait gambar serta peta situs dipantau untuk indeks gambar.

**Indikator kelengkapan SEO di admin (pengembangan dari 8.7)**

Setiap produk dan artikel memiliki skor kelengkapan berdasarkan pemeriksaan:

- Teks alternatif semua gambar terisi.
- Nama file gambar deskriptif.
- Gambar utama memenuhi resolusi minimum.
- Merek, SKU, dan harga terisi sesuai pengaturan tampilan harga.
- `priceValidUntil` berada di masa depan dan harga tidak kedaluwarsa.
- Status ketersediaan terisi.
- Gambar Open Graph tersedia dan bukan logo.
- Judul dan deskripsi meta terisi atau terhasilkan dari template.

Skor ini membantu kualitas data dan tidak menjamin tampilnya hasil kaya atau peringkat tertentu, karena Google tidak menjamin fitur data terstruktur akan muncul.

#### 9.10.6 Kriteria penerimaan SEO

1. Semua gambar produk, varian, galeri, dan artikel dirender dengan `<img src>`, dan tidak ada gambar konten yang dipasang lewat CSS background.
2. Setiap `<picture>` atau `srcset` memiliki `<img src>` pengganti, dan setiap gambar memiliki `alt` yang terisi (kecuali dekoratif) serta atribut `width` dan `height`.
3. Nama file gambar yang diunggah diganti otomatis menjadi nama deskriptif.
4. Peta situs gambar terbentuk otomatis dan terdaftar di Search Console, dan domain CDN (jika dipakai) sudah diverifikasi.
5. Halaman produk memuat JSON-LD `Product` pada HTML awal dengan `name`, `image`, dan `offers` yang cocok dengan harga yang tampil, serta `priceCurrency` bernilai `IDR`.
6. Halaman dengan harga coret memakai `Offer` (bukan `AggregateOffer`), dan `priceValidUntil` tidak pernah berisi tanggal yang sudah lewat.
7. Properti `availability` hanya ditulis bila admin mengisinya.
8. Halaman kategori, hasil pencarian, dan daftar produk tidak memuat markup `Product`.
9. Markup rating dan ulasan tidak muncul untuk data yang belum diaktifkan admin.
10. Setiap halaman memiliki `og:title`, `og:type`, `og:image`, `og:url`, `og:image:alt`, `og:site_name`, dan `og:locale` bernilai `id_ID`.
11. Halaman artikel memuat `article:published_time`, `article:modified_time`, `article:section`, dan `article:tag`.
12. Halaman contoh lolos Rich Results Test tanpa error kritis dan lolos pemeriksaan Facebook Object Debugger sebelum rilis penuh.
13. Laporan Product snippets dan status hasil kaya di Search Console dipantau setelah rilis dan setelah setiap perubahan template.

### 9.11 Spesifikasi teknis lanjutan berbasis Google Search Central

Bagian 9.11 sampai 9.20 memasukkan dokumen "Arsitektur Optimasi Mesin Pencari (SEO) Komprehensif Berbasis Google Search Central pada Ekosistem Laravel dan Filament" ke dalam PRD ini. Isinya disesuaikan dengan keputusan yang sudah ada (katalog tanpa checkout, satu bahasa, white label, dan pembelian di marketplace). Bagian yang tidak relevan tercatat di 9.20. Pada konflik dengan bagian sebelumnya, bagian 9.10 sampai 9.20 yang berlaku.

#### 9.11.1 Prasyarat arsitektur

- Halaman dirender di server (Blade), sehingga `<head>`, hierarki heading, dan JSON-LD sudah ada pada respons HTTP pertama tanpa menunggu JavaScript.
- Data terstruktur dibangun dari model Eloquent yang sama dengan yang dirender ke halaman, sehingga markup dan konten yang terlihat tidak berbeda.
- Seluruh situs memakai HTTPS dengan header HSTS.
- Setiap halaman memiliki tepat satu `<h1>` dengan sub-heading (`<h2>`, `<h3>`) yang berurutan, dan semua gambar bermakna memiliki teks alternatif.

#### 9.11.2 Pengindeksan prioritas seluler (mobile-first)

Google memakai versi seluler yang dirayapi Googlebot Smartphone untuk pengindeksan dan peringkat. Karena itu:

- Meta robots, struktur heading, dan data terstruktur harus sama pada tampilan desktop dan seluler. Tag `noindex` atau `nofollow` yang hanya ada di versi seluler membuat halaman gagal diindeks.
- Konten utama tidak boleh bergantung pada interaksi untuk muncul (menggeser, mengeklik, atau mengetik), karena Googlebot tidak melakukannya. Contohnya teks deskripsi, spesifikasi, FAQ, dan isi accordion tetap ada di HTML awal. Konten di bottom sheet varian mobile (8.11.10) juga harus tersedia di HTML.
- CSS, JavaScript, dan URL gambar (termasuk yang di CDN) tidak diblokir oleh `robots.txt`.
- Server memiliki kapasitas untuk laju perayapan versi seluler.

#### 9.11.3 Crawling versus pengindeksan

Dua kontrol ini dipisahkan secara tegas di kode:

| Kontrol | Fungsi | Alat |
|---|---|---|
| Perayapan | Mengatur halaman atau berkas yang boleh diminta crawler | `robots.txt` |
| Pengindeksan | Mencegah halaman masuk ke indeks | `<meta name="robots">` atau header `X-Robots-Tag` |
| Akses sama sekali | Membatasi akses crawler dan pengguna | Password atau autentikasi rute |

Aturan:

- `noindex` dan `X-Robots-Tag` hanya terbaca jika URL dapat dirayapi. URL yang di-Disallow di `robots.txt` tidak pernah dibaca meta atau header-nya, dan tetap dapat muncul di hasil Google jika ditautkan dari tempat lain.
- Aturan `noindex` di dalam `robots.txt` tidak didukung Google dan tidak dipakai.
- **Validasi silang di Filament:** halaman yang ditandai `noindex` tidak boleh masuk daftar Disallow. Sistem menolak penyimpanan yang melanggar aturan ini.
- Halaman hasil pencarian internal, draf pratinjau, dan halaman filter yang tidak layak indeks memakai `noindex` tanpa diblokir `robots.txt`.
- Rute pengalihan teknis seperti `/go/` dan `/ad/` bukan halaman yang ingin diindeks. Rute ini cukup di-Disallow di `robots.txt`, tanpa `noindex`, dan tidak masuk sitemap (menggantikan anjuran "gunakan pula kontrol pengindeksan" pada 8.6).
- Area admin (`/admin/*`) dilindungi autentikasi dan tidak masuk sitemap.

#### 9.11.4 Direktif robots dan cuplikan

Direktif diterapkan lewat dua jalur dengan efek setara: `<meta name="robots">` pada halaman HTML, dan header `X-Robots-Tag` (middleware Laravel) untuk PDF, gambar, dan endpoint non-HTML. Jika beberapa aturan digabung, Google menerapkan yang paling membatasi.

| Direktif | Penggunaan di sistem |
|---|---|
| `noindex`, `nofollow`, `none` | Opsi per halaman di editor SEO Filament |
| `max-snippet:-1` | Bawaan untuk artikel dan produk |
| `max-image-preview:large` | Bawaan untuk artikel dan produk (berlaku juga di Google Discover) |
| `max-video-preview:-1` | Bawaan untuk halaman yang memuat video |
| `nosnippet`, `max-snippet:0` | Hanya jika dipilih admin |
| `indexifembedded` | Opsional, untuk blok HTML kustom terisolasi (8.3) yang berada di halaman `noindex` |
| `notranslate` | Selektif, untuk halaman dokumen hukum atau nama merek |
| `noimageindex` | Opsional per halaman |
| `unavailable_after` | Terhubung ke kolom tanggal berakhir pada halaman kampanye berbatas waktu |
| `data-nosnippet` (atribut HTML) | Dipasang pada banner cookie, modal kampanye (8.9), slot iklan (8.8), disclaimer berulang, dan widget obrolan |

Aturan lama yang tidak dipakai lagi oleh Google, yaitu `noarchive`, `nocache`, dan `nositelinkssearchbox`, tidak disediakan sebagai opsi.

Penghapusan gambar dari Google Gambar (opsi "Sembunyikan dari Google Gambar" di pustaka media) memakai salah satu cara: header `X-Robots-Tag: noindex` pada rute penyajian gambar, atau aturan Disallow untuk `Googlebot-Image` di `robots.txt`.

Catatan: Googlebot Smartphone dan Desktop memakai token produk yang sama (`Googlebot`) di `robots.txt`, sehingga keduanya tidak dapat ditargetkan terpisah. Blokir sementara atau `noindex` yang dipakai selama pembangunan (staging) harus dihapus sepenuhnya saat peluncuran produksi.

#### 9.11.5 Kanonikalisasi, paginasi, dan kualifikasi tautan

- Setiap halaman yang valid memiliki `<link rel="canonical">` ke URL bersihnya sendiri (self-referencing), kecuali admin menetapkan kanonis kustom di editor SEO.
- Halaman katalog dengan parameter filter atau pengurutan memiliki canonical ke URL kategori induk tanpa parameter, kecuali kombinasi filter tersebut diberi status landing page SEO di Filament (kolom `is_seo_landing`).
- Paginasi memakai pola URL seragam di seluruh situs (contoh: `?page=2`). Setiap halaman paginasi memiliki canonical ke dirinya sendiri (halaman 2 tidak dikanonisasi ke halaman 1), serta `<title>` dan meta description unik yang memuat nomor halaman.
- Tidak boleh ada halaman yatim. Setiap URL penting dijangkau lewat elemen `<a href>` biasa dengan teks jangkar deskriptif.
- Editor teks kaya menyediakan opsi atribut `rel` untuk tautan keluar: `sponsored` (berbayar atau afiliasi), `ugc` (konten buatan pengguna), dan `nofollow`. Tautan ke toko resmi Jaya Intero tetap memakai aturan 8.4.

#### 9.11.6 Peta situs dinamis dan mode pemeliharaan

**Peta situs**

- Dibuat dari model Eloquent (produk, kategori, artikel, penulis, halaman statis) lewat antarmuka sitemap pada model, dengan `lastmod`, dan atribut gambar bila ada.
- `lastmod` memakai tanggal pembaruan bermakna (sesuai 8.3), bukan setiap perubahan `updated_at`, agar Google tidak menerima sinyal tanggal yang menyesatkan.
- Dipecah per jenis: `sitemap-products.xml`, `sitemap-categories.xml`, `sitemap-articles.xml`, `sitemap-pages.xml`, dan `sitemap-images.xml` (9.10.1), semua direferensikan di satu berkas indeks `sitemap.xml`. Batas jumlah URL per berkas diatur dan berkas dipecah otomatis menjadi bernomor jika melebihi batas.
- Dikecualikan: `/admin/*`, draf, permutasi filter, halaman `noindex`, dan rute teknis.
- Lokasi indeks dicantumkan di `robots.txt` lewat baris `Sitemap:`.
- Setiap publikasi, pembaruan, atau penghapusan konten publik memicu antrean yang meregenerasi pecahan sitemap terkait dan mengirim ulang sitemap ke Google lewat Search Console Sitemap API.

**Mode pemeliharaan atau jeda situs**

- Saat situs dijeda atau dipelihara, server mengembalikan HTTP **503** dengan header `Retry-After` (tanggal atau durasi), bukan 403, 404, 410, atau `noindex`.
- Halaman 503 berupa HTML statis ringan dengan CSS inline dan sumber daya luar minimal.
- Rute `/robots.txt` tetap mengembalikan **200 OK**. Respons 503 pada `robots.txt` memblokir seluruh perayapan.
- Direktif `Disallow: /` tidak dipakai, karena dapat menghapus konten bahkan URL situs dari Google Penelusuran.

### 9.12 Kebijakan data terstruktur dan deprekasi 2026

#### 9.12.1 Kebijakan umum

- JSON-LD adalah format yang dipakai, dibuat secara terprogram dari model Eloquent.
- Markup mewakili konten utama halaman dan sesuai kebijakan spam Google. Tidak ada markup untuk informasi yang tersembunyi dari pembaca: harga diskon, ulasan, atau penulis yang ada di JSON-LD juga tampil di HTML.
- Tidak ada ulasan palsu, konten berbatas waktu yang sudah kedaluwarsa, atau penyamaran kepemilikan.
- Markup ditempatkan di halaman yang dideskripsikannya, dengan jenis dan properti schema.org yang paling spesifik.
- Jika ada halaman duplikat untuk konten yang sama, markup yang sama ada di semua duplikat, bukan hanya di halaman kanonis.
- Entitas yang saling terkait di satu halaman dihubungkan dengan `@id` (contoh: `#shipping_policy`, `#return_policy`).
- URL gambar pada properti markup relevan dengan halaman, dapat dirayapi, dan dapat diindeks.
- Search Console dipantau untuk laporan Tindakan Manual. Tindakan manual membuat seluruh data terstruktur di halaman diabaikan.
- Data terstruktur tidak menjamin hasil kaya. Google dapat memilih tidak menampilkannya.

#### 9.12.2 Fitur yang tidak dipakai atau berubah

| Fitur | Status | Keputusan di sistem |
|---|---|---|
| Sitelinks Searchbox (`SearchAction`) | Dihentikan Google pada November 2024, sitelinks dibuat otomatis | Tidak diimplementasikan. Markup `WebSite` hanya berisi `name` dan `url` |
| `HowTo` | Dihentikan Google sejak September 2023 | Tidak dipakai untuk strategi tampilan SERP |
| Hasil kaya `FAQ` | Berhenti tampil di Google Penelusuran sejak 7 Mei 2026. Tipe `FAQPage` tetap valid dan markup yang sudah ada tidak perlu dihapus | Lihat 9.12.3 |

#### 9.12.3 FAQ

- Konten FAQ tetap ditampilkan terlihat di halaman produk, kategori, dan artikel karena bermanfaat bagi pembaca.
- JSON-LD `FAQPage` bersifat opsional dan hanya dibuat jika pertanyaan dan jawabannya juga tampil di halaman. Markup ini tidak lagi dipakai untuk mengejar tampilan hasil kaya di Google, tetapi tetap dapat dibaca mesin pencari lain dan sistem pencari generatif.
- Fase 2: kolom `short_answer` (ringkasan jawaban singkat) dan `key_facts` (daftar fakta kunci) pada artikel, serta endpoint `/api/answers/{slug}` yang mengekspor judul, ringkasan, fakta kunci, dan tautan sumber. Manfaatnya bagi kutipan oleh mesin AI belum terjamin dan dievaluasi dari data setelah MVP.
- Laporan Search Console untuk FAQ tidak dijadikan indikator keberhasilan.

### 9.13 Artikel, penulis, dan gambar multi-rasio

#### 9.13.1 Data terstruktur artikel

Jenis dipilih admin: `BlogPosting` (bawaan), `Article`, atau `NewsArticle`. Google tidak menetapkan properti wajib mutlak untuk jenis ini, sehingga properti rekomendasi yang relevan diisi sebanyak mungkin.

| Kolom | Properti JSON-LD | Aturan |
|---|---|---|
| `schema_type` | `@type` | Pilihan terbatas pada tiga jenis di atas |
| `headline` | `headline` | Wajib, maksimal 110 karakter agar tidak terpotong di perangkat seluler |
| Gambar utama | `image` | Larik beberapa URL (lihat 9.13.3) |
| `published_at` | `datePublished` | ISO 8601 beserta zona waktu, contoh `2026-10-22T10:22:00+07:00` |
| Tanggal pembaruan bermakna | `dateModified` | ISO 8601 beserta zona waktu |
| Penulis | `author` | Lihat 9.13.2 |
| Branding situs | `publisher` | `Organization` dengan nama dan logo dari pengaturan branding (8.10) |

Konten berlangganan atau paywall tidak ada pada MVP sehingga markup `isAccessibleForFree` tidak dibuat.

#### 9.13.2 Penulis dan halaman profil

- Seluruh penulis yang tampil di halaman dimasukkan ke JSON-LD sebagai larik, bertipe `Person` (individu) atau `Organization`.
- `author.name` hanya berisi nama murni. Empat hal dipisahkan dan dilarang masuk ke `name`:

| Informasi | Properti yang dipakai |
|---|---|
| Nama penerbit | `publisher` |
| Jabatan | `author.jobTitle` |
| Gelar awalan atau akhiran | `author.honorificPrefix`, `author.honorificSuffix` |
| Kata pengantar redaksional ("posted by", "ditulis oleh") | Dihapus |

- Validasi di Filament dan aksesor model menolak atau memisahkan gelar, jabatan, dan kata pengantar dari kolom nama.
- Setiap penulis memiliki `author.url` ke halaman profil internal `/penulis/{slug}`, serta `sameAs` untuk profil eksternal (media sosial, dan lainnya).
- Halaman profil penulis diberi markup `ProfilePage` dengan `dateCreated`, `dateModified` (ISO 8601), dan `mainEntity` bertipe `Person`. Halaman ini masuk ke MVP dalam bentuk sederhana (nama, foto, bio singkat, dan daftar artikel).

Tabel `authors`:

| Kolom | Tipe | Properti target |
|---|---|---|
| `name` | VARCHAR(150) | `author.name` |
| `honorific_prefix` | VARCHAR(50) | `author.honorificPrefix` |
| `honorific_suffix` | VARCHAR(50) | `author.honorificSuffix` |
| `job_title` | VARCHAR(100) | `author.jobTitle` |
| `slug` | VARCHAR | `author.url` |
| `same_as` | JSON | `author.sameAs` |

#### 9.13.3 Gambar artikel dan produk multi-rasio

- Properti `image` memuat gambar yang mewakili konten, bukan logo atau teks keterangan, dalam format yang didukung Google Images, dan dapat dirayapi.
- Untuk hasil terbaik di Google Penelusuran dan Discover, disediakan tiga rasio aspek: **16:9**, **4:3**, dan **1:1**.
- Setiap gambar memiliki lebar kali tinggi minimal **50.000 piksel**. Unggahan di bawah batas ini ditolak oleh formulir Filament. Standar resolusi internal pada 9.10.1 poin 12 tetap berlaku di atas batas minimum ini.
- Saat unggah, sistem membuat tiga turunan otomatis: 1200 x 675 (16:9), 1200 x 900 (4:3), dan 1200 x 1200 (1:1), lalu memasukkan ketiga URL ke larik `image` pada JSON-LD.
- Aturan ini berlaku untuk gambar utama artikel dan gambar utama produk.

### 9.14 Produk lanjutan: varian, harga diskon, pengiriman, dan pengembalian

#### 9.14.1 Mode halaman produk

Setiap produk memiliki pengaturan mode markup:

| Mode | Untuk | Status |
|---|---|---|
| `product_snippet` | Halaman yang tidak memungkinkan pembelian langsung | **Bawaan MVP**, karena pembelian berlangsung di marketplace |
| `merchant_listing` | Halaman tempat pelanggan membeli langsung dari penjual | Disiapkan, tidak diaktifkan pada MVP. Diaktifkan jika transaksi internal (Fase 3) disetujui |

#### 9.14.2 Varian (`ProductGroup`)

Keputusan: halaman produk dengan banyak varian memakai pola **halaman tunggal (single-page)** dari dokumentasi Varian Google, karena seluruh varian berada di satu URL (`?varian=`).

- Satu `ProductGroup` mengelompokkan seluruh varian, dengan properti `productGroupID` (SKU induk), `variesBy` (properti pembeda, misalnya ukuran, ketebalan, atau warna), dan `hasVariant`.
- Setiap varian adalah `Product` dengan `sku`, `image`, dan `Offer` miliknya sendiri.
- Properti umum (merek, bahan, deskripsi) diletakkan di tingkat `ProductGroup`.
- Markup di halaman tidak berubah mengikuti varian yang dipilih pengunjung. Semua varian selalu ada di markup.
- `AggregateOffer` tidak dipakai untuk varian.
- Karena `?varian=` berkanonis ke URL produk, tidak diperlukan pola multi-halaman.
- Hasil divalidasi pada laporan Product snippets di Search Console dan Rich Results Test.

#### 9.14.3 Harga diskon dan masa berlaku

- Harga aktif dan harga coret dipisah memakai `priceSpecification` bertipe `UnitPriceSpecification`: harga aktif sebagai `price` dan `priceCurrency` (`IDR`), sedangkan harga coret ditandai `"priceType": "https://schema.org/StrikethroughPrice"`.
- Pengaturan ini mengikuti dokumentasi Listingan Penjual. Perilakunya untuk mode `product_snippet` diverifikasi dengan Rich Results Test sebelum diaktifkan, dan `Offer` dengan `price` harga jual tetap menjadi dasar markup.
- Lencana penurunan harga (price drop) dihitung Google dari perubahan harga yang diamati dari waktu ke waktu. Sistem tidak dapat memaksanya muncul.
- Harga per satuan ukuran (jika ada) memakai `referenceQuantity` bertipe `QuantitativeValue` (`value`, `unitCode`, `valueReference`).
- Periode diskon memakai `validFrom` dan `validThrough` atau `priceValidUntil` dalam ISO 8601 beserta zona waktu situs (bawaan WIB, +07:00).
- Validasi di Filament: saat harga coret diisi, tanggal mulai dan berakhir wajib diisi, dan tanggal mulai harus lebih awal dari atau sama dengan tanggal berakhir.

#### 9.14.4 Properti penawaran lain

| Properti | Aturan |
|---|---|
| `availability` | Satu nilai tunggal dari enumerasi schema.org (`InStock`, `OutOfStock`, `SoldOut`, `BackOrder`, dan lainnya). Dikelola manual dan hanya ditulis bila diisi (9.10.2) |
| `itemCondition` | `https://schema.org/NewCondition` untuk bahan bangunan baru |
| `shippingDetails` (`OfferShippingDetails`) | Hanya pada mode `merchant_listing`. Memuat `shippingRate` (`MonetaryAmount`, nilai `0` untuk gratis ongkir) dan `shippingDestination` (`DefinedRegion`). Didefinisikan sekali dan dirujuk dengan `@id` `#shipping_policy` |
| `hasMerchantReturnPolicy` (`MerchantReturnPolicy`) | Hanya pada mode `merchant_listing`. Didefinisikan sekali dan dirujuk dengan `@id` `#return_policy` |
| `review`, `aggregateRating`, `positiveNotes`, `negativeNotes` | Mengikuti 9.10.2. Hanya dari ulasan nyata yang terlihat di halaman |

Ekspor umpan data Google Merchant Center terjadwal tidak masuk MVP dan dievaluasi di Fase 3 bersama mode `merchant_listing`.

### 9.15 Skema pendukung

#### 9.15.1 BreadcrumbList

- Mewakili jalur navigasi yang lazim dilalui pengguna menuju halaman, bukan sekadar salinan struktur direktori URL.
- Minimal dua `ListItem` berurutan. Tingkat teratas (domain) dan halaman itu sendiri tidak wajib disertakan.
- Setiap `ListItem` memuat `position`, `name`, dan `item` (URL). Pada item terakhir `item` boleh dihilangkan, dan Google memakai URL halaman yang memuatnya.
- Markup `data-vocabulary.org` tidak dipakai.
- Dihasilkan oleh satu komponen Blade yang dipakai di halaman produk, kategori, dan artikel, dengan breadcrumb yang sama pada tampilan dan JSON-LD.

#### 9.15.2 Organization dan WebSite

- `Organization` dipasang di seluruh situs (tata letak utama) dengan `name`, `url`, dan `logo` yang valid. Seluruh nilainya dibaca dari pengaturan branding (8.10).
- `WebSite` memuat `name` dan `url` tanpa `SearchAction`.

#### 9.15.3 LocalBusiness (opsional, Fase 2)

Dipakai pada halaman kontak atau cabang jika ada alamat fisik. Pengelolaan `openingHoursSpecification` di Filament mengikuti empat aturan pemformatan:

| Kasus | Aturan |
|---|---|
| Jam buka melewati tengah malam | Satu objek pada hari mulai, contoh `"dayOfWeek": "Saturday"`, `"opens": "18:00"`, `"closes": "03:00"` |
| Buka 24 jam | `"opens": "00:00"`, `"closes": "23:59"` |
| Tutup sepanjang hari | `"opens": "00:00"`, `"closes": "00:00"` |
| Jam musiman atau libur | Tambahkan `validFrom` dan `validThrough` (format `YYYY-MM-DD`) dengan `"opens": "00:00"` dan `"closes": "00:00"` |

Properti rekomendasi lain: `priceRange`, `department`, dan `aggregateRating` (hanya jika situs menampilkan ulasan tentang bisnis lokal sesuai pedoman cuplikan ulasan).

### 9.16 Otomatisasi di panel admin Filament

- Setiap Resource dengan rute publik (produk, kategori, artikel, penulis, halaman statis) menyematkan komponen editor SEO polimorfik yang terhubung ke tabel SEO terpusat: judul, deskripsi, Open Graph, canonical, dan direktif robots.
- **Nilai bawaan otomatis (fallback):** jika judul SEO kosong, model menyusun `<title>` unik dengan nama entitas di depan dan nama brand di belakang. Ribuan produk dan artikel langsung memiliki metadata unik sejak hari pertama. Perubahan nama produk atau judul artikel memperbarui `<title>` dan JSON-LD di semua halaman terkait.
- **Observer siklus hidup model:**
  - Slug berubah pada halaman terbit: catat redirect 301 dari URL lama ke URL baru, cegah rantai dan loop (9.1), dan bersihkan cache halaman.
  - Rekaman publik disimpan, diubah, atau dihapus: antrean meregenerasi pecahan sitemap terkait dan memberi tahu Google lewat Search Console Sitemap API.
- Validasi silang `noindex` dan `robots.txt` (9.11.3) dan aturan nama penulis (9.13.2) ditegakkan di backend.

### 9.17 Kandidat paket dan penyesuaian arsitektur

Paket berikut disebut sebagai kandidat. Sesuai prinsip 2.2 (poin 3 dan 4), setiap paket wajib diverifikasi keberadaan dan kompatibilitasnya dengan Laravel 12 dan Filament 5 di Packagist dan dokumentasi resmi sebelum dipasang. Jika tidak kompatibel, komponen dibangun sendiri.

| Kebutuhan | Kandidat paket |
|---|---|
| Editor SEO di Filament | `ralphjsmit/laravel-filament-seo` dan `ralphjsmit/laravel-seo`, atau `rankbeam/laravel-seo-filament`, atau `nomanur/filament-seo-pro` |
| JSON-LD terprogram | `spatie/schema-org` |
| Tag kepala terpusat | `artesaos/seotools` |
| Sitemap | `spatie/laravel-sitemap` |
| Slug | `spatie/laravel-sluggable` |
| Redirect | `spatie/laravel-missing-page-redirector`, atau tabel `redirects` buatan sendiri (sudah ada di model data) |
| Media dan konversi gambar | `spatie/laravel-medialibrary` dan `intervention/image` |
| Performa | Cache respons (`spatie/laravel-responsecache`) dan Redis. `laravel/octane` bersifat opsional, dipertimbangkan sesuai hosting |

### 9.18 Kriteria penerimaan SEO tambahan

| ID | Area | Skenario | Kriteria lulus |
|---|---|---|---|
| SEO-01 | Gambar multi-rasio | Editor mengunggah gambar utama artikel atau produk | Gambar dengan lebar x tinggi di bawah 50.000 piksel ditolak. Gambar valid menghasilkan tiga turunan (16:9, 4:3, 1:1) yang masuk larik `image` pada JSON-LD dan dapat dirayapi |
| SEO-02 | Kemurnian `author.name` | Editor mengisi penulis dengan gelar dan jabatan | `author.name` hanya berisi nama. Gelar, jabatan, dan penerbit ada di properti masing-masing. `author.url` mengarah ke halaman `ProfilePage` |
| SEO-03 | `noindex` dan `robots.txt` | Halaman ditandai `noindex` di Filament | URL tidak ada di daftar Disallow. Meta atau header `noindex` terbaca di URL Inspection. Penyimpanan yang melanggar ditolak |
| SEO-04 | Diskon dan varian | Produk bervarian sedang diskon, diuji di Rich Results Test | `ProductGroup` memuat `hasVariant`, `variesBy`, dan `productGroupID`. Harga coret dan masa berlaku berformat ISO 8601 ber-zona waktu. `availability` bernilai tunggal. Tidak ada error kritis |
| SEO-05 | Breadcrumb | JSON-LD diekstraksi dari halaman | Minimal dua `ListItem` berurutan tanpa `data-vocabulary.org`. Item terakhir tidak wajib memiliki `item`. Sesuai jalur yang tampil |
| SEO-06 | Deprekasi | Kode sumber seluruh halaman diaudit | Tidak ada `SearchAction`, `nositelinkssearchbox`, atau `HowTo` untuk tampilan SERP. `FAQPage` hanya ada bila teks tanya jawab terlihat di halaman |
| SEO-07 | Slug dan sitemap | Editor mengubah slug artikel terbit | URL lama merespons 301 ke URL baru. Sitemap memecah per jenis konten (termasuk gambar), terdaftar di `robots.txt`, dan terkirim ke Google lewat API |
| SEO-08 | Mode pemeliharaan | Aplikasi dijeda, perayap mengakses halaman produk dan `/robots.txt` | Halaman mengembalikan 503 dengan `Retry-After` berupa HTML statis. `/robots.txt` mengembalikan 200 tanpa `Disallow: /` |
| SEO-09 | Parity seluler | Halaman dirender sebagai Googlebot Smartphone | Meta robots, heading, dan JSON-LD sama dengan desktop. Deskripsi, spesifikasi, FAQ, dan konten varian tersedia di HTML awal tanpa interaksi |
| SEO-10 | Kanonis | Katalog dibuka dengan filter, pengurutan, dan paginasi | Halaman filter berkanonis ke kategori induk (kecuali landing page SEO). Setiap halaman paginasi berkanonis ke dirinya sendiri dengan judul dan deskripsi unik |

Sebelum peluncuran, seluruh kode dari templat Blade divalidasi dengan Rich Results Test dan Schema Markup Validator, dan error kritis harus nol. Peringatan non-kritis diperbaiki bila layak. Setelah rilis, tim memeriksa halaman dengan URL Inspection (termasuk tangkapan layar render dan sumber daya yang dimuat), memantau laporan Data Terstruktur yang Tidak Dapat Diurai, dan memantau versi kanonis pilihan Google (Google-selected canonical), yang hanya terlihat pada data yang sudah terindeks dan tidak dapat diprediksi lewat pengujian langsung. Proses perayapan ulang dan pengindeksan ulang memerlukan waktu beberapa hari hingga dua minggu.

### 9.19 Urutan penerapan SEO pada tahap implementasi

| Tahap PRD | Pekerjaan SEO |
|---|---|
| Tahap 2 | Editor SEO polimorfik dan fallback otomatis, middleware `X-Robots-Tag`, handler mode pemeliharaan 503 (dengan `/robots.txt` tetap 200), kanonikalisasi filter dan paginasi, tabel `authors` |
| Tahap 3 dan 4 | Builder JSON-LD (`Product`, `ProductGroup`, `Article`, `ProfilePage`, `BreadcrumbList`), konversi gambar tiga rasio, validasi `author.name`, dan halaman penulis |
| Tahap 5 | Sitemap terpecah beserta pengiriman lewat API, observer redirect dan cache, `Organization`, serta validasi dengan Rich Results Test |
| Tahap 6 | Pengujian SEO-01 sampai SEO-10, URL Inspection, dan penghapusan blokir staging sebelum produksi |
| Fase 2 | `LocalBusiness`, `short_answer`, `key_facts`, `/api/answers/{slug}`, dan template ulasan produk dengan kelebihan dan kekurangan |
| Fase 3 | Mode `merchant_listing`, pengiriman dan pengembalian, serta umpan Google Merchant Center |

### 9.20 Bagian dokumen sumber yang tidak diadopsi

| Bagian | Alasan |
|---|---|
| Skema `SoftwareApplication` dan `Dataset` | Tidak relevan untuk katalog bahan bangunan |
| Markup konten berlangganan (paywall) | Tidak ada konten berbayar |
| Paket lokalisasi dan `hreflang` | Konten hanya satu bahasa (9.9) |
| Pengiriman perubahan lewat WebSub | Tidak termasuk MVP. Pembaruan sitemap memakai Search Console Sitemap API |
| `SearchAction` dan `HowTo` | Dihentikan Google (9.12.2) |
| `laravel/octane` sebagai kewajiban | Dijadikan opsional, bergantung pada hosting (9.17) |
| Pembagian pekerjaan dalam tiga sprint | Digantikan pembagian tahap implementasi PRD (9.19) |

## 10. Model data

Struktur awal database terdiri dari tabel berikut.

| Tabel | Fungsi |
|---|---|
| `categories` | Kategori bertingkat, deskripsi, SEO, dan status |
| `brands` | Merek produk |
| `products` | Data utama produk, metadata SEO, MPN, status ketersediaan, dan tanggal berlaku harga |
| `product_variants` | Variasi ukuran, SKU, dan harga acuan |
| `product_media` | Galeri gambar dan video, teks alternatif, dimensi, nama file deskriptif, dan poster video (kolom di 8.11.6.1) |
| `sales_channels` | Daftar kanal pembelian |
| `product_links` | Tautan produk ke kanal pembelian |
| `articles` | Konten artikel, keterangan dan kredit foto, dateline, penulis, editor, inisial, dan jumlah kunjungan |
| `article_product` | Relasi artikel dan produk |
| `article_category` | Relasi artikel dan kategori |
| `article_related` | Relasi artikel ke artikel lain untuk blok Lihat Juga dan Artikel Terkait |
| `tags` | Tag konten |
| `taggables` | Relasi polimorfik tag |
| `faqs` | FAQ produk, kategori, atau artikel |
| `click_events` | Riwayat klik pembelian |
| `ads` | Iklan artikel: slot, format, materi, tautan, jadwal, penargetan, dan prioritas |
| `ad_events` | Impresi dan klik iklan |
| `campaigns` | Banner kampanye modal: konten, jadwal, penargetan, frekuensi, dan prioritas |
| `campaign_events` | Impresi, klik CTA, dan penutupan banner |
| `redirects` | Pengalihan URL |
| `settings` | Konfigurasi website, termasuk nama, palet warna, logo, dan identitas brand |
| `setting_versions` | Riwayat versi pengaturan branding untuk pemulihan |
| `audit_logs` | Catatan perubahan pengaturan penting beserta pelaku dan hasil konfirmasi |
| `users` | Pengguna panel admin |
| `authors` | Penulis: nama, gelar, jabatan, slug halaman profil, dan tautan sameAs (9.13.2) |
| `seo_meta` | Metadata SEO polimorfik: judul, deskripsi, Open Graph, canonical, direktif robots, dan penanda landing page SEO (9.16) |

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
| Keamanan | Autentikasi admin, pembatasan percobaan login, validasi input, konfirmasi password ulang untuk perubahan branding besar, sanitasi konten editor, pembatasan akses berbasis peran, dan validasi tujuan redirect untuk mencegah open redirect. |
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
- Buat pengaturan website dan branding (nama, palet warna, logo, konfirmasi password, dan riwayat versi).
- Siapkan layout publik dan panel admin dengan warna dan nama dari pengaturan, bukan tertanam di kode.
- Tambahkan pengujian fondasi aplikasi.
- Pasang fondasi SEO teknis sesuai 9.19 (editor SEO, middleware `X-Robots-Tag`, mode pemeliharaan 503).

### Tahap 3: Katalog dan kanal pembelian

- Implementasikan kategori, merek, produk, varian, dan galeri.
- Buat pengelolaan tautan kanal pembelian.
- Implementasikan halaman publik produk dan kategori.
- Buat rute redirect `/go/`.
- Implementasikan pencatatan klik server.

### Tahap 4: Sistem konten

- Implementasikan artikel, tag, dan FAQ.
- Implementasikan iklan artikel dan banner kampanye modal.
- Buat relasi artikel dengan produk dan kategori.
- Buat halaman statis.
- Tambahkan pencarian internal dan navigasi.

### Tahap 5: SEO dan pengukuran

- Implementasikan metadata dan canonical.
- Tambahkan JSON-LD produk, artikel, dan breadcrumb sesuai 9.10.2.
- Tambahkan tag Open Graph (9.10.3) dan peta situs gambar (9.10.1).
- Terapkan nama file otomatis, aturan teks alternatif, dan indikator kelengkapan SEO.
- Buat sitemap terpecah dan `robots.txt`, serta kirim sitemap lewat Search Console Sitemap API (9.11.6).
- Implementasikan redirect 301.
- Integrasikan GA4.
- Optimalkan gambar, cache, dan performa.

### Tahap 6: Pengujian dan peluncuran

- Uji seluruh rute publik dan admin.
- Uji akses berbasis peran.
- Uji redirect dan pencatatan klik.
- Uji sitemap dan structured data dengan Rich Results Test, URL Inspection, dan Facebook Object Debugger.
- Rilis bertahap pada halaman contoh sebelum seluruh produk dan artikel (9.10.5).
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
| Markup produk tidak sesuai konten yang tampil, atau harga kedaluwarsa pada JSON-LD | Markup dibuat dari data yang sama dengan tampilan, pekerjaan harian memeriksa tanggal berlaku harga, dan pemantauan laporan Search Console |
| Gambar tidak terindeks karena dipasang lewat CSS atau tanpa teks alternatif | Aturan `<img src>`, validasi alt, dan indikator kelengkapan SEO |
| Blok HTML kustom disalahgunakan untuk menyisipkan skrip berbahaya | Sanitasi allow-list, iframe sandbox untuk JavaScript, pembatasan peran, CSP, dan log perubahan |
| Database atau media hilang | Backup otomatis dan pengujian pemulihan |
| Fitur melebar sebelum MVP selesai | Prioritas fitur berdasarkan ruang lingkup fase 1 |
| Perubahan nama atau warna yang keliru memengaruhi brand dan SEO | Konfirmasi password, pratinjau sebelum diterapkan, riwayat versi, dan pemulihan |

## 16. Keputusan dan pertanyaan terbuka

### 16.1 Keputusan yang sudah ditetapkan

| No | Topik | Keputusan | Dampak pada PRD |
|---|---|---|---|
| 1 | Cakupan katalog | Katalog memuat produk dari berbagai merek. Pembelian dilakukan melalui toko resmi Jaya Intero di marketplace (Shopee, Tokopedia, dan kanal lain). | Entitas merek bersifat jamak dan filter merek tetap berlaku (bagian 8.1). Jaya Intero berperan sebagai penjual pada semua kanal. |
| 2 | Jenis tautan marketplace | Seluruh tautan adalah tautan toko resmi Jaya Intero, bukan tautan afiliasi. | Atribut `rel` pada tautan keluar disesuaikan (bagian 8.4). Admin hanya dapat memasukkan tautan toko resmi. |
| 7 | Target wilayah | Nasional. | Konten, kata kunci, dan informasi pengiriman tidak dibatasi pada Sumatera Utara dan sekitarnya. |
| 9 | Impor produk | Impor produk CSV atau Excel beserta tautan kanal tersedia pada MVP. Impor massal lanjutan dikerjakan di Fase 2. | Impor dasar masuk Tahap 6 (impor produk awal). Pengembangan lanjutan tetap pada Fase 2. |
| 10 | Pola markup varian | `ProductGroup` dengan pola halaman tunggal, karena semua varian berada di satu URL. | Bagian 9.14.2. |

### 16.2 Pertanyaan yang masih terbuka

Keputusan berikut masih perlu dikonfirmasi sebelum implementasi fitur terkait:

1. Apakah harga produk ditampilkan, disembunyikan, atau ditampilkan sebagai rentang? (Nomor 3)
2. Berapa jumlah produk saat peluncuran, dan apakah data awal berasal dari Excel, marketplace, atau pemasok? (Nomor 4)
3. Apakah nomor WhatsApp hanya satu atau berbeda menurut kategori dan produk? (Nomor 5)
4. Siapa yang bertanggung jawab menulis dan meninjau artikel? (Nomor 6)
5. Apa indikator yang menentukan kelayakan pengembangan checkout dan payment gateway? (Nomor 8)
6. Apakah white label dipakai untuk satu brand per instalasi (asumsi MVP) atau perlu mode multi-brand dalam satu instalasi? Jika multi-brand, bagian 8.10 dan model data perlu dirancang ulang.