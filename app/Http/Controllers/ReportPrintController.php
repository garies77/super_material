<?php

namespace App\Http\Controllers;

use App\Support\Reports\ReportPeriod;
use App\Support\Reports\ReportSummary;
use Illuminate\Http\Request;

/**
 * Ringkasan Pusat Laporan siap cetak untuk rapat: dibuka dari tombol "Cetak / PDF", lalu disimpan sebagai PDF
 * lewat dialog cetak browser (tanpa pustaka PDF tambahan). Hanya Admin/Editor.
 */
class ReportPrintController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->canReview() && $request->user()->is_active, 403);

        $period = ReportPeriod::fromFilters($request->only(['period', 'from', 'until']));

        return response(view('reports.print', [
            'summary' => ReportSummary::build($period, 10),
            'generatedAt' => ReportSummary::generatedAt(),
            'author' => $request->user()->name,
        ]))->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
