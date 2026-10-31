# Deploy Supermaterial ke shared hosting (tanpa SSH)

Deploy berjalan otomatis lewat GitHub Actions (`.github/workflows/deploy.yml`) dan FTP. Tidak ada perintah yang perlu
dijalankan manual di server.

## Alur singkat

1. Kerjakan perubahan di branch lain (mis. `feat/...`), push, buat pull request ke `main`.
2. GitHub menjalankan semua tes + uji migrasi dan cadangan/pulih database di MariaDB. Gagal = tidak bisa di-deploy.
3. Merge ke `main` → deploy otomatis:
   - memastikan cron hosting hidup (bila tidak, situs **tidak disentuh**);
   - situs masuk mode pemeliharaan, file yang berubah di-upload, file `RELEASE` di-upload terakhir;
   - cron menjalankan `php artisan deploy:finish`: **cadangan database** (bila ada migrasi baru) → migrasi → cache → situs dibuka
     → gambar baru didaftarkan ke Pustaka Media (`media:sync`; run pertama bisa beberapa menit, situs sudah terbuka);
   - GitHub mengecek halaman situs dan memastikan berkas rahasia tidak bisa diakses publik.
4. Hasil terlihat di tab **Actions** (centang hijau / silang merah) beserta ringkasannya.

## Yang tidak pernah disentuh deploy

| Lokasi | Isi |
|---|---|
| `.env` | konfigurasi & kata sandi server |
| `storage/` (seluruhnya) | foto produk & unggahan (`storage/app/public`), potongan gambar, log, sesi, cadangan database |
| `public/storage` | symlink ke foto unggahan |
| berkas buatan server di `bootstrap/cache/` | cache config/route (dibuat ulang oleh `deploy:finish`) |

Berkas lain di server yang tidak berasal dari repositori (mis. `public/build.zip`) juga tidak dihapus. Yang ditimpa
hanya berkas kode dari repositori, termasuk `public/index.php` dan `public/.htaccess` (lihat langkah 1.5).

---

## 1. Persiapan server (sekali)

1. **PHP 8.4** — hPanel → Advanced → PHP Configuration: pilih 8.4. Pastikan ekstensi aktif:
   `mbstring, intl, gd, zip, bcmath, exif, fileinfo, pdo_mysql`.
2. **Document root harus folder `public/`**, bukan folder proyek. Cek setelah deploy: workflow otomatis memastikan
   `/.env`, `/composer.json`, `/vendor/autoload.php`, `/storage/logs/laravel.log` **tidak** bisa dibuka dari internet.
   Bila hosting menyalin isi `public/` ke `public_html` secara terpisah, isi variabel `FTP_PUBLIC_DIR` (bagian 2).
3. **`.env` produksi** (hPanel → File Manager, di folder proyek), minimal:
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://supermaterial.jayaintero.com
   APP_KEY=base64:...            # jangan diubah bila sudah ada
   APP_LOCALE=id
   APP_MAINTENANCE_DRIVER=file   # wajib "file": mode pemeliharaan deploy memakai berkas storage/framework/down
   LOG_LEVEL=error
   DB_CONNECTION=mysql           # beserta DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
   MAIL_MAILER=smtp              # beserta MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS
   QUEUE_CONNECTION=sync         # lihat catatan antrean di bawah
   ```
4. **Cron tiap menit** — hPanel → Advanced → Cron Jobs → Custom, jadwal `* * * * *`:
   ```bash
   /opt/alt/php84/usr/bin/php /home/USER/domains/supermaterial.jayaintero.com/FOLDER-PROYEK/artisan schedule:run >> /dev/null 2>&1
   ```
   Sesuaikan `USER` dan `FOLDER-PROYEK` (folder yang berisi berkas `artisan`). Path PHP 8.4 di Hostinger umumnya
   `/opt/alt/php84/usr/bin/php`; bila berbeda, tanyakan ke support Hostinger "path PHP 8.4 CLI untuk cron".
   Cron ini juga menjalankan jadwal lain: penanda harga kedaluwarsa, skor katalog, email laporan mingguan.
5. **Bandingkan `public/.htaccess` dan `public/index.php` di server dengan versi repositori.** Deploy akan menimpanya
   dengan versi repositori. Bila versi server pernah diubah khusus untuk hosting, salin perubahan itu ke repositori dulu.
6. **Akun FTP** — hPanel → Files → FTP Accounts. Catat host, username, password, dan folder awal (root) akun itu.

> **Catatan antrean:** notifikasi email formulir dan impor produk lewat API memakai antrean. Shared hosting tidak
> punya worker antrean, jadi dengan `QUEUE_CONNECTION=database` email itu tidak pernah terkirim. `QUEUE_CONNECTION=sync`
> mengirimnya langsung saat formulir dikirim (sedikit lebih lambat untuk pengunjung). Pilihan lain (worker antrean
> lewat cron) butuh perubahan kode tersendiri.

## 2. Pengaturan GitHub (sekali)

Repositori → **Settings → Secrets and variables → Actions**.

**Secrets** (isi sendiri; jangan dibagikan ke siapa pun):

| Nama | Isi |
|---|---|
| `FTP_SERVER` | host FTP dari hPanel (mis. `ftp.supermaterial.jayaintero.com` atau IP) |
| `FTP_USERNAME` | username akun FTP |
| `FTP_PASSWORD` | password akun FTP |

**Variables**:

| Nama | Isi | Wajib |
|---|---|---|
| `FTP_SERVER_DIR` | folder proyek (berisi `artisan`) **relatif dari folder awal akun FTP**, diakhiri `/`, mis. `domains/supermaterial.jayaintero.com/laravel/` | ya |
| `SITE_URL` | `https://supermaterial.jayaintero.com` | ya |
| `FTP_PROTOCOL` | `ftps` (bawaan, terenkripsi) atau `ftp` bila server tidak mendukung FTPS | tidak |
| `FTP_PUBLIC_DIR` | hanya bila isi `public/` disalin terpisah ke `public_html`, diakhiri `/` | tidak |

Cara menentukan `FTP_SERVER_DIR`: login dengan aplikasi FTP (mis. FileZilla) memakai akun yang sama; folder yang
pertama terbuka adalah titik awal. Telusuri sampai folder yang berisi `artisan`; jalurnya dari titik awal itulah isinya.

**Environment `production`** — Settings → Environments → `production`:
- *Deployment branches and tags*: pilih **Selected branches** → `main`.
- (Disarankan) *Required reviewers*: Anda sendiri, agar tiap deploy menunggu klik "Approve".

**Proteksi branch `main`** — Settings → Branches → Add rule `main`:
- *Require a pull request before merging*;
- *Require status checks to pass*: centang **Tes otomatis**.

## 3. Cadangan sebelum deploy pertama (wajib)

1. hPanel → **Backups**: buat cadangan baru (berkas + database), tunggu selesai, lalu **unduh** keduanya ke komputer.
2. hPanel → **Databases → phpMyAdmin** → pilih database → **Export** (Quick, SQL) → simpan.
3. hPanel → **File Manager**: kompres lalu unduh folder `storage/app/public` (foto produk & unggahan) dan salinan `.env`.
4. Simpan ketiganya di tempat aman (bukan hanya di laptop yang sama).

Setelah sistem ini berjalan, `deploy:finish` membuat cadangan database otomatis ke `storage/app/backups/`
setiap ada migrasi baru (5 terbaru disimpan). Cadangan hPanel tetap disarankan sebelum perubahan besar.

## 4. Deploy pertama

1. Pastikan bagian 1–3 selesai, terutama **cron** dan **cadangan**.
2. Merge pull request ke `main` (atau Actions → **Tes & deploy** → Run workflow dari branch `main`).
3. Deploy pertama meng-upload seluruh `vendor/` dan bisa memakan waktu 20–60 menit; berikutnya hanya berkas yang berubah.
4. Setelah centang hijau, buka situs dan panel admin, coba beberapa halaman.

## 5. Rollback (kembali ke versi sebelumnya)

Actions → **Tes & deploy** → **Run workflow** → branch `main` → isi **kode commit** versi lama (terlihat di riwayat
commit `main`) → Run. Hanya versi yang sudah ada di `main` yang diterima.

Batasan yang perlu diingat:
- **Migrasi tidak dibatalkan otomatis.** Tabel/kolom baru tetap ada di database; kode lama umumnya tetap berjalan
  selama migrasi hanya *menambah* (tabel/kolom baru). Karena itu migrasi di proyek ini harus selalu bersifat menambah;
  menghapus atau mengganti nama kolom dilakukan bertahap di rilis terpisah setelah kode tidak memakainya lagi.
- Bila database perlu dikembalikan persis: unduh berkas terbaru dari `storage/app/backups/` (File Manager), lalu
  phpMyAdmin → database → **Import** berkas `.sql.gz` itu. Data yang masuk setelah cadangan dibuat (klik, lead, dll.)
  akan hilang, jadi lakukan hanya bila benar-benar perlu.

## 6. Jika deploy gagal

| Pesan di Actions | Kondisi situs | Yang dilakukan |
|---|---|---|
| *Pengaturan belum diisi* / *Cron hosting tidak berjalan* | tidak disentuh | lengkapi pengaturan / aktifkan cron, lalu Run workflow lagi |
| *Versi ... belum masuk ke branch main* | tidak disentuh | merge ke `main` dulu |
| Upload FTP gagal (setelah dicoba ulang) | mode pemeliharaan | Run workflow lagi; bila tetap gagal, periksa akun FTP |
| *deploy:finish gagal* | mode pemeliharaan, data aman | lihat log lewat workflow **Darurat: buka mode pemeliharaan** (tanpa mengetik BUKA), perbaiki lalu push, atau rollback |
| *deploy:finish belum selesai dalam 10 menit* | mode pemeliharaan | biasanya cron tertunda: tunggu, deploy selesai sendiri. Bila tidak, cek log seperti di atas |
| *... bisa diakses publik* | situs jalan | document root salah (bagian 1.2): perbaiki segera |

**Darurat:** Actions → **Darurat: buka mode pemeliharaan** → Run workflow:
- kosongkan isian → hanya menampilkan status deploy, detak cron, `deploy.log`, dan `laravel.log`;
- ketik `BUKA` → membuka situs dari mode pemeliharaan. Gunakan hanya bila yakin migrasi tidak bermasalah,
  lalu segera deploy ulang atau rollback.

Tanpa GitHub pun bisa: hPanel → File Manager → hapus berkas `storage/framework/down` di folder proyek.
