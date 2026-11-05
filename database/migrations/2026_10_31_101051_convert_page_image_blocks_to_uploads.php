<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Blok "Gambar" halaman statis pindah dari editor teks ke unggahan berkas (lihat Page::upgradeLegacyImageBlocks). */
    public function up(): void
    {
        DB::table('pages')->select(['id', 'blocks', 'draft_blocks'])->orderBy('id')->each(function (object $page) {
            $changes = [];
            foreach (['blocks', 'draft_blocks'] as $column) {
                $blocks = json_decode((string) $page->{$column}, true);
                if (! is_array($blocks)) {
                    continue;
                }

                $upgraded = Page::upgradeLegacyImageBlocks($blocks);
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
        // Format lama tidak dipulihkan; blok baru tetap tampil dengan renderer saat ini
    }
};
