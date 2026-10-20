<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('image_caption')->nullable()->after('image');
            $table->string('image_credit')->nullable()->after('image_caption');
            $table->string('editor_name')->nullable()->after('author_id');
            $table->string('author_initials', 20)->nullable()->after('editor_name');
            $table->string('editor_initials', 20)->nullable()->after('author_initials');
            $table->string('dateline')->nullable()->default('Medan, Supermaterial --')->after('editor_initials');
            $table->unsignedBigInteger('views_count')->default(0)->after('canonical_url');
            $table->boolean('is_featured')->default(false)->after('noindex');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');
            $table->index(['taggable_type', 'taggable_id']);
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });

        Schema::create('article_related', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('related_article_id')->constrained('articles')->cascadeOnDelete();
            $table->primary(['article_id', 'related_article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_related');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'image_caption',
                'image_credit',
                'editor_name',
                'author_initials',
                'editor_initials',
                'dateline',
                'views_count',
                'is_featured',
            ]);
        });
    }
};
