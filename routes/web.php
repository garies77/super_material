<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\GoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/produk', [CatalogController::class, 'index'])->name('product.index');
Route::get('/kategori/{slug}', [CatalogController::class, 'category'])->name('category.show');
Route::get('/produk/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/artikel', [ArticleController::class, 'index'])->name('article.index');
Route::get('/artikel/kategori/{slug}', [ArticleController::class, 'category'])->name('article.category');
Route::get('/artikel/tag/{slug}', [ArticleController::class, 'tag'])->name('article.tag');
Route::get('/artikel/{slug}', [ArticleController::class, 'show'])->name('article.show');
Route::get('/penulis/{slug}', [AuthorController::class, 'show'])->name('author.show');
Route::get('/cari', SearchController::class)->name('search');
Route::get('/go/{product}/{channel}', GoController::class)->middleware('throttle:60,1')->name('go');
Route::get('/ad/{ad}', [AdController::class, 'click'])->whereNumber('ad')->middleware('throttle:60,1')->name('ad.click');
Route::post('/promo-event/{ad}', [AdController::class, 'event'])->whereNumber('ad')->middleware('throttle:120,1')->name('ad.event');
Route::get('/admin/pratinjau-iklan/{ad}', [AdController::class, 'preview'])->whereNumber('ad')->name('ad.preview');
Route::post('/sm-campaign/{campaign}/event', [CampaignController::class, 'event'])->whereNumber('campaign')->middleware('throttle:120,1')->name('campaign.event');
Route::get('/admin/pratinjau-kampanye/{campaign}', [CampaignController::class, 'preview'])->whereNumber('campaign')->name('campaign.preview');
Route::get('/admin/pratinjau-kampanye/{campaign}/bingkai', [CampaignController::class, 'previewFrame'])->whereNumber('campaign')->name('campaign.preview-frame');

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'index']);
Route::get('/sitemap-{type}.xml', [SeoController::class, 'show'])->where('type', implode('|', SeoController::TYPES));
Route::get('/sitemap-{type}-{page}.xml', [SeoController::class, 'showNumbered'])->where(['type' => implode('|', SeoController::TYPES), 'page' => '[0-9]+']);

// Halaman statis (tentang, kontak, dsb.) — harus paling akhir.
Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '(?!admin$|up$|go$|ad$)[a-z0-9\-]+')->name('page.show');
