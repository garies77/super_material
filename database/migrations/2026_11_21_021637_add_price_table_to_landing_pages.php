<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pasang blok "Tabel harga" (gaya halaman daftar harga) tepat setelah grid produk di landing marketplace dan
 * pengadaan (jual-peralatan-proyek-di-*). Blok masuk ke salinan kerja (draft_blocks), jadi halaman yang sudah terbit
 * tidak berubah sampai admin menekan Terbitkan. Halaman yang sudah punya tabel harga atau tidak punya grid dilewati.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pages = DB::table('pages')->where('slug', 'like', 'jual-peralatan-proyek-di-%')->get(['id', 'blocks', 'draft_blocks']);

        foreach ($pages as $page) {
            $blocks = json_decode((string) ($page->draft_blocks ?? $page->blocks), true);
            if (! is_array($blocks) || collect($blocks)->contains('type', 'price_table')) {
                continue;
            }

            $gridIndexes = array_keys(array_filter($blocks, fn ($b) => ($b['type'] ?? null) === 'products'));
            $channelId = $gridIndexes ? ($blocks[max($gridIndexes)]['data']['channel_id'] ?? null) : null;
            if (! $channelId) {
                continue;
            }

            $channelName = (string) DB::table('sales_channels')->where('id', $channelId)->value('name');
            array_splice($blocks, max($gridIndexes) + 1, 0, [[
                'type' => 'price_table',
                'data' => [
                    'heading' => "Daftar Harga Peralatan & Bahan Proyek di {$channelName}",
                    'intro' => "Ringkasan harga acuan produk kami yang tersedia di {$channelName}. Tekan tombol beli untuk membuka halaman produknya di {$channelName}.",
                    'channel_id' => $channelId,
                    'group_by' => 'category',
                    'limit' => 48,
                ],
            ]]);

            DB::table('pages')->where('id', $page->id)->update([
                'draft_blocks' => json_encode(array_values($blocks), JSON_UNESCAPED_UNICODE),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function down(): void
    {
        $pages = DB::table('pages')->where('slug', 'like', 'jual-peralatan-proyek-di-%')->whereNotNull('draft_blocks')->get(['id', 'draft_blocks']);

        foreach ($pages as $page) {
            $blocks = array_values(array_filter(
                (array) json_decode((string) $page->draft_blocks, true),
                fn ($b) => ($b['type'] ?? null) !== 'price_table',
            ));
            DB::table('pages')->where('id', $page->id)->update(['draft_blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE)]);
        }
    }
};
