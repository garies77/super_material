# Plan Pengembangan Server MCP Supermaterial

Status awal: server `Supermaterial` versi 0.4.0 (`app/Mcp/Servers/SupermaterialServer.php`) dengan 16 tool: baca katalog, artikel, dan laporan, serta tulis produk dan artikel. Fondasinya sudah benar dan dipertahankan: tool hanya muncul bila ability token ∩ peran mengizinkan (`AbilityTool`), validasi memakai aturan yang sama dengan REST API (`ProductRules`, `ArticleRules`), dan setiap perubahan tercatat di `AuditLog` dengan sumber `mcp`.

Tujuan: satu perintah ke asisten AI, misalnya "rapikan semua produk kategori Cetok lalu buatkan artikelnya", bisa jalan dari awal sampai siap terbit tanpa harus membuka panel admin, tanpa menurunkan keamanan.

## Temuan dari pemakaian dan kode

| # | Temuan | Penyebab di kode | Dampak |
|---|--------|------------------|--------|
| 1 | Foto produk dan foto utama artikel tidak bisa diunggah lewat MCP | REST sudah punya `POST products/{product}/media` (`ProductMediaUploader`), tetapi belum ada tool MCP-nya. Artikel belum punya jalur unggah sama sekali, padahal `Article::imageUrl()` sudah bisa menerima URL | Alur dari buat sampai terbit selalu terputus di panel admin |
| 2 | Ada slug berisi spasi atau huruf besar: kategori `Palu-Jaya- Intero `, `kain mat-jaya-intero`, dan produk `Palu -Kambing -Magnet -Wolf -medan` | `CategoryResource` dan `BrandResource` (Filament) hanya mewajibkan slug unik, tanpa `regex` `ProductRules::SLUG_PATTERN`. Form produk di Filament juga perlu dicek | URL berisi `%20`, sulit dipakai sebagai filter, dan kurang rapi untuk SEO |
| 3 | Harga Rp32 dan harga yang tidak wajar bisa tersimpan | `price_min` hanya `min:0` | Harga salah bisa tampil di situs dan schema Product |
| 4 | Kategori dan merek hanya bisa dibaca | Belum ada tool tulis, dan juga belum ada endpoint REST untuk itu | Typo "Dnynabolt" dan slug rusak tidak bisa diperbaiki dari AI |
| 5 | Audit katalog harus dilakukan satu per satu | Belum ada tool ringkasan kualitas data | Lambat, dan AI menghabiskan banyak panggilan |
| 6 | Belum ada cara menghapus atau mengarsipkan | Sengaja belum dibuat | Draf yang salah, misalnya draf bundling Rp32, menumpuk |
| 7 | `resources` dan `prompts` di server masih kosong | Belum dipakai | Pedoman gaya tulis, aturan SEO, dan alur kerja harus diulang di setiap percakapan |
| 8 | Impor Excel ada di REST (`import:write`) tetapi tidak ada di MCP | Belum dibuka | Perubahan massal harus lewat panel |

## Prinsip yang tidak berubah

1. **Aturan tunggal.** Tool MCP selalu memanggil Rules dan Writer yang sama dengan REST API dan Filament. Validasi tidak boleh ditulis dua kali.
2. **Hak efektif = ability token ∩ peran.** Ability baru didaftarkan di `config/api.php` (`abilities`, `write_abilities`, `role_abilities`).
3. **Draf dulu.** Konten baru tetap tersimpan sebagai draf. Terbit hanya bila diminta.
4. **Semua tulisan diaudit** lewat `AuditLog::record(..., source: 'mcp')`.
5. **Tidak ada penghapusan permanen lewat MCP.** Yang tersedia hanya arsip (soft delete) yang bisa dipulihkan dari panel.
6. **Ditambah teruskan, bukan diubah.** Kontrak tool lama tidak diubah, supaya klien yang sudah memakai server ini tidak rusak. Versi server naik di setiap fase.

---

## Fase A: Data bersih (fondasi, kerjakan dulu)

Tujuan: data yang masuk dari jalur mana pun (panel, REST, MCP) sudah valid, supaya akses AI yang lebih luas aman.

**A1. Validasi slug di Filament**
- Tambahkan `->regex(ProductRules::SLUG_PATTERN)` dan `->maxLength(255)` pada field slug di `CategoryResource`, `BrandResource`, form produk, dan form artikel.
- Ubah `afterStateUpdated` agar slug otomatis memakai `Str::slug()` juga saat edit bila slug masih kosong.
- Tes: menyimpan kategori dengan slug `"Palu Jaya "` ditolak.

**A2. Batas harga yang masuk akal**
- `ProductRules`: `price_min` minimal 100 bila diisi (konstanta `MIN_PRICE`, bisa diatur lewat config).
- Peringatan, bukan penolakan: bila harga berubah lebih dari 70% dari harga lama, respons tool menyertakan `warnings[]` agar AI meminta konfirmasi ke pengguna.

**A3. Perintah perbaikan data lama**
- `php artisan catalog:normalize-slugs --dry-run`: menampilkan slug kategori, merek, dan produk yang tidak sesuai pola beserta usulan barunya. Tanpa `--dry-run`, perintah ini menyimpan perubahan dan membuat redirect 301, memakai mekanisme yang sudah ada saat slug diubah.
- Tes: slug lama menghasilkan redirect ke slug baru.

**Selesai bila:** tidak ada slug di luar pola, dan jalur panel, REST, maupun MCP sama-sama menolak harga di bawah batas.

## Fase B: Media lewat MCP (dampak terbesar)

**B1. Tool `add-product-media`** (ability `catalog:write`)
- Argumen: `product`, `url` (gambar atau video publik), `alt`, `position` (opsional), `is_primary` (opsional).
- Server mengunduh file dari URL dengan `Http::timeout(15)` dan batas ukuran `api.uploads.image_max_kb`, memeriksa tipe MIME (jpeg, png, webp), lalu menyimpannya lewat `ProductMediaUploader::store()`. Dengan begitu konversi, ukuran, dan resize sama dengan unggahan REST.
- Keamanan: tolak URL ke IP privat dan localhost (perlindungan SSRF), ikuti paling banyak 3 redirect, dan hanya izinkan skema `https`.
- Audit: `product.media_added`.

**B2. Tool `remove-product-media` dan `reorder-product-media`**
- Hapus atau urutkan berdasarkan `media_id` dari `get-product`. Berkas dipindahkan ke arsip, tidak langsung dihapus dari disk.

**B3. Foto utama artikel**
- Tambahkan argumen `image_url` di `create-article` dan `update-article`. Server mengunduh gambar ke `storage/articles/` dengan aturan yang sama seperti B1, lalu mengisi `image`.
- Tambahkan argumen `image_alt`.
- Endpoint REST yang setara: `POST articles/{article}/image`.

**B4. Tool `generate-image-alt`** (opsional)
- Membuat alt text dari nama produk dan varian bila `alt` kosong. Logikanya ditulis di PHP, tidak bergantung pada AI.

**Selesai bila:** artikel dynabolt yang sudah ada bisa diberi foto utama lewat MCP, lalu tampil benar di halaman dan di `og:image`.

## Fase C: Kategori, merek, dan tag

Ability baru: `taxonomy:write`, ditambahkan ke `write_abilities` serta peran admin dan editor.

**C1. Tool `create-category`, `update-category`**
- Kolom: `name`, `slug`, `parent`, `description`, `is_active`, `meta_title`, `meta_description`, `image_url`.
- Mengubah slug membuat redirect 301, memakai mekanisme yang sama dengan panel.
- Peringatan bila kategori masih punya produk saat `is_active=false`.

**C2. Tool `create-brand`, `update-brand`**
- Kolom: `name`, `slug`, `description`, `logo_url`, `website`.

**C3. Tool `list-tags`, `merge-tags`**
- `merge-tags` memindahkan semua artikel dari tag sumber ke tag tujuan, lalu mengarsipkan tag sumber. Gunanya untuk merapikan tag yang dobel, misalnya "tips tukang" dan "tips-tukang".

**C4. REST setara**
- `POST/PATCH admin/categories`, `admin/brands`. Ini sekaligus memperbarui PRD 8.12.6.

**Selesai bila:** typo kategori "Dnynabolt" bisa diganti menjadi "Dynabolt" lewat MCP, URL lama mengarah ke URL baru, dan `list-categories` menampilkan hasilnya.

## Fase D: Audit dan operasi massal

**D1. Tool `audit-catalog`** (ability `catalog:read`)
- Satu panggilan mengembalikan daftar masalah per produk, dikelompokkan per jenis:
  - tanpa foto, foto tanpa alt
  - tanpa `meta_description`, atau `meta_title` lebih dari 60 karakter
  - tanpa FAQ, tanpa link marketplace aktif
  - harga kosong atau `price_review_needed`
  - deskripsi pendek (kurang dari 300 karakter)
  - slug di luar pola
  - kemungkinan dobel: nama mirip, dicek dengan `similar_text` atau trigram, dalam satu kategori dan merek. Contohnya dua listing Dynabolt WOWO
- Filter: `category`, `brand`, `status`, `issue`.
- Skor kelengkapan 0–100 per produk, supaya AI bisa mulai dari yang paling buruk.

**D2. Tool `audit-articles`**
- Tanpa foto utama, tanpa produk tertaut, tautan produk yang statusnya draf, meta yang kosong atau terlalu panjang, artikel yang judulnya mirip.

**D3. Tool `bulk-update-products`** (ability `catalog:write`)
- Argumen: `products[]` (ID atau slug, maksimal 50) dan `changes` (kolom yang sama dengan `update-product`).
- Wajib `dry_run: true` lebih dulu. Hasilnya berisi diff per produk dan `confirmation_token`. Panggilan kedua dengan token itu baru menyimpan. Token berlaku 10 menit dan memakai tabel `idempotency_keys` yang sudah ada.
- Semua perubahan berjalan dalam satu transaksi dan setiap produk diaudit.

**D4. Tool `start-import` dan `get-import-status`** (ability `import:write`)
- Membuka impor Excel yang sudah ada di REST. File diambil dari URL, dengan aturan unduh yang sama seperti B1.

**Selesai bila:** "cek produk mana yang belum lengkap" dijawab dalam satu panggilan, dan perubahan massal 20 produk bisa dipratinjau lalu disimpan dalam dua panggilan.

## Fase E: Siklus hidup konten

**E1. Tool `archive-product`, `archive-article`**
- Memakai soft delete. Hanya bisa dilakukan bila status draf, atau bila dikirim `force_unpublish: true` untuk konten yang sudah terbit.
- Tidak ada tool penghapusan permanen. Pemulihan dilakukan dari panel.

**E2. Tool `publish-article` dan `schedule-article`**
- Pintasan yang jelas niatnya, supaya tidak sekadar mengubah `status` lewat `update-article`. Sebelum terbit, tool menjalankan `audit-articles` untuk artikel itu dan menolak bila foto utama atau meta description kosong, kecuali dikirim `skip_checks: true`.

**E3. Tool `get-audit-log`** (ability baru `audit:read`, hanya untuk admin)
- Menampilkan riwayat perubahan sebuah produk atau artikel: siapa, kapan, sumbernya, dan apa yang berubah. Berguna untuk menjawab pertanyaan seperti "siapa yang mengubah harga ini?".

**E4. Tool `get-revisions` dan `restore-revision` untuk artikel**
- Hanya bila model revisi sudah ada atau dibuat di fase ini. Tool ini mengembalikan isi sebelumnya bila AI salah menulis.

## Fase F: Membuat AI lebih pintar (resources dan prompts)

**F1. Resources MCP**
- `supermaterial://pedoman/gaya-tulis`: suara merek, istilah yang dipakai, kata yang dihindari.
- `supermaterial://pedoman/seo`: panjang meta, pola judul, aturan tautan internal.
- `supermaterial://katalog/ringkasan`: jumlah produk per kategori dan merek, rentang harga. Dibuat ulang dari cache.
- Isinya disimpan di tabel `settings` agar bisa diubah dari panel tanpa deploy.

**F2. Prompts MCP**
- `tulis-artikel-dari-produk` (argumen: `category` atau `products`): langkah cari, cek duplikat, susun outline, minta persetujuan, lalu simpan draf.
- `rapikan-produk` (argumen: `product`): audit, usulan perbaikan, persetujuan, lalu simpan.
- `laporan-mingguan`: ringkasan trafik, klik, produk teratas, dan rekomendasi.

**F3. Tool `suggest-internal-links`**
- Untuk satu artikel, mengembalikan produk dan artikel lain yang relevan berdasarkan kategori, tag, dan pencocokan kata. Logikanya ditulis di PHP.

## Fase G: Laporan yang bisa ditindaklanjuti

**G1. Tool `get-search-report`**
- Membaca `SearchStat`: kata kunci yang paling sering dicari dan yang hasilnya nol. Dari sini kelihatan produk atau artikel yang perlu dibuat.

**G2. Tool `get-content-opportunities`**
- Menggabungkan pencarian tanpa hasil, produk dengan klik tinggi tapi tanpa artikel, dan kategori tanpa artikel, lalu mengembalikan daftar topik yang diurutkan.

**G3. Webhook keluar** (sudah tercatat sebagai Fase 2 di PRD 8.12.10)
- `product.published`, `product.price_changed`, `article.published` dengan HMAC. Bisa dipakai untuk memicu n8n, misalnya membuat posting Instagram otomatis saat artikel terbit.

---

## Urutan dan perkiraan

| Fase | Isi | Perkiraan | Versi server |
|------|-----|-----------|--------------|
| A | Data bersih | 1–2 hari | 0.4.1 |
| B | Media | 2–3 hari | 0.5.0 |
| C | Kategori, merek, tag | 2 hari | 0.6.0 |
| D | Audit dan operasi massal | 3–4 hari | 0.7.0 |
| E | Siklus hidup konten | 2 hari | 0.8.0 |
| F | Resources dan prompts | 1–2 hari | 0.9.0 |
| G | Laporan dan webhook | 2–3 hari | 1.0.0 |

A wajib paling awal. B dan C bisa dikerjakan paralel di branch terpisah karena berkas yang disentuh berbeda. D bergantung pada A, karena aturan slug dan harga dipakai sebagai pemeriksaan audit.

## Pengujian per fase

- Setiap tool baru mendapat tes di `tests/Feature/Mcp*Test.php` dengan pola yang sama seperti `McpWriteToolsTest`:
  - tool hanya terdaftar bila ability dan peran cocok
  - input tidak valid ditolak dengan pesan berbahasa Indonesia
  - perubahan tercatat di `AuditLog` dengan `source = mcp`
  - hasilnya sama dengan jalur REST yang setara
- Khusus unduh dari URL: tes dengan `Http::fake()` untuk MIME salah, ukuran terlalu besar, redirect ke IP privat, dan timeout.
- Jalankan `php artisan test --filter=Mcp` serta seluruh test suite sebelum merge.

## Di luar cakupan

- Penghapusan permanen lewat MCP.
- Mengubah pengguna, peran, token API, redirect manual, dan iklan lewat MCP (tetap mengikuti PRD 8.12.10).
- Sinkronisasi harga dan stok otomatis dari marketplace.
- AI generatif di sisi server. Semua penulisan konten tetap dilakukan oleh klien AI, sedangkan server hanya memvalidasi dan menyimpan.
