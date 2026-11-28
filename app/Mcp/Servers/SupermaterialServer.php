<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\TidyProductPrompt;
use App\Mcp\Prompts\WeeklyReportPrompt;
use App\Mcp\Prompts\WriteArticleFromProductsPrompt;
use App\Mcp\Resources\CatalogSummaryResource;
use App\Mcp\Resources\SeoGuideResource;
use App\Mcp\Resources\WritingGuideResource;
use App\Mcp\Tools\AddProductMedia;
use App\Mcp\Tools\ArchiveArticle;
use App\Mcp\Tools\ArchiveProduct;
use App\Mcp\Tools\AuditArticles;
use App\Mcp\Tools\AuditCatalog;
use App\Mcp\Tools\BulkUpdateProducts;
use App\Mcp\Tools\CreateArticle;
use App\Mcp\Tools\CreateBrand;
use App\Mcp\Tools\CreateCategory;
use App\Mcp\Tools\CreateProduct;
use App\Mcp\Tools\GetArticle;
use App\Mcp\Tools\GetAuditLog;
use App\Mcp\Tools\GetClickReport;
use App\Mcp\Tools\GetContentOpportunities;
use App\Mcp\Tools\GetImportStatus;
use App\Mcp\Tools\GetProduct;
use App\Mcp\Tools\GetProductReport;
use App\Mcp\Tools\GetReportSummary;
use App\Mcp\Tools\GetTrafficReport;
use App\Mcp\Tools\ListBrands;
use App\Mcp\Tools\ListCategories;
use App\Mcp\Tools\ListTags;
use App\Mcp\Tools\MergeTags;
use App\Mcp\Tools\PublishArticle;
use App\Mcp\Tools\RemoveProductMedia;
use App\Mcp\Tools\ReorderProductMedia;
use App\Mcp\Tools\ReplaceProductVariants;
use App\Mcp\Tools\RestoreArticle;
use App\Mcp\Tools\RestoreProduct;
use App\Mcp\Tools\SearchArticles;
use App\Mcp\Tools\SearchProducts;
use App\Mcp\Tools\SetProductLinks;
use App\Mcp\Tools\StartImport;
use App\Mcp\Tools\SuggestInternalLinks;
use App\Mcp\Tools\UpdateArticle;
use App\Mcp\Tools\UpdateBrand;
use App\Mcp\Tools\UpdateCategory;
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
#[Version('1.0.0')]
#[Instructions('Server MCP untuk situs katalog bahan bangunan Supermaterial (supermaterial.id). Tool yang muncul mengikuti izin token. Baca: katalog (search-products, get-product, list-categories, list-brands, audit-catalog), artikel (search-articles, get-article, list-tags, audit-articles), laporan (get-report-summary, get-product-report, get-traffic-report, get-click-report, get-content-opportunities untuk ide konten berbasis data). Tulis produk (butuh catalog:write): create-product, update-product, replace-product-variants, set-product-links, add-product-media (foto dari URL https atau video YouTube), remove-product-media, reorder-product-media, bulk-update-products (selalu pratinjau dulu, simpan dengan confirmation_token). Impor massal (butuh import:write): start-import dari URL CSV/xlsx, pantau dengan get-import-status. Untuk merapikan katalog atau artikel, mulai dari audit-catalog / audit-articles. Tulis artikel (butuh articles:write): create-article, update-article; foto utama artikel dikirim lewat image_url; terbitkan/jadwalkan dengan publish-article (ada pemeriksaan kelengkapan). Arsip (bisa dipulihkan, tanpa hapus permanen): archive-product/restore-product (catalog:write), archive-article/restore-article (articles:write). Riwayat perubahan (audit:read, khusus Admin): get-audit-log. Kategori, merek, dan tag (butuh taxonomy:write): create-category, update-category, create-brand, update-brand, merge-tags; mengganti slug kategori membuat redirect 301 otomatis. Sebelum menulis: cari dulu, ambil detailnya, jelaskan perubahan yang akan dibuat ke pengguna, lalu minta persetujuan. Produk dan artikel baru dibuat sebagai draf kecuali pengguna meminta langsung terbit. Saat menulis artikel: cek search-articles agar topik tidak dobel, pakai slug kategori dari list-categories, tautkan produk yang relevan dari search-products, tulis isi HTML dengan subjudul <h2>/<h3>, dan isi meta_title (maks. 70 karakter) serta meta_description. replace-product-variants dan set-product-links MENGGANTI seluruh daftar, begitu juga categories/tags/products/faqs di update-article, jadi sertakan juga item lama yang ingin dipertahankan. Tanggal laporan memakai zona WIB (Asia/Jakarta), bawaan 30 hari terakhir. Harga dalam rupiah tanpa titik. Sebelum menulis atau merapikan konten, baca resource supermaterial://pedoman/gaya-tulis dan supermaterial://pedoman/seo; supermaterial://katalog/ringkasan memberi gambaran katalog. Prompt siap pakai: tulis-artikel-dari-produk, rapikan-produk, laporan-mingguan. Untuk tautan internal artikel pakai suggest-internal-links. Jawab dalam bahasa Indonesia.')]
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
        ListTags::class,
        AuditCatalog::class,
        AuditArticles::class,
        SuggestInternalLinks::class,
        GetReportSummary::class,
        GetProductReport::class,
        GetTrafficReport::class,
        GetContentOpportunities::class,
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
        PublishArticle::class,
        ArchiveArticle::class,
        RestoreArticle::class,
        CreateCategory::class,
        UpdateCategory::class,
        CreateBrand::class,
        UpdateBrand::class,
        MergeTags::class,
        BulkUpdateProducts::class,
        StartImport::class,
        GetImportStatus::class,
        ArchiveProduct::class,
        RestoreProduct::class,
        GetAuditLog::class,
    ];

    /** Pedoman dan ringkasan yang dibaca asisten sebelum bekerja; terdaftar mengikuti ability token (RequiresAbility). */
    protected array $resources = [
        WritingGuideResource::class,
        SeoGuideResource::class,
        CatalogSummaryResource::class,
    ];

    /** Alur kerja siap pakai; terdaftar mengikuti ability token (RequiresAbility). */
    protected array $prompts = [
        WriteArticleFromProductsPrompt::class,
        TidyProductPrompt::class,
        WeeklyReportPrompt::class,
    ];
}
