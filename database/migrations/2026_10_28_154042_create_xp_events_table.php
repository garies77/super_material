<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat poin pengalaman (XP) gamifikasi. Satu baris per pemberian; award_key unik mencegah poin ganda
     * untuk konten yang sama. Admin dapat membatalkan baris (revoked_at) tanpa menghapus riwayatnya.
     */
    public function up(): void
    {
        Schema::create('xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('reason');
            $table->nullableMorphs('subject');
            $table->string('award_key')->unique();
            $table->json('meta')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_events');
    }
};
