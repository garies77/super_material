<?php

namespace App\Mcp\Prompts;

use App\Mcp\Concerns\RequiresAbility;
use App\Support\AiGuides;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('rapikan-produk')]
#[Description('Audit lalu rapikan data produk (deskripsi, meta, FAQ, foto, tautan beli, kategori) dengan persetujuan pengguna di setiap perubahan. Tanpa argumen, mulai dari produk yang paling tidak lengkap.')]
class TidyProductPrompt extends Prompt
{
    use RequiresAbility;

    /** @var list<string> */
    protected array $abilities = ['catalog:write'];

    public function handle(Request $request): Response
    {
        $product = trim((string) $request->get('produk'));
        $start = $product !== ''
            ? "Produk yang dirapikan: {$product}. Ambil detailnya dengan get-product, lalu jalankan audit-catalog dan cari baris produk ini."
            : 'Jalankan audit-catalog (limit 10) dan mulai dari produk dengan completeness terendah. Tunjukkan ringkasan issue_counts ke pengguna dulu.';
        $seo = AiGuides::seo();

        return Response::text(<<<MD
        Tugas: rapikan data produk agar lengkap dan siap tampil di Google dan marketplace.

        {$start}

        Untuk setiap produk:
        1. Daftar kekurangannya (issues dan failed_seo_checks) dengan bahasa sederhana.
        2. Usulkan perbaikan konkret: deskripsi minimal 300 karakter dari spesifikasi nyata, meta_title ≤ 60 karakter, meta_description 140–160 karakter, 3 FAQ, kategori/merek yang tepat.
        3. Bila issues berisi possible_duplicate, tunjukkan produk di duplicate_of dan tanyakan mana yang dipertahankan. Produk yang tidak dipakai diarsipkan dengan archive-product (minta persetujuan dulu), bukan dihapus.
        4. Bila slug tidak rapi, usulkan slug baru; perubahan slug otomatis membuat redirect 301.
        5. Minta persetujuan, lalu simpan dengan update-product. Foto lewat add-product-media (URL https dari pengguna), tautan beli lewat set-product-links (sertakan tautan lama yang dipertahankan).
        6. Perubahan kolom yang sama untuk banyak produk (kategori, merek, badge, status) memakai bulk-update-products: tunjukkan pratinjaunya, baru simpan dengan confirmation_token.
        7. Bila update-product mengembalikan warnings (mis. harga berubah jauh), sampaikan dan minta konfirmasi.

        Jangan mengubah harga atau nama tanpa perintah eksplisit pengguna.

        {$seo}
        MD);
    }

    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [
            new Argument('produk', 'Slug atau ID produk yang dirapikan (opsional; kosong = mulai dari hasil audit).'),
        ];
    }
}
