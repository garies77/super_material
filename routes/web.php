<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\GoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginLinkController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PagePreviewController;
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
Route::get('/cari/live', [SearchController::class, 'live'])->middleware('throttle:120,1')->name('search.live');
Route::get('/form/{slug}', [FormController::class, 'show'])->name('form.show');
// Batas kiriman per IP (anti-spam); honeypot ditangani di controller
Route::post('/form/{slug}', [FormController::class, 'submit'])->middleware('throttle:form-submit')->name('form.submit');
Route::get('/go/{product}/{channel}', GoController::class)->middleware('throttle:60,1')->name('go');
// Kampanye internal artikel: jalur netral agar tidak cocok dengan pola daftar filter ad blocker (/ad/, /promo)
Route::get('/sm-link/{ad}', [AdController::class, 'click'])->whereNumber('ad')->middleware('throttle:60,1')->name('ad.click');
Route::post('/sm-link/{ad}/event', [AdController::class, 'event'])->whereNumber('ad')->middleware('throttle:120,1')->name('ad.event');
// Alias jalur lama untuk halaman yang masih tersimpan di cache
Route::get('/ad/{ad}', [AdController::class, 'click'])->whereNumber('ad')->middleware('throttle:60,1');
// Kampanye (gabungan Kampanye Artikel + Banner Kampanye): satu menu /admin/kampanye dan satu pratinjau
Route::get('/admin/pratinjau-kampanye/{ad}', [AdController::class, 'preview'])->whereNumber('ad')->name('ad.preview');
Route::get('/admin/pratinjau-kampanye/{ad}/bingkai', [AdController::class, 'previewFrame'])->whereNumber('ad')->name('ad.preview-frame');
Route::redirect('/admin/pratinjau-iklan/{ad}', '/admin/pratinjau-kampanye/{ad}', 301)->whereNumber('ad');
Route::redirect('/admin/ads', '/admin/kampanye', 301);
Route::redirect('/admin/campaigns', '/admin/kampanye', 301);
Route::redirect('/admin/campaign-report', '/admin/laporan-kampanye', 301);
Route::redirect('/admin/ad-report', '/admin/laporan-kampanye', 301);
Route::get('/admin/pratinjau-halaman/{page}', [PagePreviewController::class, 'show'])->whereNumber('page')->name('page.preview');
Route::get('/admin/pratinjau-halaman/{page}/bingkai', [PagePreviewController::class, 'frame'])->whereNumber('page')->name('page.preview-frame');
Route::get('/admin/pratinjau-tampilan', [PagePreviewController::class, 'layout'])->name('layout.preview');

Route::get('/robots.txt', [SeoController::class, 'robots']);
// Tautan login dari email saat brute force terdeteksi; tanda tangan URL wajib valid
Route::get('/admin/login/link/{token}', LoginLinkController::class)->middleware(['signed', 'throttle:10,1'])->name('login.verify');
Route::get('/sitemap.xml', [SeoController::class, 'index']);
Route::get('/sitemap-{type}.xml', [SeoController::class, 'show'])->where('type', implode('|', SeoController::TYPES));
Route::get('/sitemap-{type}-{page}.xml', [SeoController::class, 'showNumbered'])->where(['type' => implode('|', SeoController::TYPES), 'page' => '[0-9]+']);

// Halaman statis (tentang, kontak, dsb.) — harus paling akhir.
Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '(?!admin$|up$|go$|ad$)[a-z0-9\-]+')->name('page.show');
