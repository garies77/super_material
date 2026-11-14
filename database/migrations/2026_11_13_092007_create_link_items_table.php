<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Blok/tombol di halaman link-in-bio: tautan, judul pemisah, produk katalog, atau WhatsApp. */
    public function up(): void
    {
        Schema::create('link_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('link');
            $table->string('label');
            // Bentuk tujuan sama dengan App\Support\LinkTarget
            $table->string('link_type', 20)->nullable();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('url', 2000)->nullable();
            $table->boolean('new_tab')->default(true);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('whatsapp_number', 30)->nullable();
            $table->string('whatsapp_message', 500)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['link_page_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_items');
    }
};
