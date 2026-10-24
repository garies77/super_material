<?php

namespace App\Http\Controllers;

use App\Models\Page;

/**
 * Pratinjau halaman statis dengan layout frontend (Navbar, konten, Footer), memakai draf terbaru.
 * Hanya Admin/Editor; draf tidak pernah tampil di halaman publik.
 */
class PagePreviewController extends Controller
{
    public function show(Page $page)
    {
        $this->authorizePreview();

        return response(view('page-preview', ['page' => $page]))->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function frame(Page $page)
    {
        $this->authorizePreview();

        return response(view('page', ['page' => $page, 'blocks' => $page->draft_blocks ?? $page->blocks, 'preview' => true]))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Beranda dengan Navbar/Footer yang belum disimpan dari builder (dibaca SiteLayout dari sesi). */
    public function layout()
    {
        abort_unless(auth()->user()?->isAdmin() && auth()->user()->is_active, 403);

        return response(app(HomeController::class)())->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function authorizePreview(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'editor'], true) && auth()->user()->is_active, 403);
    }
}
