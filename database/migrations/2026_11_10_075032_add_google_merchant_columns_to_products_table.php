<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak sinkron Google Merchant Center: offer ID yang sudah terkirim (untuk menghapus varian yang hilang),
     * waktu sinkron terakhir, dan pesan galat terakhir dari Merchant API.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('google_merchant_offer_ids')->nullable();
            $table->timestamp('google_merchant_synced_at')->nullable();
            $table->text('google_merchant_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['google_merchant_offer_ids', 'google_merchant_synced_at', 'google_merchant_error']);
        });
    }
};
