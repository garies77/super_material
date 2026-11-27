<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddProductMedia;
use App\Mcp\Tools\CreateArticle;
use App\Mcp\Tools\CreateProduct;
use App\Mcp\Tools\GetArticle;
use App\Mcp\Tools\GetClickReport;
use App\Mcp\Tools\GetProduct;
use App\Mcp\Tools\GetProductReport;
use App\Mcp\Tools\GetReportSummary;
use App\Mcp\Tools\GetTrafficReport;
use App\Mcp\Tools\ListBrands;
use App\Mcp\Tools\ListCategories;
use App\Mcp\Tools\RemoveProductMedia;
use App\Mcp\Tools\ReorderProductMedia;
use App\Mcp\Tools\ReplaceProductVariants;
use App\Mcp\Tools\SearchArticles;
use App\Mcp\Tools\SearchProducts;
use App\Mcp\Tools\SetProductLinks;
use App\Mcp\Tools\UpdateArticle;
use App\Mcp\Tools\UpdateProduct;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Server MCP Supermaterial: pintu bagi asisten AI (mis. Claude) ke katalog, artikel, dan laporan,
 * serta penulisan produk (Fase 3) dengan validasi dan audit yang sama dengan REST API admin.
 * Memakai token API Sanctum yang sama dengan REST API admin; hak efektif = ability token ∩ peran pengguna.
 */
#[Name('Supermaterial')]
#[Version('0.5.0')]
#[Instructions('Server MCP untuk situs katalog bahan bangunan Supermaterial (supermaterial.id). Tool yang muncul mengikuti izin token. Baca: katalog (search-products, get-product, list-categories, list-brands), artikel (search-articles, get-article), laporan (get-report-summary, get-product-report, get-traffic-report, get-click-report). Tulis produk (butuh catalog:write): create-product, update-product, replace-product-variants, set-product-links, add-product-media (foto dari URL https atau video YouTube), remove-product-media, reorder-product-media. Tulis artikel (butuh articles:write): create-article, update-article; foto utama artikel dikirim lewat image_url. Sebelum menulis: cari dulu, ambil detailnya, jelaskan perubahan yang akan dibuat ke pengguna, lalu minta persetujuan. Produk dan artikel baru dibuat sebagai draf kecuali pengguna meminta langsung terbit. Saat menulis artikel: cek search-articles agar topik tidak dobel, pakai slug kategori dari list-categories, tautkan produk yang relevan dari search-products, tulis isi HTML dengan subjudul <h2>/<h3>, dan isi meta_title (maks. 70 karakter) serta meta_description. replace-product-variants dan set-product-links MENGGANTI seluruh daftar, begitu juga categories/tags/products/faqs di update-article, jadi sertakan juga item lama yang ingin dipertahankan. Tanggal laporan memakai zona WIB (Asia/Jakarta), bawaan 30 hari terakhir. Harga dalam rupiah tanpa titik. Jawab dalam bahasa Indonesia.')]
class SupermaterialServer extends Server
{
    /** Hanya tool yang ability-nya dimiliki token yang terdaftar (lihat AbilityTool). */
    protected array $tools = [
        SearchProducts::class,
        GetProduct::class,
        ListCategories::class,
        ListBrands::class,
        SearchArticles::class,
        GetArticle::class,
        GetReportSummary::class,
        GetProductReport::class,
        GetTrafficReport::class,
        GetClickReport::class,
        CreateProduct::class,
        UpdateProduct::class,
        ReplaceProductVariants::class,
        SetProductLinks::class,
        AddProductMedia::class,
        RemoveProductMedia::class,
        ReorderProductMedia::class,
        CreateArticle::class,
        UpdateArticle::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
