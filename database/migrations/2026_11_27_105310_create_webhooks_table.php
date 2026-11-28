<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook keluar (PRD 8.12.10): product.published, product.price_changed, article.published, ditandatangani HMAC.
 * articles.announced_at menandai artikel yang event terbitnya sudah dikirim, termasuk artikel terjadwal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 1000);
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->timestamp('announced_at')->nullable();
        });

        // Artikel yang sudah terbit dianggap sudah diumumkan, agar webhook tidak membanjiri saat fitur ini aktif
        DB::table('articles')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->update(['announced_at' => DB::raw('published_at')]);
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('announced_at');
        });

        Schema::dropIfExists('webhooks');
    }
};
