# Rencana: Review Snippet (Review & AggregateRating) Halaman Produk

Sumber: [Google Search Central, Review snippet structured data](https://developers.google.com/search/docs/appearance/structured-data/review-snippet) (diperbarui 2026-09-08).
Terkait: PRD-Supermaterial.md bagian 8.11.4.2 dan 9.10.2; branch `feat/aggregate-rating` (commit `8f8ad65`).

## 1. Ringkasan aturan Google yang relevan

### Syarat sumber data (penentu utama)

| Aturan Google | Dampak ke Supermaterial |
|---|---|
| Jangan menggabungkan ulasan atau rating dari situs lain | Rekap Shopee/Tokopedia (`rating_value`, `rating_count` yang diisi admin) **tidak boleh** masuk JSON-LD |
| Jangan memasang ulasan palsu, atau ulasan berimbalan (diskon, voucher, produk gratis) yang tidak diungkapkan dengan jelas | Ulasan harus dari pengalaman nyata; bila ada insentif, wajib diberi label |
| Konten ulasan harus terlihat jelas oleh pengunjung di halaman yang sama | Rata-rata rating dan jumlahnya wajib tampil di halaman produk; bila `review` dipasang, teks ulasan dan bintangnya juga harus tampil |
| Disarankan hanya menerima rating yang disertai komentar dan nama penulis | Form ulasan mewajibkan nama dan komentar |
| Ulasan harus tentang satu item spesifik, bukan kategori atau daftar | Hanya di halaman detail produk; tidak di halaman kategori, katalog, atau beranda |
| Bila memasang beberapa `review`, sertakan juga `aggregateRating` dari ulasan-ulasan itu | `aggregateRating` dihitung dari ulasan yang sama |
| Untuk `LocalBusiness`/`Organization`: entitas yang mengontrol ulasan tentang dirinya tidak layak mendapat bintang (*self-serving*) | Jangan pasang rating di `Organization`, `WebSite`, atau `LocalBusiness` Supermaterial |

Catatan: aturan "rating harus langsung dari pengguna, jangan dikompilasi editor" di dokumentasi Google secara tertulis berlaku untuk local business/organization. Untuk Product, larangan utamanya adalah mengambil rating dari situs lain dan ulasan palsu. Hasilnya sama: satu-satunya sumber yang aman adalah ulasan yang dikirim pembeli langsung di situs ini.

### Properti `AggregateRating` (bersarang di `Product`/`ProductGroup`)

| Properti | Status | Catatan |
|---|---|---|
| `ratingValue` | Wajib | Angka dengan titik desimal (`4.4`, bukan `4,4`) |
| `ratingCount` / `reviewCount` | Wajib minimal satu | `ratingCount` = jumlah rating di situs ini; `reviewCount` = jumlah orang yang menulis ulasan |
| `itemReviewed` | Tidak perlu | Karena bersarang; nama diambil dari `Product.name` |
| `bestRating` / `worstRating` | Opsional | Default 5 dan 1, jadi dihilangkan untuk skala 1–5 |

### Properti `Review` (opsional, bersarang lewat `review`)

| Properti | Status | Catatan |
|---|---|---|
| `author` (`Person`) | Wajib | Nama valid, kurang dari 100 karakter |
| `reviewRating.ratingValue` | Wajib | Skala 1–5 |
| `datePublished` | Disarankan | Format ISO 8601 |
| `reviewBody` | Opsional | Teks ulasan yang tampil di halaman |

## 2. Kondisi sekarang

- Kolom `products.rating_value`, `rating_count`, dan `rating_updated_at` diisi manual oleh admin; nilai di seeder berupa rekap marketplace.
- Branch `feat/aggregate-rating` sudah memasang `aggregateRating` dari kolom tersebut. **Secara teknis benar, tetapi sumber datanya melanggar pedoman** (rating dari situs lain).
- Folder utama (`feat/seo-prd`) belum memasang `AggregateRating` sama sekali, jadi saat ini belum ada risiko yang tayang.

## 3. Rencana

### Tahap 1: Amankan markup yang ada (kecil, di `feat/aggregate-rating`) — selesai

Diterapkan dengan kolom `products.rating_source` (nama marketplace). `aggregateRating` dihapus dari JSON-LD, bukan dibiarkan mengembalikan `null`; Tahap 2 akan memasangnya kembali dari ulasan situs.

1. Bedakan dua sumber rating:
   - **Rating marketplace** (kolom lama): hanya tampilan, diberi label sumber, misalnya "4.9 · 1240 penilaian di Shopee". Tidak pernah masuk JSON-LD.
   - **Rating situs** (Tahap 2): satu-satunya sumber `aggregateRating`.
2. `Product::aggregateRatingSchema()` mengembalikan `null` sampai Tahap 2 selesai.
3. Ganti label form admin menjadi "Rating Marketplace (hanya tampilan, tidak masuk structured data)".
4. Perbarui tes: rating marketplace tampil di halaman tetapi `AggregateRating` tidak ada di JSON-LD.

### Tahap 2: Fitur ulasan pembeli di situs

**Data**

- Tabel `product_reviews`: `id`, `product_id`, `author_name` (maks. 100 karakter), `rating` (tinyint 1–5), `body` (teks, wajib), `is_incentivized` (bool), `status` (`pending` | `approved` | `rejected`), `published_at`, `ip_hash`, `created_at`.
- Kolom cache di `products`: `site_rating_value` (decimal 3,1) dan `site_rating_count` (int). Dihitung ulang dari ulasan berstatus `approved` setiap kali status ulasan berubah (observer).

**Pengunjung**

- Form "Tulis Ulasan" di halaman produk: nama, bintang 1–5, komentar (wajib), dan centang "saya menerima imbalan untuk ulasan ini" bila relevan.
- Perlindungan spam: rate limit per IP, honeypot, dan panjang minimal komentar.
- Ulasan masuk berstatus `pending`; tidak tampil sebelum disetujui.

**Admin (Filament)**

- Resource `ProductReview`: daftar ulasan, filter status, aksi Setujui/Tolak (massal dan per baris).
- Admin hanya memoderasi; admin tidak bisa mengubah bintang atau isi ulasan.

**Halaman produk**

- Ringkasan rating situs (rata-rata, jumlah, sebaran bintang) di dekat judul produk.
- Bagian "Ulasan Pembeli" menampilkan ulasan yang disetujui: nama, bintang, tanggal, komentar, dan label "Ulasan berimbalan" bila `is_incentivized`.

**JSON-LD**

- `aggregateRating`: `ratingValue` = `site_rating_value` (satu desimal, titik), `ratingCount` = `site_rating_count`, `reviewCount` = jumlah ulasan yang disetujui dan berisi komentar.
- `review`: maksimal 5 ulasan terbaru yang tampil di halaman, dengan `author`, `reviewRating`, `datePublished`, `reviewBody`.
- Tidak di-render bila `site_rating_count == 0`.
- Ambang minimum (usul): render `aggregateRating` hanya jika ada minimal 3 ulasan yang disetujui, supaya bintang tidak berasal dari satu ulasan saja. Ini kebijakan internal, bukan syarat Google.

## 4. Kriteria penerimaan

1. `ratingValue`, `ratingCount`, dan `reviewCount` di JSON-LD sama persis dengan yang tampil di halaman.
2. Nilai dihitung dari tabel `product_reviews` berstatus `approved`, bukan dari input admin atau data marketplace.
3. Rating marketplace tidak pernah muncul di JSON-LD.
4. Bila tidak ada ulasan yang disetujui (atau di bawah ambang), `aggregateRating` dan `review` tidak di-render.
5. Rating spesifik per produk; tidak ada `AggregateRating` di beranda, kategori, `Organization`, atau `WebSite`.
6. Setiap `review` di JSON-LD juga tampil di halaman beserta nama, bintang, dan teksnya.
7. Lolos [Rich Results Test](https://search.google.com/test/rich-results) tanpa error untuk minimal satu halaman produk yang sudah online.

## 5. Tes Pest

- Ulasan `pending` atau `rejected` tidak memengaruhi rata-rata dan tidak tampil.
- Menyetujui ulasan memperbarui `site_rating_value` dan `site_rating_count`.
- Konsistensi JSON-LD dan tampilan (rata-rata, jumlah, isi `review`).
- Rating marketplace tampil tetapi tidak ada di JSON-LD.
- Di bawah ambang atau nol ulasan: tidak ada `AggregateRating` maupun `Review`.
- Validasi form: rating 1–5, nama maksimal 100 karakter, komentar wajib, rate limit.

## 6. Setelah tayang

- Pantau laporan *Review snippets* di Search Console untuk item valid dan tidak valid.
- Gunakan URL Inspection pada beberapa produk, lalu minta crawl ulang.
- Perlu waktu beberapa hari sampai minggu sebelum bintang mungkin muncul; Google tidak menjamin rich result tampil.

## 7. Keputusan yang perlu diambil

1. Apakah rating marketplace tetap ditampilkan (dengan label sumber) atau disembunyikan?
2. Ambang minimum ulasan sebelum `aggregateRating` dipasang: 1, 3, atau 5?
3. Apakah pengulas harus mengisi nomor WhatsApp atau email (untuk verifikasi, tidak ditampilkan) atau cukup nama?
