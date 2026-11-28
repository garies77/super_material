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

#[Name('tulis-artikel-dari-produk')]
#[Description('Alur lengkap menulis artikel dari produk yang ada: cari produk, cek topik dobel, susun kerangka, minta persetujuan, lalu simpan sebagai draf sesuai pedoman gaya tulis dan SEO situs.')]
class WriteArticleFromProductsPrompt extends Prompt
{
    use RequiresAbility;

    /** @var list<string> */
    protected array $abilities = ['articles:write'];

    public function handle(Request $request): Response
    {
        $topic = trim((string) $request->get('topik'));
        $category = trim((string) $request->get('kategori'));
        $products = trim((string) $request->get('produk'));

        $focus = match (true) {
            $products !== '' => "Produk yang dibahas: {$products} (ambil detail tiap produk dengan get-product).",
            $category !== '' => "Fokus pada kategori \"{$category}\": cari produknya dengan search-products (category={$category}).",
            default => 'Belum ada produk yang dipilih: lihat ringkasan katalog (resource supermaterial://katalog/ringkasan) atau search-products, lalu usulkan 3 topik ke pengguna dan minta mereka memilih.',
        };
        $topicLine = $topic !== '' ? "Topik yang diminta: {$topic}." : 'Topik belum ditentukan; usulkan dari produk yang tersedia.';
        $guides = AiGuides::writing()."\n\n".AiGuides::seo();

        return Response::text(<<<MD
        Tugas: tulis satu artikel blog untuk situs ini berdasarkan produk yang benar-benar dijual.

        {$topicLine}
        {$focus}

        Langkah:
        1. Kumpulkan data: get-product untuk setiap produk yang relevan (ukuran, isi kemasan, spesifikasi). Jangan mengarang angka.
        2. Cek topik dobel: search-articles dengan kata kunci utama. Bila sudah ada artikel serupa, laporkan dan tawarkan memperbarui artikel itu dengan update-article.
        3. Ambil slug kategori dari list-categories dan tag yang sudah ada dari list-tags.
        4. Susun kerangka (judul, meta_title, meta_description, subjudul <h2>/<h3>, produk tertaut, 3–5 FAQ) lalu TUNJUKKAN ke pengguna dan minta persetujuan sebelum menulis isi lengkap.
        5. Setelah disetujui, panggil create-article (status draf). Sertakan image_url bila pengguna memberi foto.
        6. Jalankan audit-articles dan sebutkan kekurangan artikel ini yang tersisa.
        7. Jangan terbitkan kecuali diminta. Bila diminta, pakai publish-article.

        {$guides}
        MD);
    }

    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [
            new Argument('topik', 'Topik atau judul kerja artikel, mis. "cara pasang dynabolt".'),
            new Argument('kategori', 'Slug kategori produk yang dibahas (opsional).'),
            new Argument('produk', 'Slug produk yang dibahas, pisahkan dengan koma (opsional).'),
        ];
    }
}
