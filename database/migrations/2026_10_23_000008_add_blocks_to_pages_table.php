<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Page builder: konten halaman berupa blok (tipe, urutan, konfigurasi).
 * blocks = versi terbit; draft_blocks = salinan kerja editor sampai dipublikasikan.
 * Isi lama dipindah ke satu blok teks agar tidak ada konten yang hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('content');
            $table->json('draft_blocks')->nullable()->after('blocks');
            $table->foreignId('updated_by')->nullable()->after('is_published')->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('pages')->whereNotNull('content')->get(['id', 'content']) as $page) {
            $blocks = json_encode([['type' => 'text', 'data' => ['content' => $page->content]]]);
            DB::table('pages')->where('id', $page->id)->update(['blocks' => $blocks, 'draft_blocks' => $blocks]);
        }
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['blocks', 'draft_blocks']);
        });
    }
};
