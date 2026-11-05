<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pembuat video (nama & URL channel YouTube) untuk properti creator VideoObject. */
    public function up(): void
    {
        foreach (['videos', 'product_media'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('creator_name')->nullable()->after('description');
                $table->string('creator_url', 500)->nullable()->after('creator_name');
            });
        }
    }

    public function down(): void
    {
        foreach (['videos', 'product_media'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['creator_name', 'creator_url']);
            });
        }
    }
};
