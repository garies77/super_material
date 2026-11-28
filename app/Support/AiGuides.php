<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Pedoman untuk asisten AI (dibaca lewat resource MCP dan disisipkan ke prompt), agar gaya tulis dan aturan SEO
 * tidak perlu diulang di setiap percakapan. Admin bisa menggantinya di Pengaturan situs; kosong = pedoman bawaan.
 */
class AiGuides
{
    public const WRITING_KEY = 'ai_writing_guide';

    public const SEO_KEY = 'ai_seo_guide';

    public static function writing(): string
    {
        return filled($custom = Setting::get(self::WRITING_KEY)) ? (string) $custom : self::defaultWriting();
    }

    public static function seo(): string
    {
        return filled($custom = Setting::get(self::SEO_KEY)) ? (string) $custom : self::defaultSeo();
    }

    public static function defaultWriting(): string
    {
        $brand = Setting::brandName();

        return <<<MD
        # Gaya tulis {$brand}

        - Pembaca: pemilik rumah, mandor, dan tukang yang mencari bahan bangunan, kebanyakan dari HP.
        - Bahasa Indonesia baku tapi santai, kalimat pendek, langsung ke inti. Sapa pembaca dengan "Anda".
        - Mulai dari masalah pembaca, baru solusi dan produk. Jangan membuka dengan basa-basi seperti "Di era modern ini".
        - Pakai istilah lapangan yang dipakai tukang (cetok, raskam, sipatan, dynabolt), jelaskan sekali bila perlu.
        - Angka harus nyata: ukuran, isi kemasan, dan langkah kerja diambil dari data produk (get-product), jangan dikarang.
        - Jangan menulis harga di isi artikel; harga berubah dan sudah tampil di kartu produk.
        - Hindari klaim berlebihan ("terbaik", "paling murah", "100% anti karat") kecuali tertulis di data produk.
        - Tutup dengan langkah berikutnya yang jelas: produk yang cocok atau ajakan konsultasi via WhatsApp.
        MD;
    }

    public static function defaultSeo(): string
    {
        return <<<'MD'
        # Aturan SEO

        - Judul artikel ≤ 70 karakter dan memuat kata kunci utama di depan (mis. "Cara Pasang Dynabolt ...").
        - meta_title 50–60 karakter (maks. 70), meta_description 140–160 karakter, keduanya wajib diisi.
        - Struktur isi: paragraf pembuka 2–3 kalimat, lalu subjudul <h2> per bagian dan <h3> untuk langkah. Jangan memakai <h1>.
        - Panjang isi minimal 300 kata; panduan langkah demi langkah biasanya 600–1.200 kata.
        - Tautkan 2–5 produk relevan lewat kolom products, dan sebut produknya di isi. Pakai suggest-internal-links untuk ide.
        - Tambahkan 3–5 FAQ dari pertanyaan nyata pembeli.
        - Foto utama wajib (image_url) dengan image_caption yang menjelaskan isi foto.
        - Slug huruf kecil dengan tanda hubung, pendek, tanpa kata sambung (mis. cara-pasang-dynabolt-di-beton).
        - Satu kategori utama dari list-categories dan 2–4 tag; cek list-tags agar tidak membuat tag dobel.
        MD;
    }
}
