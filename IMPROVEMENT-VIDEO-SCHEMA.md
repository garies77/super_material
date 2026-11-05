# Rencana Fitur: Markup Video (VideoObject) — Tahap 1

> **Cakupan tahap 1:** artikel dengan mode "video utama", momen penting, sitemap video, dan perbaikan markup video produk.
> **Tahap 2 (nanti):** halaman statis, setelah tahap 1 terbukti di Search Console.
>
> Referensi: [Google — Data terstruktur video](https://developers.google.com/search/docs/appearance/structured-data/video?hl=id) · [Laporan pengindeksan video](https://support.google.com/webmasters/answer/9495631)

---

## Ringkasan

| Bagian | Kondisi sekarang | Target tahap 1 |
|---|---|---|
| Artikel | Belum bisa diberi video (RichEditor membuang iframe) | Section "Video" di admin, player "video utama" di atas artikel, `video` di JSON-LD Article, chapter (Clip) |
| Produk | `VideoObject` hanya untuk video pertama, `name` generik, `uploadDate` = tanggal produk dibuat, `contentUrl` diisi URL YouTube | Semua video ikut, metadata asli (judul, deskripsi, tanggal unggah, durasi, chapter), `contentUrl` hanya untuk file unggahan |
| Sitemap | `products, categories, articles, pages, authors, images` | Ditambah `sitemap-videos.xml` |
| Kode YouTube | 3 regex terpisah (ProductMedia, PageBlocks, page-blocks.blade) | Satu helper `App\Support\YouTube` |

### Aturan Google yang menjadi patokan
- **Wajib:** `name`, `thumbnailUrl`, `uploadDate` (ISO 8601 dengan zona waktu). Kalau salah satunya kosong, markup **tidak dikeluarkan**.
- **Disarankan:** `description`, `duration` (`PT4M30S`), `embedUrl` / `contentUrl`, `hasPart` (Clip), `expires`, `regionsAllowed`.
- Fitur video di hasil penelusuran hanya muncul kalau **video adalah konten utama halaman**: posisinya paling atas, terlihat tanpa scroll, dan lebar minimal 140px.
- `contentUrl` harus berisi file video (`.mp4`, dll.), bukan URL halaman YouTube. Untuk YouTube cukup `embedUrl`.
- Isi markup harus sesuai dengan yang tampil di halaman: judul dan deskripsi video perlu terlihat.

### Keputusan yang dipakai (bisa diubah sebelum mulai)
1. **Isi otomatis dari YouTube:** ~~YouTube Data API v3~~ dibatalkan. Hanya oEmbed tanpa API key (judul saja); tanggal unggah opsional dengan cadangan waktu video ditambahkan.
2. **Posisi video utama di artikel:** di bawah judul/meta, di atas isi. Gambar utama tetap dipakai untuk OG dan `image` di schema, tapi tidak ditampilkan saat ada video utama.
3. **Data video produk:** tetap di `product_media`, ditambah kolom baru. Galeri, API, duplikator, dan impor yang sekarang tidak berubah.

---

## Fase 0 — Persiapan

**Tujuan:** memastikan aturan proyek dan dependensi aman sebelum menulis kode.

- [ ] Baca `.ai/rules/index.md` (kalau ada) dan aturan yang cocok untuk `app/Models`, `app/Filament`, `resources/views`, `database/migrations`.
- [ ] `search-docs` (Boost) untuk: Filament Repeater relationship, Filament `Action` di form field (tombol "Isi otomatis"), Laravel HTTP client, `morphMany`.
- [ ] Pastikan CSP `frame-src` sudah mengizinkan `youtube-nocookie.com` dan `img-src` mengizinkan `i.ytimg.com` / `img.youtube.com` (`config/security.php`).
- [ ] Cek versi paket: `composer show filament/filament`.

**Selesai bila:** daftar aturan yang berlaku sudah dicatat dan tidak perlu dependensi baru. YouTube API dipanggil memakai `Http::` bawaan Laravel.

---

## Fase 1 — Fondasi bersama

**Tujuan:** satu sumber kebenaran untuk urusan YouTube dan pembuatan schema, dipakai artikel, produk, dan nanti halaman statis.

### 1.1 `App\Support\YouTube` (`php artisan make:class Support/YouTube`)
- `videoId(?string $url): ?string`: mendukung `watch?v=`, `youtu.be/`, `shorts/`, `embed/`, `youtube-nocookie.com`.
- `embedUrl(string $id, ?int $start = null): string` → `https://www.youtube-nocookie.com/embed/{id}` (+ `?start=`).
- `watchUrl(string $id): string`.
- `thumbnailUrl(string $id): string` → `https://i.ytimg.com/vi/{id}/hqdefault.jpg`. `maxresdefault` tidak selalu tersedia, jadi `hqdefault` dipakai sebagai default yang aman.
- Refactor pemakai lama agar memanggil helper ini:
  - `ProductMedia::youtubeVideoId()`
  - `resources/views/partials/page-blocks.blade.php` (blok `embed`)
  - Regex di `PageBlocks` dan `ProductResource::videoData()` boleh tetap sebagai validasi form, tapi pola ID diambil dari konstanta di helper.

### 1.2 `App\Support\Seo\VideoSchema`
Input berupa satu objek data video yang sudah dinormalisasi (lihat 1.3), output berupa array `VideoObject` atau `null`.

- Mengembalikan `null` bila `name`, `thumbnailUrl`, atau `uploadDate` kosong.
- `description`: pakai deskripsi video. Kalau kosong, pakai cadangan dari halaman induk (dipotong 300 karakter, tanpa tag HTML).
- `duration`: detik → ISO 8601 (`272` → `PT4M32S`).
- `embedUrl` untuk YouTube, `contentUrl` hanya untuk `provider = upload`.
- `hasPart`: setiap chapter menjadi
  ```json
  { "@type": "Clip", "name": "Mengaduk semen", "startOffset": 130, "endOffset": 285, "url": "https://situs/artikel/slug?t=130" }
  ```
  `endOffset` chapter terakhir = durasi video (kalau ada). Chapter yang offset-nya tidak urut atau melebihi durasi dibuang.
- `regionsAllowed` / `expires`: opsional, hanya keluar kalau diisi.
- Tanpa `@context` (karena disisipkan ke schema induk). Ada metode `standalone()` yang menambahkan `@context` untuk dipakai sendiri.

### 1.3 Data transfer object `App\Support\Seo\VideoData`
`readonly` class dengan properti: `title, description, thumbnailUrl, uploadDate (CarbonInterface), durationSeconds, provider, url, youtubeId, clips (array<int, array{name: string, start: int, end: ?int}>), pageUrl`.
Dibuat dari `Video` (artikel) dan `ProductMedia` (produk) lewat metode `toVideoData(string $pageUrl)` di masing-masing model.

### 1.4 Helper durasi
- `VideoSchema::isoDuration(int $seconds): string`
- `VideoDuration::parse(string $input): ?int` menerima `4:32`, `1:02:10`, atau `272`. Dipakai form admin.

**Selesai bila:** unit test helper YouTube, durasi, dan `VideoSchema` lulus (lihat Fase 7), dan halaman produk/halaman statis yang lama tetap tampil normal setelah refactor.

---

## Fase 2 — Database & model

### 2.1 Tabel `videos` untuk artikel (polimorfik, siap dipakai halaman statis di tahap 2)
`php artisan make:model Video -mf --no-interaction`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint | |
| `videoable_type`, `videoable_id` | `morphs` | index |
| `provider` | string(20) | `youtube` / `upload` |
| `url` | string(500) | URL YouTube atau path file di disk publik |
| `title` | string(255) | wajib |
| `description` | text, nullable | |
| `thumbnail_path` | string(500), nullable | thumbnail kustom (Pustaka Media). Kosong = thumbnail YouTube |
| `upload_date` | timestamp, nullable | tanggal unggah video asli. Kosong = markup tidak keluar |
| `duration_seconds` | unsigned int, nullable | |
| `clips` | json, nullable | `[{name, start, end}]` |
| `is_primary` | boolean, default false | maksimal satu per artikel |
| `sort_order` | unsigned int, default 0 | |
| `timestamps` | | |

### 2.2 Model `App\Models\Video`
- `videoable(): MorphTo`
- casts: `upload_date => datetime`, `clips => array`, `is_primary => boolean`, `duration_seconds => integer`.
- `youtubeId()`, `embedUrl()`, `thumbnailUrl()`, `isYoutube()`, `toVideoData(string $pageUrl): VideoData`.
- Event `saving`: isi `provider` otomatis dari URL. Kalau `is_primary = true`, matikan `is_primary` video lain milik induk yang sama.
- Factory dengan state `youtube()`, `primary()`, `withClips()`, `incomplete()` (tanpa `upload_date`).

### 2.3 `Article`
- `videos(): MorphMany` → `orderBy('sort_order')`.
- `primaryVideo(): ?Video` → dari koleksi yang sudah di-eager-load.
- `HasSeo` / skor SEO artikel (kalau ada perhitungan skor): opsional, beri poin tambah bila video utama lengkap.

### 2.4 Kolom tambahan di `product_media`
`php artisan make:migration add_video_metadata_to_product_media_table`
- `description` (text, nullable), `upload_date` (timestamp, nullable), `duration_seconds` (unsigned int, nullable), `clips` (json, nullable).
- Judul video tetap memakai kolom `alt_text` yang sudah ada.
- `ProductMedia::toVideoData()` dengan cadangan `upload_date` → `created_at` baris media (bukan tanggal produk). Lebih baik lagi: diisi dari YouTube.
- `ProductDuplicator` sudah me-replicate media, jadi kolom baru ikut tersalin. Pastikan lewat test.

### 2.5 Eager loading
- `ArticleController` (baris 129 & 148): tambahkan `'videos'` di samping `'faqs'`.
- `ProductController`: media sudah di-load, tidak perlu perubahan.

**Selesai bila:** `php artisan migrate` berjalan di lokal, factory bisa membuat data, dan tidak ada query N+1 baru di halaman artikel.

---

## Fase 3 — Isi otomatis dari YouTube

> **Diputuskan 31-10-2026:** tanpa YouTube Data API (pengaturan API key di Google Cloud dianggap terlalu ribet). Hanya oEmbed publik yang dipakai.

**Tujuan:** admin cukup menempel URL lalu klik satu tombol untuk judul video, tanpa API key.

### 3.1 `App\Support\YouTubeMetadata`
- `fetch(string $videoId): ?array{title, thumbnail_url}` lewat `https://www.youtube.com/oembed?url=…&format=json`.
- `Http::timeout(5)`, hasil di-cache 1 hari per video ID, gagal = `null` (tidak melempar error ke form).

### 3.2 Tombol di form
- Suffix action "Ambil judul dari YouTube" di field URL. Mengisi judul hanya bila masih kosong.
- Tanggal unggah, durasi, deskripsi, dan chapter diisi manual (semuanya opsional).
- **Tanggal unggah opsional:** kalau kosong, markup memakai waktu video ditambahkan ke artikel/produk, sehingga VideoObject tetap keluar.

**Selesai bila:** dengan HTTP palsu (`Http::fake`), tombol mengisi judul kosong dan tidak menimpa judul yang sudah diisi.

---

## Fase 4 — Admin (Filament)

### 4.1 `App\Filament\Support\VideoFields`
Skema field bersama, dipakai artikel dan produk (dan halaman statis di tahap 2):
- `url` (wajib, validasi YouTube atau file video) + action isi otomatis.
- `title` (wajib, maks 255), `description` (textarea, maks 2000).
- `upload_date` (DateTimePicker, zona waktu aplikasi), `duration` (TextInput "mm:ss", disimpan ke `duration_seconds` via `dehydrateStateUsing` / `formatStateUsing`).
- `thumbnail_path` (pemilih Pustaka Media, opsional, helper: "Kosongkan untuk memakai thumbnail YouTube").
- Repeater "Momen penting" (collapsible): `name`, `start` (mm:ss), `end` (mm:ss, opsional).
- Placeholder status: **"Siap tampil di Google"** / **"Belum lengkap: isi tanggal unggah"**, dihitung dari `VideoSchema`.

### 4.2 Artikel (`ArticleResource`)
- Section baru **"Video"** di dekat section FAQ: `Repeater::make('videos')->relationship()->orderColumn('sort_order')`.
- Field tambahan: toggle **"Video utama (tampil di atas artikel)"**, dengan helper: "Pilih ini kalau video adalah inti artikel, misalnya tutorial. Hanya satu video utama per artikel."
- Kolom tabel daftar artikel (opsional): ikon video bila artikel punya video utama.
- Pastikan alur review (`HasReviewWorkflow`) dan audit log ikut mencatat perubahan video, mengikuti cara FAQ diperlakukan.

### 4.3 Produk (`ProductResource`, repeater `videos` baris 203)
- Pakai `VideoFields` (tanpa toggle video utama). `alt_text` tetap menjadi judul.
- `videoData()` tetap menentukan `type`/`provider`. Durasi dan clips dinormalisasi di sini.
- Modal duplikat produk: deskripsi tidak perlu berubah.

### 4.4 API (opsional, mengikuti pola FAQ)
- `ArticleDetailResource` / `ProductDetailResource`: tambah array `videos` (judul, url, thumbnail, durasi, upload_date).
- Admin API produk: validasi field video baru di `ProductRules` hanya bila API memang menerima media video. Cek dulu `ProductMediaUploader`.

**Selesai bila:** admin bisa menambah, mengubah, mengurutkan, dan menghapus video di artikel dan produk, status kelengkapan tampil, dan test Filament lulus.

---

## Fase 5 — Tampilan publik

### 5.1 Komponen `x-video-player`
`resources/views/components/video-player.blade.php`
- Props: `video` (VideoData), `primary` (bool).
- YouTube: `<iframe src="youtube-nocookie.com/embed/{id}" loading="lazy">` dalam `aspect-video`, lebar penuh kolom konten (≥ 140px).
  - Video utama memakai iframe sungguhan, bukan thumbnail-klik, agar Google mendeteksi video sebagai konten utama.
  - Video tambahan boleh memakai pola "facade" (thumbnail + tombol play) seperti di produk, demi performa.
- File unggahan: `<video controls preload="metadata" poster="…">`.
- Judul (`<h2>`/`<figcaption>`) dan deskripsi singkat tampil di bawah player, sesuai isi markup.
- Daftar momen penting tampil sebagai tautan `?t=130` di bawah player.

### 5.2 Artikel
- Partial baru `resources/views/article/video.blade.php`.
- Ada video utama → player di bawah judul/meta, menggantikan gambar utama di posisi itu.
- Video lain → setelah isi artikel (`article/content`), sebelum FAQ.
- Hormati pengaturan Artikel (`ArticleSettings`: layout, lebar, urutan, `$show`). Tambahkan kunci `video` ke daftar bagian yang bisa diatur kalau polanya mendukung.

### 5.3 Deep-link momen penting (`?t=`)
- Skrip kecil di `resources/js` (ikut Vite, tidak inline agar aman CSP): kalau URL punya `?t=detik`, iframe video utama di-set `start=detik` lalu di-scroll ke player.
- Klik tautan chapter di halaman → perbarui `start` tanpa reload.

### 5.4 Produk
- Tampilan galeri tidak berubah. Kalau ada deskripsi video, tampilkan di bawah player modal atau tab video (sesuai struktur `product.blade.php`).

**Selesai bila:** di browser, video utama terlihat tanpa scroll di desktop dan mobile, `?t=90` memulai video di detik 90, tidak ada error CSP di konsol, dan screenshot desktop/mobile sudah dicek.

---

## Fase 6 — JSON-LD

### 6.1 Artikel (`resources/views/article.blade.php`)
- Tambahkan ke `$ld` yang sudah ada:
  ```php
  'video' => $videosLd ?: null, // array VideoObject dari VideoSchema, yang null dibuang
  ```
- Video utama berada di urutan pertama.
- `image` Article tetap ada. Kalau artikel tidak punya gambar utama, pakai thumbnail video utama sebagai `image` dan OG image.

### 6.2 Produk (`resources/views/product.blade.php` baris 165–176)
- Ganti blok `$firstVideo` dengan semua video lewat `VideoSchema`:
  - `name` = judul video (`alt_text`), cadangan "Video {produk}".
  - `uploadDate` = `upload_date` → `created_at` baris media.
  - Tanpa `contentUrl` untuk YouTube.
  - `description` = deskripsi video → cadangan deskripsi SEO produk.
- Satu video → objek, lebih dari satu → array.
- Sama untuk `ProductGroup`.

### 6.3 Contoh output artikel
```json
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "Cara Memasang Keramik 60x60 untuk Lantai",
  "video": [{
    "@type": "VideoObject",
    "name": "Cara Memasang Keramik 60x60 untuk Lantai",
    "description": "Panduan lengkap dari persiapan hingga nat…",
    "thumbnailUrl": ["https://i.ytimg.com/vi/abc123def45/hqdefault.jpg"],
    "uploadDate": "2026-09-14T10:00:00+07:00",
    "duration": "PT8M12S",
    "embedUrl": "https://www.youtube-nocookie.com/embed/abc123def45",
    "hasPart": [
      { "@type": "Clip", "name": "Persiapan alat", "startOffset": 30, "endOffset": 130, "url": "https://situs/artikel/cara-memasang-keramik?t=30" },
      { "@type": "Clip", "name": "Mengaduk semen instan", "startOffset": 130, "endOffset": 285, "url": "https://situs/artikel/cara-memasang-keramik?t=130" }
    ]
  }]
}
```

**Selesai bila:** Rich Results Test lokal (tempel HTML lewat tab "Kode") menunjukkan VideoObject valid tanpa error, dan peringatan hanya untuk field opsional.

---

## Fase 7 — Sitemap video

- `SeoController::TYPES` ditambah `'videos'` → `/sitemap-videos.xml` (+ penomoran halaman seperti tipe lain), otomatis masuk sitemap index.
- Isinya: artikel terbit (bukan `noindex`) yang punya **video lengkap**, ditambah produk aktif dengan video lengkap.
- Format:
  ```xml
  <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
          xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
    <url>
      <loc>https://situs/artikel/cara-memasang-keramik</loc>
      <video:video>
        <video:thumbnail_loc>…</video:thumbnail_loc>
        <video:title>…</video:title>
        <video:description>…</video:description>
        <video:player_loc>https://www.youtube-nocookie.com/embed/…</video:player_loc>
        <video:duration>492</video:duration>
        <video:publication_date>2026-09-14T10:00:00+07:00</video:publication_date>
      </video:video>
    </url>
  </urlset>
  ```
- File unggahan memakai `<video:content_loc>`, bukan `player_loc`.
- Semua teks di-escape dengan `e()`, dan deskripsi dipotong ≤ 2048 karakter.
- Ikuti cache sitemap yang sudah ada (kalau ada) dan tambahkan invalidasi saat video disimpan.

**Selesai bila:** `/sitemap-videos.xml` valid secara XML, hanya memuat video lengkap, dan tercantum di `/sitemap.xml`.

---

## Fase 8 — Test (Pest)

Baca skill `testing-best-practices` dulu. Buat dengan `php artisan make:test --pest`.

### Unit
- `YouTubeTest`: ID dari semua format URL, URL bukan YouTube → `null`.
- `VideoSchemaTest`:
  - Field wajib kosong → `null`.
  - Detik → ISO 8601 (`0`, `59`, `272`, `3730`).
  - Clip yang tidak urut atau melebihi durasi dibuang, `endOffset` terakhir = durasi.
  - YouTube tanpa `contentUrl`, file unggahan dengan `contentUrl`.
- `VideoDurationTest`: `4:32`, `1:02:10`, `272`, input sampah → `null`.

### Feature
- `ArticleVideoSchemaTest`:
  - Artikel dengan video utama lengkap → JSON-LD memuat `VideoObject` + `hasPart`, dan player tampil.
  - Video tanpa `upload_date` → `uploadDate` memakai waktu video ditambahkan.
  - Artikel tanpa video → tidak ada `VideoObject`.
  - Hanya satu `is_primary` per artikel.
- `ProductSingleTest` (update): nama video asli, `uploadDate` dari media, tanpa `contentUrl` YouTube, semua video ikut.
- `VideoSitemapTest`: memuat artikel/produk dengan video lengkap, tidak memuat draf/noindex, dan terdaftar di index.
- Tombol "Ambil judul dari YouTube": `Http::fake()` oEmbed, mengisi judul kosong, tidak menimpa judul yang sudah diisi.
- `AdminPanelTest` / test Filament: menyimpan artikel dengan repeater video, dan durasi yang tidak valid ditolak.

Jalankan yang relevan saja:
```bash
php artisan test --compact --filter=Video
```
Lalu `vendor/bin/pint --dirty --format agent`, dan minta suite penuh dijalankan.

---

## Fase 9 — Rilis & pemantauan di Google

1. Deploy (`DEPLOY.md`), lalu `php artisan migrate --force`.
2. `npm run build` (skrip momen penting di `resources/js/app.js`).
3. Pilih **3–5 artikel tutorial** yang videonya memang inti isinya. Beri video utama + chapter.
4. Uji URL-nya di **[Rich Results Test](https://search.google.com/test/rich-results)**.
5. Search Console → Sitemaps → kirim `sitemap-videos.xml`.
6. Search Console → **Inspeksi URL** → minta pengindeksan untuk artikel tadi.
7. Pantau 2–4 minggu di **Pengindeksan → Halaman video**. Status yang perlu ditangani:
   - *Video bukan konten utama halaman* → pindahkan video ke atas dan kurangi konten di atasnya.
   - *Video di luar area pandang* → cek tampilan mobile, header, dan iklan.
   - *Video terlalu kecil / tinggi tidak valid* → cek rasio dan lebar player.
   - *Thumbnail tidak valid* → cek akses `i.ytimg.com` dan thumbnail kustom.
8. Pantau Performa → Tampilan penelusuran → **Hasil video** untuk impresi dan klik.

**Lanjut ke tahap 2 (halaman statis) bila:** minimal sebagian artikel berstatus "Video diindeks" tanpa error markup.

---

## Tahap 2 (gambaran singkat, tidak dikerjakan sekarang)
- Halaman statis: blok `embed` di `PageBlocks` memakai `VideoFields` (data disimpan di JSON blok), JSON-LD `VideoObject` standalone per blok, dan masuk sitemap video.
- Halaman "kumpulan video tutorial" (video hub).
- Opsional: `interactionStatistic` (jumlah tayangan dari YouTube API), `BroadcastEvent` untuk siaran langsung.

---

## Perkiraan berkas yang tersentuh

**Baru**
- `app/Support/YouTube.php`, `app/Support/YouTubeMetadata.php`
- `app/Support/Seo/VideoSchema.php`, `app/Support/Seo/VideoData.php`, `app/Support/Seo/VideoDuration.php`
- `app/Models/Video.php`, `database/factories/VideoFactory.php`
- `database/migrations/…_create_videos_table.php`, `…_add_video_metadata_to_product_media_table.php`
- `app/Filament/Support/VideoFields.php`
- `resources/views/components/video-player.blade.php`, `resources/views/article/video.blade.php`
- Skrip momen penting ditambahkan ke `resources/js/app.js`
- Test: `tests/Feature/VideoSchemaTest.php`, `tests/Unit/VideoDurationTest.php`

**Diubah**
- `app/Models/Article.php`, `app/Models/ProductMedia.php`
- `app/Filament/Resources/Articles/ArticleResource.php`, `app/Filament/Resources/Products/ProductResource.php`
- `resources/js/app.js`, `app/Http/Controllers/SeoController.php`
- `app/Http/Controllers/ArticleController.php`, `app/Http/Controllers/SeoController.php`
- `resources/views/article.blade.php`, `resources/views/product.blade.php`, `resources/views/partials/page-blocks.blade.php`
- `resources/js/app.js` (import skrip deep-link)
- `tests/Feature/ProductSingleTest.php`
