# Rencana: Integrasi API (Search Console, Endpoint Jawaban, dan API Tersirat)

Sumber: `PRD-Supermaterial.md` bagian 5, 8.6, 9.4, 9.10.5, 9.11.6, 9.12.3, 9.16, 9.18 (SEO-07), 9.19, 9.20, 13, 14 (Tahap 5) dan tabel risiko.
Dokumentasi Google yang dirujuk: [Search Console API: Sitemaps](https://developers.google.com/webmaster-tools/v1/sitemaps), [Search Analytics: query](https://developers.google.com/webmaster-tools/v1/searchanalytics/query), [Usage limits](https://developers.google.com/webmaster-tools/limits).
Basis kode: branch `feat/aggregate-rating` (commit `001bf9d`).

Dokumen ini hanya berisi rencana. Belum ada kode yang diubah.

## 1. Daftar API

| # | API | Rujukan PRD | Cakupan | Arah |
|---|---|---|---|---|
| A | Search Console Sitemaps API (`sitemaps.submit`, `sitemaps.list`) | 9.4, 9.10.5 poin 5, 9.11.6, 9.16, SEO-07, 9.19 Tahap 5, 9.20 (pengganti WebSub), 14 Tahap 5 | **MVP** | Keluar ke Google |
| B | Search Console Search Analytics API (laporan Performance) | 9.10.5 (pemantauan berkala), 13 (metrik impresi dan klik) | **MVP** (usul: akhir Tahap 5 atau setelah peluncuran) | Masuk dari Google |
| C | Endpoint publik `GET /api/answers/{slug}` | 9.12.3, 9.19 Fase 2 | **Fase 2** | Keluar dari situs (publik) |
| D | API tersirat dan yang sengaja tidak dipakai | 5, 8.4, 8.6, 9.20, risiko | Lihat bagian 6 | — |

A dan B memakai kredensial Google yang sama, jadi fondasinya dibangun sekali (bagian 2).

## 2. Kondisi kode sekarang (celah yang ditutup)

| Area | Yang sudah ada | Celah |
|---|---|---|
| Sitemap | `SeoController` membuat `sitemap.xml` (indeks) dan pecahan per jenis (`products`, `categories`, `articles`, `pages`, `authors`, `images`), dinomori otomatis bila lebih dari 5000 URL. Isinya di-cache 1 jam per jenis. | Sitemap dirender saat diminta, bukan berkas statis. Tidak ada pemanasan cache. |
| Observer | Trait `TracksPublicUrl` (dipakai `Product`, `Category`, `Article`, `Page`, `Author`) mencatat redirect 301 saat slug berubah dan memanggil `SeoController::flushSitemaps()` pada `saved`/`deleted`. | Hanya mengosongkan cache secara sinkron. **Tidak ada antrean dan tidak ada panggilan ke Google.** Semua jenis dikosongkan, bukan hanya pecahan terkait. Penyimpanan draf ikut memicu. |
| Media dan varian | `ProductMedia` dan `ProductVariant` tidak memakai observer. | Gambar baru atau gambar yang dihapus tidak mengosongkan `sitemap-images.xml` sampai cache kedaluwarsa. |
| Terbit terjadwal | `Article::published()` memakai `published_at <= now()`. | Saat artikel terjadwal mulai tayang tidak ada event `saved`, jadi sitemap tidak diperbarui dan tidak dikirim ke Google. |
| Scheduler | `routes/console.php` hanya menjalankan `seo:flag-expired-prices` pukul 01:00 WIB. | Belum ada jadwal untuk Search Console. |
| Rute API | Tidak ada `routes/api.php`. `bootstrap/app.php` hanya mendaftarkan `web` dan `console`, tetapi `shouldRenderJsonWhen` sudah menangani `api/*`. | Berkas rute API belum ada. |
| Konfigurasi | `config/services.php` bawaan; `.env` sudah diabaikan git; `storage/app/private/` diabaikan git. | Belum ada kunci konfigurasi Google. |
| Antrean | `QUEUE_CONNECTION=database`, tabel `jobs` sudah ada. | Butuh worker (`queue:work`) di produksi. Bila hosting tidak mendukung worker permanen, pakai `schedule:run` + `queue:work --stop-when-empty`. |
| Paket | Guzzle tersedia lewat Laravel. Belum ada paket Google. | Lihat 2.1. |
| GA4 dan Meta Pixel | ID diatur di Pengaturan situs. Event `buy_click`, `campaign_view`, `campaign_click` dikirim dari browser (`resources/js/app.js`). | Tidak ada celah untuk MVP (lihat D1). |

### 2.1 Fondasi bersama: autentikasi Google

**Metode:** service account Google Cloud (server ke server, tanpa login pengguna).

1. Buat project Google Cloud, aktifkan **Google Search Console API**.
2. Buat service account dan unduh kunci JSON.
3. Di Search Console, tambahkan email service account sebagai pengguna properti dengan izin **Penuh** (Full). `sitemaps.submit` butuh izin tulis; izin Terbatas hanya cukup untuk membaca Performance.
4. Simpan kunci di server, **di luar repo**: `storage/app/private/google/search-console.json` (folder ini sudah diabaikan git) atau path absolut di luar folder aplikasi.

**`.env` (hanya contoh kunci di `.env.example`, tanpa nilai rahasia):**

```dotenv
GOOGLE_SEARCH_CONSOLE_ENABLED=false
GOOGLE_SEARCH_CONSOLE_SITE_URL=sc-domain:supermaterial.id
GOOGLE_SEARCH_CONSOLE_CREDENTIALS=storage/app/private/google/search-console.json
GOOGLE_SEARCH_CONSOLE_SUBMIT_DEBOUNCE=600
```

**`config/services.php`:**

```php
'google_search_console' => [
    'enabled' => env('GOOGLE_SEARCH_CONSOLE_ENABLED', false),
    'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL'),
    'credentials' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS'),
    'submit_debounce' => (int) env('GOOGLE_SEARCH_CONSOLE_SUBMIT_DEBOUNCE', 600),
],
```

- `enabled=false` di lokal, CI, dan staging, supaya staging tidak pernah mengirim sitemap ke properti produksi.
- `site_url` berformat `sc-domain:domain.tld` (properti domain) atau `https://domain.tld/` (properti awalan URL), sesuai jenis properti yang terdaftar.

**Token akses:** paket resmi `google/auth` (ringan, hanya untuk menukar kunci JSON menjadi access token OAuth2). Panggilan API tetap lewat `Http` Laravel, supaya `Http::fake()` bisa dipakai di tes. `google/apiclient` tidak dipakai karena terlalu besar untuk dua endpoint. Alternatif tanpa paket: tanda tangan JWT RS256 dengan `openssl_sign` (sekitar 40 baris). Pilihannya ada di bagian Keputusan.

**Service class:** `App\Services\Google\SearchConsoleClient`

- `accessToken()`: ambil token dengan scope `https://www.googleapis.com/auth/webmasters` (A) atau `.../webmasters.readonly` (B). Token di-cache sampai 60 detik sebelum `expires_in`.
- `submitSitemap(string $feedUrl)`: `PUT https://www.googleapis.com/webmasters/v3/sites/{siteUrl}/sitemaps/{feedpath}` (keduanya di-*urlencode*).
- `listSitemaps()`: `GET .../sites/{siteUrl}/sitemaps`.
- `searchAnalytics(array $body)`: `POST .../sites/{siteUrl}/searchAnalytics/query`.
- Semua permintaan memakai `Http::withToken()->timeout(15)->retry(3, 2000, when: 429 atau 5xx)`. Respons 401 memicu satu kali penyegaran token; 403 tidak di-retry (izin properti salah) dan dicatat sebagai error konfigurasi.
- Bila `enabled=false` atau kredensial tidak ada, method melempar `SearchConsoleDisabled` yang ditangkap job, sehingga tidak ada panggilan jaringan.

**Logging:** channel `google` terpisah di `config/logging.php` (`daily`, simpan 14 hari) berisi endpoint, status HTTP, durasi, dan pesan error. Token dan isi kunci tidak pernah ditulis ke log.

**Tabel audit `api_sync_logs`:** `id`, `service` (`gsc_sitemap` | `gsc_performance`), `action`, `target` (mis. URL sitemap), `status` (`success` | `failed` | `skipped`), `http_status`, `message`, `created_at`. Dipakai panel Filament untuk menampilkan pengiriman terakhir tanpa membuka log server. Baris lebih dari 90 hari dipangkas dengan `model:prune`.

## 3. API A: Search Console Sitemaps API (MVP)

### Tujuan

PRD 9.11.6 dan 9.16: setiap publikasi, pembaruan, atau penghapusan konten publik memicu antrean yang meregenerasi pecahan sitemap terkait dan mengirim ulang sitemap ke Google. SEO-07: sitemap "terkirim ke Google lewat API". 9.20: API ini menggantikan WebSub.

Catatan perilaku Google: ping sitemap (`/ping?sitemap=`) sudah dihentikan Google sejak akhir 2023, jadi Sitemaps API adalah satu-satunya jalur kirim terprogram. Mengirim ulang sitemap yang tidak berubah tidak mempercepat apa pun. Pengiriman perlu di-*debounce*, bukan dikirim pada setiap penyimpanan.

### Desain teknis

**Alur**

```
Model publik disimpan/dihapus (TracksPublicUrl)
  └─ tentukan jenis sitemap yang terdampak (products / images / …)
  └─ RegenerateSitemap::dispatch($types)->afterCommit()          (antrean)
        ├─ kosongkan dan panaskan cache pecahan terkait (render ulang)
        └─ SubmitSitemapToGoogle::dispatch()->delay(debounce)    (unik, antrean)
              └─ SearchConsoleClient::submitSitemap(url('/sitemap.xml'))
```

**1. Ubah `TracksPublicUrl`**

- Ganti panggilan sinkron `SeoController::flushSitemaps()` dengan `SitemapChanged::dispatch(static::SITEMAP_TYPES)`.
- Tiap model mendeklarasikan jenis terdampak: `Product` → `['products', 'images', 'pages']`, `Article` → `['articles', 'images', 'pages', 'authors']`, `Category` → `['categories', 'images']`, `Page` → `['pages']`, `Author` → `['authors']`.
- Abaikan penyimpanan yang tidak memengaruhi sitemap: dispatch hanya bila rekaman publik sebelum **atau** sesudah perubahan, dan salah satu kolom berikut berubah: `slug`, `noindex`, `canonical_url`, status atau tanggal terbit, kolom tanggal bermakna (`Article::MEANINGFUL_FIELDS`), dan gambar. Penghapusan rekaman publik selalu memicu.
- Tambahkan observer ringan pada `ProductMedia` dan `ProductVariant` yang men-*touch* jenis `images` (dan `products`) milik produk induknya.

**2. Job `App\Jobs\RegenerateSitemap`** (`ShouldQueue`, `ShouldBeUnique` per kombinasi jenis, `uniqueFor` 60 detik)

- Pindahkan pembentukan entri dari `SeoController::entries()` ke `App\Support\SitemapBuilder` supaya bisa dipanggil dari controller dan job.
- Untuk tiap jenis: `Cache::forget`, lalu `Cache::put` hasil render baru (tanpa TTL 1 jam; cache kini dibuang secara eksplisit). Controller tetap memakai `Cache::remember` sebagai cadangan.
- Setelah selesai, dispatch `SubmitSitemapToGoogle`.

**3. Job `App\Jobs\SubmitSitemapToGoogle`** (`ShouldQueue`, `ShouldBeUnique`, `uniqueFor` = `submit_debounce`)

- Di-dispatch dengan `delay(submit_debounce)`. Karena unik, sepuluh produk yang disimpan dalam 10 menit menghasilkan satu pengiriman.
- Yang dikirim: **indeks** `sitemap.xml`. Google membaca ulang semua pecahan dari indeks, dan pecahan bernomor baru (mis. `sitemap-products-2.xml`) otomatis ikut tanpa perlu didaftarkan satu per satu. Bila keputusan nomor 3 memilih kirim per pecahan, job mengirim indeks ditambah pecahan terdampak.
- `tries = 5`, `backoff = [60, 300, 900, 3600]`. 429 dan 5xx di-retry; 400, 403, dan 404 langsung gagal (`fail()`), dicatat ke `api_sync_logs`, dan memunculkan notifikasi database Filament ke admin.
- Bila API nonaktif: catat `skipped` lalu selesai tanpa error.

**4. Terbit terjadwal**

Perintah `seo:sync-scheduled-content` tiap 15 menit mencari rekaman dengan `published_at` di antara eksekusi terakhir dan sekarang (stempel waktu disimpan di cache), lalu men-dispatch `RegenerateSitemap` untuk jenisnya. Ini menutup celah artikel terjadwal yang tayang tanpa event `saved`.

**5. Scheduler (`routes/console.php`)**

```php
Schedule::command('seo:sync-scheduled-content')->everyFifteenMinutes();
Schedule::job(new SubmitSitemapToGoogle)->weeklyOn(1, '03:00')->timezone('Asia/Jakarta'); // jaring pengaman
Schedule::command('seo:sitemap-status')->dailyAt('03:30')->timezone('Asia/Jakarta');     // sitemaps.list
```

`seo:sitemap-status` memanggil `sitemaps.list` dan menyimpan `lastSubmitted`, `lastDownloaded`, `isPending`, `warnings`, `errors`, dan jumlah `submitted`/`indexed` per jenis konten ke tabel `search_console_sitemaps`. Admin bisa melihat apakah Google benar-benar mengunduh sitemap.

**6. Rate limit**

Kuota Search Console (per properti, per pengguna, per project) jauh di atas kebutuhan, karena debounce membatasi pengiriman menjadi paling banyak sekitar 6 per jam. Sebagai pengaman, `RateLimiter` tambahan bernama `gsc-submit` membatasi 30 pengiriman per jam.

### Perubahan skema

- `api_sync_logs` (lihat 2.1).
- `search_console_sitemaps`: `id`, `path` (unik), `last_submitted_at`, `last_downloaded_at`, `is_pending`, `is_sitemaps_index`, `warnings`, `errors`, `contents` (json: jenis, submitted, indexed), `synced_at`.

### Pengaturan di Filament

Halaman baru **Pengaturan → Search Console** (khusus admin):

- Status koneksi: aktif atau nonaktif (dibaca dari `config`, tidak dapat diubah dari panel karena kredensial ada di `.env`), properti yang dipakai, dan email service account (dibaca dari berkas kunci; kunci privat tidak ditampilkan).
- Tombol **Uji koneksi** (`sitemaps.list`), **Kirim sitemap sekarang** (dispatch tanpa delay), dan **Muat ulang status**.
- Tabel status sitemap dari `search_console_sitemaps` dan 20 baris terakhir `api_sync_logs`.
- Bila pengiriman gagal berturut-turut 3 kali, muncul banner peringatan di dasbor admin.

### Kriteria penerimaan

1. Menerbitkan, mengubah slug, menandai `noindex`, atau menghapus produk/artikel/kategori/halaman/penulis publik menghasilkan tepat satu job `RegenerateSitemap` (setelah transaksi commit) dan paling banyak satu pengiriman ke Google per jendela debounce.
2. Menyimpan draf atau mengubah kolom non-SEO (mis. `views_count`) tidak memicu job.
3. Mengunggah atau menghapus gambar produk memperbarui `sitemap-images.xml` tanpa menunggu cache kedaluwarsa.
4. Artikel terjadwal masuk `sitemap-articles.xml` dan dikirim ke Google paling lambat 15 menit + debounce setelah `published_at`.
5. Permintaan ke Google memakai `PUT` ke `.../sites/{siteUrl}/sitemaps/{feedpath}` yang di-*encode*, dengan token Bearer.
6. Dengan `GOOGLE_SEARCH_CONSOLE_ENABLED=false` tidak ada permintaan jaringan dan tidak ada error; log mencatat `skipped`.
7. Kegagalan 403 tercatat sekali, tidak di-retry, dan admin menerima notifikasi.
8. SEO-07 lulus: slug berubah → 301, sitemap terpecah, terdaftar di `robots.txt`, dan terkirim lewat API (dibuktikan dengan `api_sync_logs` di staging yang memakai properti uji).

### Tes Pest (tanpa panggilan nyata ke Google)

`tests/Feature/SearchConsoleSitemapTest.php`:

- `Queue::fake()`: menerbitkan produk men-dispatch `RegenerateSitemap` dengan jenis `products`, `images`, `pages`; menyimpan draf tidak men-dispatch apa pun.
- `Queue::fake()`: lima produk disimpan berturut-turut hanya menghasilkan satu `SubmitSitemapToGoogle` (keunikan).
- `Http::fake(['oauth2.googleapis.com/token' => [...], 'www.googleapis.com/webmasters/*' => Http::response(null, 204)])` dengan kunci RSA uji yang dibuat saat tes (`openssl_pkey_new`) di `storage/framework/testing`: job mengirim `PUT` ke URL yang benar; `Http::assertSent` memeriksa method, path ter-*encode*, dan header `Authorization`.
- `Http::fake` 429 lalu 204: job berhasil setelah retry. 403: job gagal tanpa retry, `api_sync_logs` berisi `failed`, notifikasi terkirim.
- `config(['services.google_search_console.enabled' => false])`: `Http::assertNothingSent()`.
- `Http::preventStrayRequests()` di `tests/Pest.php` supaya tes mana pun yang tidak sengaja memanggil jaringan langsung gagal.
- Regresi: tes sitemap lama di `SeoTest.php` tetap lulus setelah logika dipindah ke `SitemapBuilder`.
- Terbit terjadwal: `travelTo` melewati `published_at`, jalankan `seo:sync-scheduled-content`, lalu pastikan job ter-dispatch.

### Risiko

| Risiko | Mitigasi |
|---|---|
| Worker antrean tidak berjalan di hosting, sehingga job menumpuk | Cek kesehatan di halaman Search Console (jumlah job tertunda di tabel `jobs`); dokumentasi deploy memuat Supervisor atau cron `queue:work --stop-when-empty` |
| Staging mengirim sitemap staging ke properti produksi | `enabled=false` di staging; job memeriksa `url('/')` cocok dengan `site_url` sebelum mengirim |
| Izin service account kurang (403) | Langkah setup mewajibkan izin Penuh; tombol Uji koneksi |
| Kunci JSON bocor | Disimpan di luar repo, izin berkas 600, rotasi kunci tiap 90 hari, tidak pernah dicatat ke log |
| Render sitemap besar memakan memori di job | `SitemapBuilder` memakai `lazy()`/`cursor()` untuk produk; target di bawah 128 MB untuk 50.000 URL |
| Ekspektasi "kirim = langsung terindeks" | Google tidak menjamin waktu perayapan; ini dijelaskan di panel |

## 4. API B: Search Console Search Analytics API (MVP, dasbor internal)

### Tujuan

PRD 9.10.5: tarik tayangan, klik, dan posisi rata-rata otomatis untuk dasbor internal. PRD 13: "Impresi dan klik Google Search Console" serta "Performa halaman berdasarkan jenis konten".

### Desain teknis

**Job `App\Jobs\PullSearchConsolePerformance`** (`ShouldQueue`, `ShouldBeUnique`)

- Parameter: rentang tanggal. Default: 5 hari terakhir hingga 3 hari lalu. Data Search Console tertunda 2–3 hari dan baru final setelahnya, jadi rentang ini ditarik ulang dan di-*upsert*.
- Tiga kueri `searchAnalytics.query` per hari, `type=web`, `dataState=final`:
  1. Dimensi `date`: total harian situs.
  2. Dimensi `date`, `page`: per halaman.
  3. Dimensi `date`, `query`: per kata kunci.
- Paginasi `rowLimit=25000` + `startRow` sampai baris kosong. Batas aman 5 halaman (125.000 baris) per kueri per hari.
- Setiap baris di-*upsert* dengan kunci unik (tanggal + dimensi).
- `page` dari Google berupa URL lengkap; disimpan sebagai `page_path` dan dipetakan ke `content_type` (`product`, `article`, `category`, `page`, `author`, `other`) dari pola rute, plus `content_id` bila slug ditemukan. Pemetaan memakai tabel `redirects` juga, supaya URL lama ikut terhubung ke rekaman sekarang.
- Retry: `tries=3`, backoff 5 dan 30 menit untuk 429/5xx.

**Perintah**

- `seo:gsc-pull {--from=} {--to=}` untuk tarik manual dan *backfill*. Backfill awal: 16 bulan (batas riwayat Google), dipecah per 7 hari dalam batch antrean supaya tidak melewati kuota.
- Scheduler: `Schedule::command('seo:gsc-pull')->dailyAt('05:00')->timezone('Asia/Jakarta');`

**Kuota**

Batas Search Analytics adalah per properti dan per pengguna per menit, dan kueri berdimensi `page`/`query` dengan rentang panjang paling mahal. Job memproses satu hari per kueri dan menjeda 1 detik antar halaman, jauh di bawah kuota untuk situs seukuran ini.

### Perubahan skema

- `gsc_daily`: `date` (unik), `clicks`, `impressions`, `ctr`, `position`.
- `gsc_pages`: `date`, `page_path`, `content_type`, `content_id` (nullable), `clicks`, `impressions`, `ctr`, `position`; unik (`date`, `page_path`); indeks (`content_type`, `date`).
- `gsc_queries`: `date`, `query` (string 500), `clicks`, `impressions`, `ctr`, `position`; unik (`date`, `query`).
- Kueri anonim yang disembunyikan Google tidak muncul di `gsc_queries`, sehingga total per kueri lebih kecil dari `gsc_daily`. Dasbor menampilkan catatan ini.
- Retensi: usul simpan semua (`gsc_daily`, `gsc_pages`) dan pangkas `gsc_queries` lebih dari 24 bulan.

### Pengaturan dan tampilan di Filament

Halaman **Laporan → Performa Search Console** (admin dan editor):

- Filter rentang tanggal (7/28/90 hari, kustom) dan jenis konten.
- Kartu statistik: klik, tayangan, CTR, posisi rata-rata, dengan perbandingan periode sebelumnya.
- Grafik tren harian klik dan tayangan (pola sama dengan widget `ClickTrend`).
- Tabel halaman teratas: judul rekaman (bila terpetakan), klik, tayangan, CTR, posisi, dan **klik kanal pembelian** dari `click_events` untuk halaman yang sama. Ini menghubungkan metrik PRD 13 (organik → klik beli).
- Tabel kata kunci teratas, dengan filter "posisi 4–10" sebagai peluang.
- Ekspor CSV (sama dengan laporan klik di 8.6).
- Posisi rata-rata untuk agregat dihitung **berbobot tayangan** (`SUM(position × impressions) / SUM(impressions)`), bukan rata-rata biasa.
- Kolom kecil di form produk dan artikel: klik dan tayangan 28 hari terakhir.
- Tanggal sinkron terakhir dan tombol **Tarik ulang** (khusus admin).

### Kriteria penerimaan

1. Setelah `seo:gsc-pull` berjalan, `gsc_daily` berisi satu baris per hari dengan angka sama dengan laporan Performance di Search Console untuk rentang dan filter yang sama (selisih nol pada `dataState=final`).
2. Menjalankan ulang rentang yang sama tidak menggandakan baris.
3. Halaman produk dan artikel terpetakan ke rekamannya, termasuk URL lama yang sudah di-redirect.
4. Posisi rata-rata agregat berbobot tayangan.
5. Dasbor terbuka di bawah 1 detik untuk rentang 90 hari (memakai indeks dan agregasi SQL).
6. Tanpa kredensial atau dengan API nonaktif, halaman menampilkan status "belum terhubung", bukan error.

### Tes Pest

`tests/Feature/SearchConsolePerformanceTest.php`:

- `Http::fake` dengan respons `rows` contoh (dua halaman, dua kueri): data tersimpan di tiga tabel dengan pemetaan `content_type` yang benar.
- Paginasi: respons pertama 25.000 baris (dibuat di tes), respons kedua kosong; `Http::assertSentCount` sesuai.
- *Upsert*: jalankan dua kali, jumlah baris tetap.
- Badan permintaan: `Http::assertSent` memeriksa `startDate`, `endDate`, `dimensions`, `dataState`, dan `rowLimit`.
- Posisi berbobot: dua baris dengan tayangan berbeda menghasilkan rata-rata yang benar.
- Halaman Filament: admin dan editor bisa membuka, penulis tidak (pola `AdminPanelTest`).
- Pemetaan URL lama lewat `redirects`.

### Risiko

| Risiko | Mitigasi |
|---|---|
| Zona waktu: tanggal Search Console memakai waktu Pasifik (PT), bukan WIB | Disimpan apa adanya sebagai tanggal PT, diberi label di dasbor; tidak digabung per jam dengan `click_events` |
| Data tertunda 2–3 hari | Tarik ulang rentang 3–5 hari terakhir setiap hari; label "data hingga {tanggal}" |
| Volume `gsc_queries` membesar | Batas baris per hari dan retensi 24 bulan |
| Properti domain vs awalan URL menghasilkan angka berbeda | Satu properti ditetapkan di `.env` dan ditampilkan di dasbor |

## 5. API C: Endpoint publik `/api/answers/{slug}` (Fase 2)

### Tujuan

PRD 9.12.3: kolom `short_answer` dan `key_facts` pada artikel, serta endpoint yang mengekspor judul, ringkasan, fakta kunci, dan tautan sumber. Tujuannya memudahkan sistem pencari generatif mengutip. Manfaatnya belum terjamin dan dievaluasi dari data setelah MVP, jadi bagian ini dikerjakan paling akhir.

### Desain teknis

**Rute**

- Buat `routes/api.php` dan daftarkan `api: __DIR__.'/../routes/api.php'` di `bootstrap/app.php`. Tidak perlu `php artisan install:api` (Sanctum), karena endpoint publik dan hanya-baca.
- `Route::get('/answers/{slug}', AnswerController::class)->middleware('throttle:answers')->name('api.answers.show');`
- Rate limiter `answers`: 60 permintaan per menit per IP, didefinisikan di `AppServiceProvider::boot()`.
- Opsional: `GET /api/answers` (daftar slug + `updated_at`, berpaginasi) supaya perayap bisa menemukan semua jawaban. Lihat Keputusan.

**Controller `App\Http\Controllers\Api\AnswerController`** + `App\Http\Resources\AnswerResource`

- Hanya artikel `published()`, tidak `noindex`, dan `short_answer` terisi. Selain itu 404 JSON.
- Slug lama di tabel `redirects` → 301 ke `/api/answers/{slug-baru}`.
- Bentuk respons:

```json
{
  "data": {
    "slug": "cara-memilih-baja-ringan",
    "title": "Cara Memilih Baja Ringan untuk Atap Rumah",
    "short_answer": "Pilih baja ringan dengan ketebalan minimal 0,75 mm ...",
    "key_facts": ["Ketebalan rangka utama 0,75–1,00 mm", "..."],
    "url": "https://supermaterial.id/artikel/cara-memilih-baja-ringan",
    "sources": [{"title": "SNI 8399:2017", "url": "https://..."}],
    "author": {"name": "Budi Santoso", "url": "https://supermaterial.id/penulis/budi-santoso"},
    "published_at": "2026-10-01T08:00:00+07:00",
    "modified_at": "2026-10-15T10:00:00+07:00",
    "language": "id-ID"
  }
}
```

- `url` = URL kanonis artikel (selalu ada). `sources` = rujukan eksternal opsional (lihat Keputusan nomor 7).
- Header: `Cache-Control: public, max-age=3600`, `ETag` dari `modified_at`, `Content-Language: id`, dan `X-Robots-Tag: noindex` supaya JSON tidak bersaing dengan halaman artikel di hasil pencarian (lihat Keputusan nomor 8). CORS terbuka untuk `GET` (`Access-Control-Allow-Origin: *`).
- Cache respons dibuang oleh observer `TracksPublicUrl` artikel.

**Konsistensi dengan halaman**

Sesuai 9.12.1 (markup = konten yang tampil), `short_answer` dan `key_facts` juga ditampilkan di halaman artikel sebagai kotak "Jawaban singkat" dan "Fakta kunci" di atas isi. Endpoint tidak berisi informasi yang tidak terlihat di halaman.

### Perubahan skema

Migration `add_answer_fields_to_articles_table`:

- `short_answer` (text, nullable; validasi 40–320 karakter, teks polos).
- `key_facts` (json, nullable; larik string, maks. 8 butir, masing-masing maks. 200 karakter).
- `sources` (json, nullable; larik `{title, url}`, maks. 10). Hanya bila keputusan nomor 7 disetujui.

### Pengaturan di Filament

- `ArticleResource`: bagian baru **Jawaban singkat (AI/Answer)** berisi `Textarea` `short_answer` dengan penghitung karakter, `Repeater` `key_facts`, dan `Repeater` `sources` (judul + URL, divalidasi `ExternalUrl`).
- Kolom tabel artikel "Punya jawaban" (ikon) dan filter.
- Skor kelengkapan SEO (`SeoScore`) menambah pemeriksaan opsional "jawaban singkat terisi" untuk artikel bertipe panduan. Pemeriksaan ini tidak menurunkan skor artikel lain.
- Pengaturan situs: toggle **Aktifkan endpoint jawaban** (bawaan mati), supaya fitur bisa dimatikan tanpa deploy.

### Kriteria penerimaan

1. `GET /api/answers/{slug}` untuk artikel terbit ber-`short_answer` mengembalikan 200 dengan bentuk JSON di atas.
2. Draf, terjadwal, `noindex`, artikel tanpa `short_answer`, atau toggle mati → 404 JSON.
3. Slug lama → 301 ke slug baru.
4. Isi `short_answer` dan `key_facts` di JSON sama persis dengan yang tampil di halaman artikel.
5. Lebih dari 60 permintaan per menit dari satu IP → 429.
6. Endpoint tidak masuk sitemap dan tidak terblokir `robots.txt` (cukup `X-Robots-Tag: noindex`).

### Tes Pest

`tests/Feature/AnswerApiTest.php`:

- 200 dan struktur `assertJsonStructure` untuk artikel valid; `assertJsonPath` untuk `url` kanonis.
- 404 untuk draf, terjadwal, `noindex`, tanpa `short_answer`, dan toggle mati.
- 301 untuk slug lama.
- Header `X-Robots-Tag`, `Cache-Control`, dan `ETag`; 304 untuk `If-None-Match` yang cocok.
- Rate limit: 61 permintaan → 429.
- Halaman artikel menampilkan `short_answer` dan `key_facts` yang sama.
- Validasi form Filament: panjang `short_answer`, jumlah `key_facts`, URL `sources`.

### Risiko

| Risiko | Mitigasi |
|---|---|
| Manfaat bagi kutipan AI tidak terukur | Catat `api_answer_hits` ringkas (tanggal, slug, user agent diringkas, indikator bot via `BotDetector`) untuk evaluasi; tanpa IP mentah |
| Konten ganda dengan halaman artikel | `X-Robots-Tag: noindex` dan `url` kanonis di respons |
| Beban editor bertambah | Opsional per artikel; diprioritaskan untuk artikel panduan dengan trafik tertinggi (dari API B) |
| Scraping massal | Rate limit dan cache |

## 6. API D: tersirat, sudah tercakup, atau sengaja tidak dipakai

| # | Integrasi | Rujukan PRD | Status di kode | Rekomendasi |
|---|---|---|---|---|
| D1 | **GA4** (event klik beli dan kampanye) | 8.6 lapisan 2, 8.9, 14 Tahap 5 | Sudah ada: `gtag.js` dari ID di Pengaturan situs, event `buy_click`, `campaign_view`, `campaign_click` | Tidak perlu API server untuk MVP. GA4 Measurement Protocol (kirim event dari server saat `/go/` dipanggil) **tidak disarankan**: pencatatan server `click_events` sudah menjadi sumber utama dan PRD meminta pencegahan hitungan ganda. GA4 Data API untuk menarik data ke dasbor juga tidak diminta PRD. |
| D2 | **Meta Pixel** | 8.6 | Sudah ada (opsional dari Pengaturan) | Conversions API tidak diperlukan; PRD hanya menyebut Pixel opsional untuk kampanye. |
| D3 | **WhatsApp** | 8.4, 8.11.4.7 | Sudah ada: tautan `wa.me/{nomor}?text=` dan `api.whatsapp.com/send` (tautan klik-untuk-chat, bukan API) | WhatsApp Business/Cloud API **tidak dibutuhkan**. PRD hanya membuka chat dengan pesan otomatis, dan bagian 8.6 menyatakan sistem tidak melacak apakah pesan terkirim. Usul kecil (di luar rencana API): satukan dua format tautan ke `wa.me`. |
| D4 | **Marketplace (Shopee, Tokopedia, Lazada, TikTok Shop)**: sinkron stok/harga | 5 (bukan tujuan MVP), 8.11.12 | Tidak ada; tautan diisi admin | **Tidak dikerjakan.** Sinkronisasi stok dan harga secara eksplisit dikeluarkan dari MVP. Bila kelak diminta, butuh akun Open Platform tiap marketplace (Shopee Open Platform, Tokopedia/TikTok Shop Partner) dan PRD tersendiri. |
| D5 | **Pemeriksaan tautan marketplace berkala** | Tabel risiko ("pemeriksaan tautan berkala"), 13 ("tautan pembelian yang rusak") | Tidak ada | Bukan API pihak ketiga, tetapi panggilan HTTP keluar. Usul (MVP, kecil): perintah `links:check` mingguan memanggil `HEAD`/`GET` ke `product_links.url` aktif (timeout 10 detik, 1 permintaan per detik per domain, user agent jujur), menyimpan `last_checked_at`, `last_status`, `is_broken` di `product_links`, dan menampilkan filter "Tautan rusak" di Filament. Tes dengan `Http::fake`. Risiko: marketplace sering mengembalikan 200 untuk produk yang sudah dihapus atau memblokir bot, jadi hasilnya hanya indikasi. |
| D6 | **Search Console URL Inspection API** | 9.10.5, 9.18 (URL Inspection dipakai manual); 13 ("halaman terindeks dibandingkan halaman yang layak diindeks") | Tidak ada | Opsional, Fase 2. Bisa mengisi metrik "terindeks vs layak indeks" otomatis untuk sampel halaman (kuota sekitar 2.000 inspeksi per hari per properti). Memakai kredensial yang sama dengan A dan B. API ini tidak bisa meminta crawl ulang; tombol "Minta pengindeksan" tetap manual di Search Console. |
| D7 | **Google Indexing API** | — | — | **Jangan dipakai.** Google hanya mengizinkannya untuk `JobPosting` dan `BroadcastEvent`; produk dan artikel tidak memenuhi syarat. |
| D8 | **IndexNow** (Bing, Yandex) | Tidak disebut | — | Di luar PRD. Bisa ditambahkan murah (satu `POST` ke `api.indexnow.org` di job yang sama dengan A) bila trafik Bing dianggap penting. Lihat Keputusan. |
| D9 | **Google Merchant Center** (umpan produk) | 9.19 Fase 3 | — | Di luar cakupan. Catatan untuk Fase 3: Content API for Shopping sedang digantikan **Merchant API**, jadi rencana Fase 3 sebaiknya langsung memakai Merchant API. |
| D10 | **WebSub** | 9.20 | — | Tidak termasuk MVP, digantikan A. |

## 7. Urutan pengerjaan

| Langkah | Pekerjaan | Bergantung pada | Perkiraan |
|---|---|---|---|
| 1 | Fondasi: `config/services.php`, `.env.example`, channel log `google`, `SearchConsoleClient` + token, `Http::preventStrayRequests()` di tes, tabel `api_sync_logs` | Keputusan 1 dan 2 | 0,5 hari |
| 2 | Refaktor `SeoController` → `SitemapBuilder` tanpa mengubah output (tes `SeoTest` tetap hijau) | — | 0,5 hari |
| 3 | API A: event/observer selektif, observer media dan varian, job `RegenerateSitemap` dan `SubmitSitemapToGoogle`, `seo:sync-scheduled-content` | 1, 2 | 1 hari |
| 4 | API A: `seo:sitemap-status`, tabel `search_console_sitemaps`, halaman Filament Search Console, notifikasi gagal | 3 | 0,5 hari |
| 5 | Setup di Google Cloud dan Search Console oleh pengguna, lalu uji di staging dengan properti uji (SEO-07) | 1–4, akses pengguna | — |
| 6 | API B: migration `gsc_*`, job tarik + pemetaan URL, `seo:gsc-pull`, backfill | 1 | 1 hari |
| 7 | API B: halaman laporan Filament, widget, ekspor CSV, kolom di form produk/artikel | 6 | 1 hari |
| 8 | D5: `links:check` (bila disetujui) | — | 0,5 hari |
| 9 | Fase 2, API C: migration, field Filament, tampilan di halaman artikel, `routes/api.php`, controller, resource, toggle | Setelah data MVP dievaluasi | 1 hari |
| 10 | Fase 2, D6: URL Inspection (bila disetujui) | 1 | 0,5 hari |

Langkah 1–5 masuk Tahap 5 PRD. Langkah 6–7 bisa menyusul setelah peluncuran, karena dasbor butuh data beberapa minggu supaya berguna.

## 8. Risiko lintas API

- **Satu kredensial untuk semua**: bila kunci dicabut, A dan B berhenti bersamaan. Status koneksi di panel dan notifikasi gagal menjadi deteksi dini.
- **Ketergantungan pada worker antrean**: tanpa worker, A tidak pernah mengirim. Perlu dipastikan di dokumen deploy sebelum Tahap 6.
- **Perubahan API Google**: endpoint `webmasters/v3` masih berlaku, tetapi Google memindahkan sebagian layanan ke `searchconsole.googleapis.com/v1`. `SearchConsoleClient` mengisolasi URL dasar di satu tempat.
- **Tes tidak boleh menyentuh jaringan**: `Http::preventStrayRequests()` global dan kunci RSA uji yang dibuat saat tes; tidak ada kunci asli di repo atau di CI.

## 9. Keputusan yang perlu diambil pengguna

1. **Jenis properti Search Console**: properti domain (`sc-domain:…`, disarankan, mencakup http/https dan subdomain) atau awalan URL (`https://…/`)? Apa domain produksinya?
2. **Cara mendapatkan token**: paket resmi `google/auth` (disarankan) atau tanda tangan JWT sendiri tanpa dependensi baru?
3. **Yang dikirim ke Google**: hanya indeks `sitemap.xml` (disarankan) atau indeks ditambah pecahan yang berubah?
4. **Jeda debounce pengiriman**: 10 menit (disarankan), 30 menit, atau 1 jam?
5. **Kapan dasbor Performance (API B) dikerjakan**: bersama Tahap 5 sebelum peluncuran, atau 2–4 minggu setelah peluncuran saat data sudah ada?
6. **Siapa yang boleh melihat dasbor Performance**: admin saja, atau admin dan editor (disarankan)?
7. **`sources` di endpoint jawaban (Fase 2)**: cukup URL artikel sendiri, atau tambah kolom rujukan eksternal (SNI, situs pabrikan) yang diisi editor?
8. **Indeksasi endpoint jawaban**: diberi `X-Robots-Tag: noindex` (disarankan, menghindari konten ganda) atau dibiarkan dapat diindeks?
9. **Pemeriksaan tautan marketplace (D5)**: dikerjakan di MVP atau ditunda?
10. **IndexNow untuk Bing (D8)**: ditambahkan atau tidak?
11. **URL Inspection API (D6)**: dikerjakan di Fase 2 atau tidak sama sekali?
12. **Hosting antrean**: apakah hosting produksi mendukung proses permanen (Supervisor) untuk `queue:work`, atau hanya cron?
