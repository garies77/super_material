<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alur tinjauan untuk peran Kontributor (anak PKL): pembuat konten, status tinjauan, dan catatan perbaikan.
     * Konten lama tidak punya pembuat tercatat (null) sehingga hanya bisa diubah Admin/Editor.
     */
    public function up(): void
    {
        foreach (['products', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('review_status', 20)->nullable()->index();
                $table->text('review_note')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
                $table->dropConstrainedForeignId('submitted_by');
                $table->dropColumn(['review_status', 'review_note', 'submitted_at']);
            });
        }
    }
};
