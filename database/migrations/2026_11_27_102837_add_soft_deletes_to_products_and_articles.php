<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arsip (soft delete) produk dan artikel: dihapus dari panel atau lewat MCP berarti diarsipkan dan bisa dipulihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            });
        }
    }
};
