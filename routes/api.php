<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V1\Admin;
use App\Support\Api\ApiError;
use Illuminate\Support\Facades\Route;

/*
| REST API v1 (PRD 8.12). Prefix /api dari bootstrap/app.php.
| Publik: baca saja, tanpa token, hanya konten terbit. Admin: token Sanctum + ability ∩ peran.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware(['throttle:api-public', 'api.cache'])->group(function () {
        Route::get('site', V1\SiteController::class)->name('site');
        Route::get('categories', [V1\CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{slug}', [V1\CategoryController::class, 'show'])->name('categories.show');
        Route::get('brands', [V1\BrandController::class, 'index'])->name('brands.index');
        Route::get('products', [V1\ProductController::class, 'index'])->name('products.index');
        Route::get('products/{slug}', [V1\ProductController::class, 'show'])->name('products.show');
        Route::get('products/{slug}/links', [V1\ProductController::class, 'links'])->name('products.links');
        Route::get('sales-channels', [V1\SalesChannelController::class, 'index'])->name('sales-channels.index');
        Route::get('articles', [V1\ArticleController::class, 'index'])->name('articles.index');
        Route::get('articles/{slug}', [V1\ArticleController::class, 'show'])->name('articles.show');
        Route::get('tags', [V1\TagController::class, 'index'])->name('tags.index');
        Route::get('authors/{slug}', [V1\AuthorController::class, 'show'])->name('authors.show');
        Route::get('search', V1\SearchController::class)->name('search');
    });

    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'throttle:api-admin'])->group(function () {
        Route::middleware('api.ability:catalog:read')->group(function () {
            Route::get('products', [Admin\ProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [Admin\ProductController::class, 'show'])->name('products.show');
        });

        Route::middleware(['api.ability:catalog:write', 'api.idempotent'])->group(function () {
            Route::post('products', [Admin\ProductController::class, 'store'])->name('products.store');
            Route::patch('products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
            Route::put('products/{product}/variants', [Admin\ProductController::class, 'variants'])->name('products.variants');
            Route::post('products/{product}/media', [Admin\ProductController::class, 'media'])->name('products.media');
            Route::put('products/{product}/links', [Admin\ProductController::class, 'links'])->name('products.links');
        });

        Route::middleware(['api.ability:import:write', 'api.idempotent'])->group(function () {
            Route::post('imports', [Admin\ImportController::class, 'store'])->name('imports.store');
            Route::get('imports/{import}', [Admin\ImportController::class, 'show'])->name('imports.show');
        });

        Route::middleware('api.ability:reports:read')->group(function () {
            Route::get('reports/clicks', [Admin\ClickReportController::class, 'index'])->name('reports.clicks');
            Route::get('reports/clicks/export', [Admin\ClickReportController::class, 'export'])->name('reports.clicks.export');
            Route::get('reports/summary', [Admin\ReportController::class, 'summary'])->name('reports.summary');
            Route::get('reports/products', [Admin\ReportController::class, 'products'])->name('reports.products');
            Route::get('reports/traffic', [Admin\ReportController::class, 'traffic'])->name('reports.traffic');
        });
    });

    // Rute tak dikenal di bawah /api/v1 tetap memakai format galat API
    Route::fallback(fn () => ApiError::response(404))->name('fallback');
});
