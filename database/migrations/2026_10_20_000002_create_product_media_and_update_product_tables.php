<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('image'); // image, video
            $table->string('path');
            $table->string('provider')->default('upload'); // upload, youtube
            $table->string('poster_path')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('badge')->nullable()->after('name');
            $table->string('shipping_note')->nullable()->after('unit');
            $table->string('guarantee_note')->nullable()->after('shipping_note');
            $table->decimal('rating_value', 3, 1)->nullable()->after('guarantee_note');
            $table->unsignedInteger('rating_count')->nullable()->after('rating_value');
            $table->date('rating_updated_at')->nullable()->after('rating_count');
            $table->string('price_display')->default('show')->after('rating_updated_at'); // show | hidden
            $table->boolean('show_quantity')->default(true)->after('price_display');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('image_path')->nullable()->after('slug');
            $table->unsignedBigInteger('price')->nullable()->after('reference_price');
            $table->unsignedBigInteger('compare_at_price')->nullable()->after('price');
            $table->dateTime('discount_starts_at')->nullable()->after('compare_at_price');
            $table->dateTime('discount_ends_at')->nullable()->after('discount_starts_at');
            $table->string('option_group')->default('Varian')->after('discount_ends_at');
            $table->boolean('is_active')->default(true)->after('option_group');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });

        // Migrate reference_price to price for existing variants
        DB::table('product_variants')->whereNull('price')->whereNotNull('reference_price')->update([
            'price' => DB::raw('reference_price'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'badge',
                'shipping_note',
                'guarantee_note',
                'rating_value',
                'rating_count',
                'rating_updated_at',
                'price_display',
                'show_quantity',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'image_path',
                'price',
                'compare_at_price',
                'discount_starts_at',
                'discount_ends_at',
                'option_group',
                'is_active',
                'sort_order',
            ]);
        });
    }
};
