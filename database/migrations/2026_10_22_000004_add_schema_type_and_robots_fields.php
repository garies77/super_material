<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD 9.11.4, 9.11.6 & 9.13.1:
 * - articles.schema_type: BlogPosting (bawaan), Article, atau NewsArticle.
 * - articles.content_updated_at: tanggal pembaruan bermakna untuk dateModified, article:modified_time, dan lastmod sitemap.
 * - nofollow per halaman di editor SEO.
 */
return new class extends Migration
{
    private array $robotsTables = ['articles', 'products', 'categories', 'pages'];

    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('schema_type', 20)->default('BlogPosting')->after('slug');
            $table->dateTime('content_updated_at')->nullable()->after('published_at');
        });

        // Belum ada riwayat pembaruan bermakna: mulai dari tanggal terbit
        DB::table('articles')->update(['content_updated_at' => DB::raw('published_at')]);

        foreach ($this->robotsTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('nofollow')->default(false)->after('noindex');
            });
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['schema_type', 'content_updated_at']);
        });

        foreach ($this->robotsTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('nofollow');
            });
        }
    }
};
