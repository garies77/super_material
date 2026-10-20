<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->after('email'); // admin, editor, author
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->text('intro')->nullable();
            $table->longText('bottom_content')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->json('specifications')->nullable();
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->unsignedBigInteger('price_min')->nullable();
            $table->unsignedBigInteger('price_max')->nullable();
            $table->string('unit')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->unsignedBigInteger('reference_price')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('marketplace'); // marketplace | whatsapp
            $table->string('color')->default('#16a34a');
            $table->json('allowed_hosts')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->constrained()->cascadeOnDelete();
            $table->string('url')->nullable(); // URL marketplace atau nomor WhatsApp
            $table->string('message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'sales_channel_id']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('article_product', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'product_id']);
        });

        Schema::create('article_category', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'category_id']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->morphs('faqable');
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('click_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->text('page_url')->nullable();
            $table->text('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('user_agent', 120)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('code')->default(301);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'redirects', 'click_events', 'pages', 'faqs', 'article_category', 'article_product', 'articles', 'product_links', 'sales_channels', 'product_variants', 'products', 'brands', 'categories'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
