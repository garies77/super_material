<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Nama marketplace asal rating manual (mis. Shopee). Rating ini hanya tampilan, tidak masuk JSON-LD.
            $table->string('rating_source')->nullable()->after('rating_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('rating_source');
        });
    }
};
