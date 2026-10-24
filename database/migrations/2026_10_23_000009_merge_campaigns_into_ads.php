<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu fitur Kampanye: Kampanye Artikel (ads) dan Banner Kampanye pop-up (campaigns) digabung di tabel ads.
 * - placement: banner | sidebar; display: inline (di halaman) | popup.
 * - pages: halaman tujuan (home, product, article, category, page).
 * Tabel campaigns/campaign_events tidak dihapus agar data lama tetap tersimpan.
 */
return new class extends Migration
{
    private const ALL_PAGES = ['home', 'product', 'article', 'category', 'page'];

    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->string('placement', 16)->default('banner')->after('slot');
            $table->string('display', 16)->default('inline')->after('placement');
            $table->json('pages')->nullable()->after('display');
            $table->string('device', 16)->default('all');
            $table->string('trigger', 16)->default('delay');
            $table->unsignedSmallInteger('delay_seconds')->default(5);
            $table->unsignedTinyInteger('scroll_percent')->default(30);
            $table->string('frequency', 16)->default('days');
            $table->unsignedSmallInteger('frequency_days')->default(7);
            $table->boolean('allow_never_again')->default(false);
            $table->unsignedBigInteger('legacy_campaign_id')->nullable()->unique();
        });

        // Kampanye artikel lama: tetap di artikel, sidebar menjadi format Sidebar
        DB::table('ads')->update(['pages' => json_encode(['article'])]);
        DB::table('ads')->where('slot', 'sidebar')->update(['placement' => 'sidebar']);

        if (! Schema::hasTable('campaigns')) {
            return;
        }

        foreach (DB::table('campaigns')->get() as $c) {
            $pages = match ($c->targeting) {
                'home' => ['home'],
                'products' => ['product'],
                'articles' => ['article'],
                'categories' => ['category', 'product'],
                default => self::ALL_PAGES,
            };

            $adId = DB::table('ads')->insertGetId([
                'name' => $c->name,
                'slot' => 'popup',
                'placement' => 'banner',
                'display' => 'popup',
                'pages' => json_encode($pages),
                'format' => $c->layout === 'image_text' ? 'text' : 'image',
                'label' => 'Sorotan',
                'target_url' => $c->target_url,
                'image' => $c->image,
                'image_mobile' => $c->image_mobile,
                'image_alt' => $c->image_alt,
                'title' => $c->title,
                'body' => $c->body,
                'cta_label' => $c->cta_label,
                'targeting' => $c->targeting === 'categories' ? 'categories' : 'all',
                'target_category_ids' => $c->target_category_ids,
                'starts_at' => $c->starts_at,
                'ends_at' => $c->ends_at,
                'is_active' => $c->status === 'active',
                'priority' => $c->priority,
                'device' => $c->device,
                'trigger' => $c->trigger,
                'delay_seconds' => $c->delay_seconds,
                'scroll_percent' => $c->scroll_percent,
                'frequency' => $c->frequency,
                'frequency_days' => $c->frequency_days,
                'allow_never_again' => $c->allow_never_again,
                'legacy_campaign_id' => $c->id,
                'created_at' => $c->created_at,
                'updated_at' => now(),
            ]);

            foreach (DB::table('campaign_events')->where('campaign_id', $c->id)->orderBy('id')->cursor() as $e) {
                DB::table('ad_events')->insert([
                    'ad_id' => $adId,
                    'type' => $e->type,
                    'slot' => 'popup',
                    'page_url' => $e->page_path,
                    'user_agent' => $e->user_agent,
                    'is_bot' => $e->is_bot,
                    'created_at' => $e->created_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('ads')->whereNotNull('legacy_campaign_id')->pluck('id');
        DB::table('ad_events')->whereIn('ad_id', $ids)->delete();
        DB::table('ads')->whereIn('id', $ids)->delete();

        Schema::table('ads', function (Blueprint $table) {
            $table->dropUnique(['legacy_campaign_id']);
            $table->dropColumn(['placement', 'display', 'pages', 'device', 'trigger', 'delay_seconds', 'scroll_percent',
                'frequency', 'frequency_days', 'allow_never_again', 'legacy_campaign_id']);
        });
    }
};
