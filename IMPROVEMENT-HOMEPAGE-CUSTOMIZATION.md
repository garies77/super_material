# Instruksi Tugas: Kustomisasi Halaman Beranda (Homepage Command Center & Modular Builder)

Dokumen ini adalah spesifikasi kebutuhan dan instruksi fungsional terperinci untuk **Claude Code**. Pelajari arsitektur proyek, pahami kebutuhan klien atas fleksibilitas halaman beranda, dan rancang solusi implementasi kode terbaik secara mandiri tanpa terikat pada cuplikan kode kaku.

---

## 1. Ringkasan Tugas & Latar Belakang

Halaman beranda (`/` / `home.blade.php`) adalah etalase komersial terpenting situs Supermaterial. Saat ini susunan beranda bersifat statis (*hardcoded*) dan konfigurasinya tersebar terpisah:
* Teks Hero (H1, kata aksen, deskripsi, placeholder pencarian, dan badge jaminan) terkunci di template Blade.
* Banner kampanye hero berada terpisah di menu `SiteSettings`.
* Produk beranda terkunci pada query statis `is_featured` sebanyak 8 item di `HomeController`. Klien tidak bisa memilih produk tertentu (*manual curate*), tidak bisa menampilkan produk per kategori, dan tidak bisa menampilkan produk berdasarkan popularitas.
* Seksi artikel terkunci pada 3 artikel terbaru (`latest('published_at')`). Klien tidak bisa menampilkan artikel terpopuler (*most viewed*) maupun artikel pilihan redaksi.
* Tata letak (urutan seksi dan tombol hidup/mati seksi) tidak bisa diubah tanpa memodifikasi kode Blade.

**Tujuan Implementasi:**
Satukan seluruh kontrol halaman beranda ke dalam satu menu terpadu di Filament: **"Kustomisasi Beranda" (Homepage Customizer)**, yang memberikan kebebasan penuh kepada klien untuk:
1. Mengatur tata letak (urutan dan visibilitas setiap seksi).
2. Mengubah teks dan visual pada area Hero & Banner Kampanye.
3. Mengatur produk yang muncul (Kurasi manual, Berdasarkan Kategori, Otomatis Terpopuler, Otomatis Produk Unggulan, atau Otomatis Terbaru) serta mendukung pembuatan lebih dari 1 blok produk.
4. Mengatur artikel yang muncul berdasarkan kriteria sortir (Terbaru vs Terpopuler berdasarkan `views_count` vs Pilihan Manual).
5. Menjamin *backward compatibility* 100% sehingga tampilan beranda yang ada saat ini tetap utuh saat fitur pertama kali dijalankan.

---

## 2. Analisis Fitur yang Terdampak & Strategi Konsolidasi

Beberapa modul sistem saling beririsan dan harus dikonsolidasikan secara harmonis:

1. **`SiteSettings` (Pengaturan Situs):**
   * *Kondisi Lama:* Menampung grup *Hero & Banner Kampanye Beranda* (`hero_banner_*`).
   * *Perubahan:* Satukan kontrol Hero Banner ke dalam menu baru **Kustomisasi Beranda**. Di `SiteSettings`, berikan catatan/tautan ramah bahwa pengaturan visual beranda kini dipusatkan di menu Kustomisasi Beranda, atau sinkronkan nilainya secara transparan agar data lama tidak hilang.
2. **Modul Produk (`Product` & `ProductResource`):**
   * *Perubahan:* Kolom `is_featured` tetap berfungsi global, namun beranda tidak lagi tergantung kaku padanya. Klien bisa membuat blok kurasi manual produk tertentu (menggunakan searchable multi-select picker) atau blok berbasis interaksi/popularitas (`click_events`).
3. **Modul Artikel (`Article` & `ArticleResource`):**
   * *Perubahan:* Manfaatkan kolom `views_count` dan `published_at` untuk mendukung pilihan sortir dinamis: *Paling Populer* vs *Terbaru* vs *Kurasi Manual*.
4. **Modul Kampanye / Ads (`Ad` & `AdResource`):**
   * *Perubahan:* Sediakan opsi pada blok promosi untuk menampilkan banner visual kustom ATAU menautkan materi kampanye aktif yang sudah terdaftar di sistem.
5. **Frontend & Controller (`HomeController.php` & `home.blade.php`):**
   * *Perubahan:* `HomeController` diubah menjadi pemroses dinamis berbasis konfigurasi blok beranda aktif dengan proteksi caching. Template `home.blade.php` dipecah menjadi komponen modular (`<x-home.*>`).

---

## 3. Ruang Lingkup Berkas Terkait

Berkas-berkas yang akan dibuat atau disesuaikan:
* `database/migrations/xxxx_xx_xx_create_home_sections_table.php` (Tabel konfigurasi seksi beranda)
* `database/seeders/HomeSectionSeeder.php` (Seeder bawaan yang merefleksikan tampilan saat ini)
* `app/Models/HomeSection.php` (Model seksi beranda dengan JSON cast & query scopes)
* `app/Services/HomepageService.php` (Resolver data produk, artikel, dan banner per seksi dengan cache)
* `app/Filament/Pages/ManageHomepage.php` (Halaman kontrol Filament beranda)
* `app/Http/Controllers/HomeController.php` (Pengambilan data seksi aktif)
* `resources/views/home.blade.php` (Looping seksi dinamis)
* `resources/views/components/home/hero.blade.php` (Komponen modular Hero)
* `resources/views/components/home/categories.blade.php` (Komponen modular Kategori)
* `resources/views/components/home/products.blade.php` (Komponen modular Produk)
* `resources/views/components/home/articles.blade.php` (Komponen modular Artikel)
* `resources/views/components/home/promo-banner.blade.php` (Komponen modular Banner Promo)
* `resources/views/components/home/cta-consultation.blade.php` (Komponen modular WhatsApp CTA)
* `tests/Feature/HomepageCustomizerTest.php` (Pengujian fungsional otomatis)

---

## 4. Spesifikasi Database & Arsitektur Data

### A. Tabel `home_sections`
Gunakan skema tabel yang fleksibel, menyimpan data struktural di kolom indeks dan detail konfigurasi spesifik di kolom JSON:

* `id` (bigint, primary key)
* `key` (string, unique) — Pengenal unik, misalnya: `hero`, `categories`, `products_featured`, `articles_recent`, `cta_large`, `promo_strip`
* `type` (string) — Tipe seksi: `hero`, `categories`, `products`, `articles`, `banner`, `cta`
* `title` (string, nullable) — Label/judul seksi
* `sort_order` (integer, default 0) — Urutan tampilan seksi di beranda
* `is_active` (boolean, default true) — Sakelar tampil/sembunyi seksi
* `settings` (json, nullable) — Payload konfigurasi khusus tiap jenis seksi
* `timestamps`

### B. Seeder Default (Preservasi Tampilan Eksisting)
Seeder harus memuat 5 seksi default dengan urutan yang sama persis dengan `home.blade.php` saat ini:
1. `hero` (urutan 1): Konfigurasi teks judul bawaan, aksen kata "tepat untuk proyek Anda", badge "Katalog bahan bangunan", deskripsi situs, 3 trust badges bawaan (100% Produk Asli & SNI, Transaksi Toko Resmi, Konsultasi WhatsApp Cepat), dan membaca konfigurasi banner lama dari `Setting`.
2. `categories` (urutan 2): Mode tampil semua kategori induk aktif.
3. `products_featured` (urutan 3): Tipe `products`, judul "Produk pilihan", mode `featured`, limit 8, CTA link `/produk`.
4. `articles_recent` (urutan 4): Tipe `articles`, judul "Panduan & artikel", mode `latest`, limit 3, CTA link `/artikel`.
5. `cta_large` (urutan 5): Tipe `cta`, judul "Butuh material dalam jumlah besar?", teks konsultasi WhatsApp.

---

## 5. Spesifikasi Fungsional Per Blok Seksi

### A. Blok Hero & Kampanye (`type: hero`)
1. **Teks & Konten:**
   * Teks label/badge kecil (misal: "Katalog bahan bangunan").
   * Judul H1 utama dan input terpisah untuk kata/frasa aksen gradien oranye.
   * Deskripsi/tagline hero.
   * Toggle form pencarian dan teks placeholder input pencarian.
   * Repeater Trust Badges: Klien bebas menambah/mengurangi/mengubah poin jaminan (ikon centang + teks).
2. **Integrasi Kampanye Hero:**
   * Toggle aktifkan banner kampanye hero.
   * Pilihan gaya tampilan: `card` (Showcase 2 kolom), `banner` (Strip promo lebar), atau `background` (Latar penuh gelap).
   * File upload gambar poster/flyer promo.
   * Teks badge promo beranimasi (misal: "Promo Bundling Spesial").
   * Judul kampanye, deskripsi singkat, teks tombol aksi (CTA), dan tautan tujuan (CTA URL).

### B. Blok Kategori (`type: categories`)
* Toggle aktifkan seksi.
* Pilihan sumber: Tampilkan semua kategori induk aktif (default) atau pilih kategori tertentu saja.
* Opsi kolom grid responsif.

### C. Blok Produk (`type: products`)
*Klien dapat memiliki lebih dari 1 blok produk di beranda (misal: Blok 1 untuk Diskon Mingguan, Blok 2 untuk Produk Terpopuler).*
1. **Identitas Seksi:**
   * Judul seksi (misal: "Produk Pilihan", "Rekomendasi Terbaik", "Semen & Mortar Terlaris").
   * Teks tautan & URL CTA (misal: "Lihat semua produk →").
2. **Metode Pemilihan Produk (`selection_mode`):**
   * `manual`: Admin memilih produk tertentu menggunakan pemilih produk searchable (multi-select) dengan urutan yang bisa digeser.
   * `category`: Menampilkan produk dari satu kategori spesifik yang dipilih admin.
   * `featured`: Menampilkan produk yang memiliki tanda unggulan (`is_featured = true`).
   * `popular`: Menampilkan produk paling populer (diurutkan berdasarkan interaksi klik `click_events` atau agregat pembaca).
   * `latest`: Menampilkan produk paling baru diunggah/diperbarui.
3. **Kapasitas Tampilan (`limit`):**
   * Pilihan kuota produk: 4, 8, 12, atau 16 produk.

### D. Blok Panduan & Artikel (`type: articles`)
1. **Identitas Seksi:**
   * Judul seksi (Default: "Panduan & artikel").
   * Teks tautan & URL CTA (Default: "Semua artikel →").
2. **Kriteria Sortir & Sumber Artikel (`sort_by`):**
   * `latest`: Otomatis artikel terbaru berdasarkan `published_at` (terbit terkini).
   * `popular`: Otomatis artikel terpopuler berdasarkan jumlah pembaca terbanyak (`views_count` descending).
   * `manual`: Admin memilih 3 s.d. 6 artikel spesifik yang ingin di-highlight (pinned articles).
   * `category`: Filter artikel dari kategori/topik tertentu.
3. **Kapasitas Tampilan (`limit`):**
   * Pilihan: 3 atau 6 artikel.

### E. Blok Banner Promosi Antar-Seksi (`type: banner`)
* Blok banner lebar yang bisa disisipkan bebas di antara seksi produk dan artikel.
* Mendukung upload banner kustom (Desktop & Mobile view) atau pemilihan kampanye aktif dari modul `Ad`.

### F. Blok Konsultasi Proyek / WhatsApp CTA (`type: cta`)
* Judul penawaran (misal: "Butuh material dalam jumlah besar?").
* Deskripsi ajakan konsultasi.
* Teks tombol WhatsApp (misal: "Chat WhatsApp").
* Opsi nomor WhatsApp khusus (jika kosong, fallback otomatis ke nomor WhatsApp default di `Setting`).

---

## 6. Spesifikasi Antarmuka Admin Filament (`ManageHomepage`)

Rancang antarmuka admin di Filament (v4/v5 Schema style yang digunakan di proyek ini) agar klien merasa nyaman, intuitif, dan tidak kebingungan:

1. **Tata Letak Halaman Admin:**
   * Berikan tab atau accordion berbasis daftar seksi yang dapat diurutkan (*Reorderable Repeater / Builder* atau daftar baris seksi dengan input angka urutan).
   * Sediakan tombol sakelar (*Toggle*) aktif/nonaktif yang jelas di samping nama setiap seksi.
2. **Instruksi & Teks Bantuan (Helper Text):**
   * Semua label formulir wajib menggunakan Bahasa Indonesia yang ramah non-teknis.
   * Sertakan petunjuk jelas di bawah kolom gambar, misalnya rekomendasi rasio dan resolusi gambar hero.
3. **Penyimpanan Aman & Caching:**
   * Saat form disimpan, lakukan pembersihan cache beranda otomatis: `Cache::forget('homepage_sections')` dan notifikasi sukses Filament.
   * Terapkan pencatatan audit log jika diperlukan (`AuditLog::record`).
4. **Tombol Pratinjau / Tautan Cepat:**
   * Di bagian header halaman Filament, sertakan tombol aksi **"Lihat Beranda Publik"** yang membuka tab baru menuju `/` agar klien dapat langsung mengecek hasil perubahannya.

---

## 7. Desain Frontend & Komponen Modular Blade

1. **Refaktor `home.blade.php`:**
   Ubah berkas utama menjadi orkestrator yang membaca seksi aktif:
   ```blade
   <x-layouts.app :canonical="url('/')" ...>
       @foreach($sections as $section)
           @if($section->is_active)
               @include('components.home.' . $section->type, ['section' => $section, 'data' => $section->resolved_data])
           @endif
       @endforeach
   </x-layouts.app>
   ```
2. **Pemisahan Komponen:**
   Setiap berkas di `resources/views/components/home/` mengisolasi markup dan styling masing-masing seksi agar mudah dipelihara dan tidak terjadi benturan CSS:
   * Pertahankan efek visual premium saat ini (glassmorphism, radial gradient, styling dark/ink-950, animasi hover card, responsive container).
3. **SEO & Semantik HTML:**
   * Pastikan H1 tetap berada pada blok Hero dan tidak menduplikasi tag H1 pada blok lainnya (blok produk & artikel menggunakan H2 semantik).
   * Schema JSON-LD tetap valid dan konsisten.

---

## 8. Kriteria Pengujian & Jaminan Kualitas (QA Acceptance Criteria)

Implementasikan unit/feature tests di Pest PHP (`tests/Feature/HomepageCustomizerTest.php`) untuk memvalidasi:
1. **Urutan Tata Letak:** Mengubah nilai `sort_order` antar seksi benar-benar mengubah urutan kemunculan HTML di halaman beranda.
2. **Visibilitas Seksi:** Mematikan sakelar `is_active` pada seksi artikel/kategori berhasil menghilangkan seksi tersebut dari halaman publik tanpa error.
3. **Kustomisasi Teks Hero:** Judul H1 dan teks jaminan yang diubah di admin berhasil ter-render di halaman publik.
4. **Metode Produk Terpopuler vs Manual:**
   * Pengujian mode `popular` memastikan produk dengan interaksi klik terbanyak tampil teratas.
   * Pengujian mode `manual` memastikan hanya produk dengan ID terpilih yang ditampilkan.
5. **Kriteria Sortir Artikel:**
   * Pengujian mode `popular` memastikan artikel dengan `views_count` tertinggi tampil teratas.
   * Pengujian mode `latest` memastikan artikel dengan `published_at` terbaru tampil teratas.
6. **Regresi Nol:** Seluruh pengujian lama di `tests/Feature/PublicSiteTest.php` harus tetap **PASS 100%**.

---

## 9. Petunjuk Eksekusi Mandiri untuk Claude Code

* Jangan ubah skema database lain yang tidak berhubungan.
* Perhatikan konvensi Filament yang digunakan pada repositori ini (lihat `app/Filament/Pages/SiteSettings.php` dan `app/Filament/Resources/Articles/ArticleResource.php` sebagai referensi komponen schema).
* Selalu jalankan migrasi, seeder, dan uji dengan `php artisan test` untuk memastikan semua skenario berjalan sempurna.
