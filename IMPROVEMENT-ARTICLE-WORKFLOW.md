# Instruksi Tugas: Refaktor & Modernisasi Workflow Redaksi Artikel Supermaterial

Dokumen ini adalah spesifikasi kebutuhan dan instruksi fungsional terperinci untuk **Claude Code**. Pelajari arsitektur proyek, pahami kebutuhan editorial, dan rancang solusi implementasi kode terbaik secara mandiri tanpa terikat pada cuplikan kode kaku.

---

## 1. Ringkasan Tugas & Tujuan

Sederhanakan alur kerja redaksi artikel pada Supermaterial (Laravel 12 + Filament 5 + Tailwind Blade) dari gaya kantor berita cetak konvensional (*CNN Indonesia style*) menjadi format editorial web modern yang bersih, cepat, dan berstandar tinggi.

**Masalah Saat Ini:**
* Form artikel memuat banyak kolom warisan media cetak (*Inisial Penulis*, *Nama Editor*, *Inisial Editor*, *Dateline*) yang memperlambat proses penulisan.
* Kolom-kolom tersebut tidak memiliki pengaruh algoritma pada penilaian Google E-E-A-T (Google hanya membaca nama lengkap asli dan entitas terverifikasi di JSON-LD, bukan singkatan inisial).
* Status artikel sering tertahan di *Draf* karena tidak adanya jalan pintas untuk langsung menerbitkan (*publish now*) tanpa harus memilih jam dan tanggal secara manual.
* Query otorisasi peran Penulis di panel admin berpotensi bermasalah jika ID akun pengguna tidak sama persis dengan ID profil penulis.

---

## 2. Ruang Lingkup Berkas Terkait

Telusuri dan sesuaikan berkas-berkas berikut:
* `app/Filament/Resources/Articles/ArticleResource.php` (Form, Tabel, dan Otorisasi Query)
* `app/Models/Article.php` (Pengolahan konten artikel, pembersihan dateline, dan inisial)
* `app/Models/User.php` (Relasi dan resolusi profil penulis milik pengguna)
* `resources/views/article.blade.php` (Tampilan metadata artikel publik)
* `tests/Feature/` (Pembaruan atau penambahan pengujian fungsional terkait)

---

## 3. Spesifikasi Kebutuhan Rinci

### A. Penyederhanaan Form Input Artikel (`ArticleResource`)
1. **Pembaruan Seksi:**
   * Ubah identitas seksi dari nuansa berita konvensional (*Redaksi & Metadata Berita CNN Style*) menjadi seksi modern, misalnya **"Publikasi & Penulis"**.
2. **Pembersihan Kolom:**
   * Hapus kolom-kolom berikut dari formulir:
     * Inisial Penulis (`author_initials`)
     * Nama Editor (`editor_name`)
     * Inisial Editor (`editor_initials`)
     * Dateline (`dateline`)
3. **Logika Cerdas Pemilihan Penulis (`author_id`):**
   * Hubungkan pemilihan penulis dengan akun yang sedang login.
   * **Secara bawaan (*default*):** Dropdown Penulis harus otomatis terisi dengan profil penulis milik pengguna yang sedang membuat artikel.
   * **Untuk Peran Penulis (*Author*):** Kunci (*disable*) input ini agar Penulis hanya dapat menerbitkan atas nama dirinya sendiri dan tidak bisa memilih profil penulis lain. Nilai harus tetap terkirim saat form disimpan (*dehydrated*).
   * **Untuk Peran Admin & Editor:** Tetap buka pilihan dropdown agar mereka memiliki fleksibilitas menerbitkan naskah atas nama kontributor ahli lain atau mendaftarkan profil penulis baru langsung dari form.
4. **Penerbitan Cepat pada Input Tanggal Terbit (`published_at`):**
   * Berikan aksi tambahan (*suffix action* / tombol pintas) tepat di samping pemilih tanggal terbit dengan label seperti **"Terbitkan Sekarang"**.
   * Ketika tombol tersebut diklik, input tanggal terbit harus otomatis terisi dengan tanggal dan waktu saat ini (WIB), sehingga artikel langsung berstatus *Terbit* saat disimpan tanpa harus mengklik pemilih kalender.
5. **Elemen yang Tetap Dipertahankan:**
   * Pilihan jenis schema data terstruktur (`schema_type`: BlogPosting, Article, NewsArticle).
   * Toggle artikel unggulan (*headline* beranda).

---

### B. Optimalisasi Tabel Daftar Artikel (`ArticleResource`)
1. **Aksi Cepat Baris Tabel (*Record Actions*):**
   * Tambahkan tombol aksi **"Terbitkan"** langsung di tabel daftar artikel untuk artikel yang masih berstatus *Draf* atau *Terjadwal*. Aksi ini mengisi `published_at` dengan waktu sekarang dan menampilkan notifikasi sukses.
   * Tambahkan tombol aksi **"Jadikan Draf"** untuk artikel yang sudah terbit, yang mengosongkan kembali kolom `published_at` dengan konfirmasi pengguna.
2. **Perbaikan Otorisasi Filter Penulis:**
   * Di dalam metode `getEloquentQuery()`, pastikan pemfilteran artikel untuk akun ber-role *Author* memeriksa relasi penulis yang terhubung ke ID pengguna (`whereHas('author', ...)`), bukan mencocokkan `author_id` langsung dengan ID user.

---

### C. Pembersihan Logika Pemrosesan Konten (`Article.php`)
1. **Penghapusan Injeksi Dateline Otomatis:**
   * Pada logika pengolahan isi naskah dan daftar isi (`contentWithToc`), hilangkan logika yang memaksa menyisipkan teks dateline tebal (seperti *"Medan, Supermaterial --"*) ke paragraf pertama. Biarkan paragraf pembuka mengalir alami sesuai teks asli yang ditulis penulis.
2. **Penonaktifan Inisial Redaksi:**
   * Nonaktifkan atau kosongkan keluaran blok inisial kurung (`initialsBlock`) agar tidak lagi menghasilkan format `(gar/ed)` di akhir artikel.
3. **Kompatibilitas Mundur:**
   * Pastikan artikel-artikel yang sudah ada di database tetap dapat dimuat dengan aman tanpa galat (*null-safe*).

---

### D. Modernisasi Tampilan Publik (`article.blade.php`)
1. **Header Metadata Artikel:**
   * Tampilkan baris metadata yang rapi dan profesional di bawah judul:
     * **Nama Penulis** yang ditautkan ke halaman profil `/penulis/{slug}`.
     * **Tanggal & Jam Terbit** dalam zona waktu situs (WIB).
     * **Keterangan Pembaruan** (hanya muncul jika artikel pernah mengalami pembaruan bermakna).
     * **Estimasi Waktu Baca** dalam menit.
2. **Badan Konten & Penutup:**
   * Hapus pemanggilan blok inisial `(gar/ed)` di bawah artikel.
   * Pertahankan kartu profil penulis di bawah isi (foto, nama, jabatan, bio singkat, dan tautan sosial) sebagai fondasi utama sinyal E-E-A-T Google.
   * Pastikan penempatan slot iklan bawah dan tombol bagikan tetap proporsional.

---

## 4. Batasan & Prinsip Teknis

1. **Integritas E-E-A-T & SEO Google:**
   * Jangan pernah merusak atau menghapus skema JSON-LD `Person`, `ProfilePage`, dan properti `author` pada artikel. Google mengidentifikasi kredibilitas melalui metadata terstruktur ini.
2. **Kesesuaian Tipe Data:**
   * Kolom database lama (`author_initials`, `dateline`, dll.) tidak perlu dihapus melalui migrasi database untuk menjaga keamanan data; cukup abaikan penggunaannya di lapisan antarmuka dan model.
3. **Keamanan Peran:**
   * Pastikan validasi di backend mencegah peran Penulis (*Author*) memanipulasi atau mengambil alih kepemilikan artikel milik penulis lain.

---

## 5. Kriteria Penerimaan & Verifikasi

Claude Code dianggap berhasil menyelesaikan tugas ini apabila:
1. Formulir penulisan artikel di admin panel tidak lagi menampilkan kolom *Inisial Penulis*, *Nama Editor*, *Inisial Editor*, dan *Dateline*.
2. Pengguna dapat menerbitkan artikel dengan 1 klik menggunakan tombol pintas **"Terbitkan Sekarang"** di form atau tombol aksi di tabel.
3. Akun dengan role *Author* otomatis terkunci ke profil penulisnya sendiri saat membuat artikel baru.
4. Halaman artikel publik tidak lagi memuat awalan tebal `Medan, Supermaterial --` pada paragraf pertama dan tidak lagi menampilkan kode inisial `(gar/ed)` di akhir artikel.
5. Semua pengujian otomatis (`php artisan test`) berjalan dengan hasil **100% Lulus (Pass)** tanpa regresi.
