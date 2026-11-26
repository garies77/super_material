<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Blok "Embed video" (satu URL) menjadi blok "Video" berisi daftar video + tata letak (lihat Page::upgradeLegacyEmbedBlocks). */
    public function up(): void
    {
        DB::table('pages')->select(['id', 'blocks', 'draft_blocks'])->orderBy('id')->each(function (object $page) {
            $changes = [];
            foreach (['blocks', 'draft_blocks'] as $column) {
                $blocks = json_decode((string) $page->{$column}, true);
                if (! is_array($blocks)) {
                    continue;
                }

                $upgraded = Page::upgradeLegacyEmbedBlocks($blocks);
                if ($upgraded !== $blocks) {
                    $changes[$column] = json_encode($upgraded);
                }
            }

            if ($changes) {
                DB::table('pages')->where('id', $page->id)->update($changes);
            }
        });
    }

    public function down(): void
    {
        // Format lama tidak dipulihkan; renderer tetap membaca kedua format
    }
};
