<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Support\WebhookEvents;
use Illuminate\Console\Command;

/**
 * Artikel terjadwal tayang saat waktunya tiba tanpa ada penyimpanan, jadi event article.published-nya
 * dikirim dari sini (dijadwalkan tiap menit). Artikel yang terbit langsung sudah diumumkan saat disimpan.
 */
class AnnounceScheduledArticles extends Command
{
    protected $signature = 'webhooks:announce-scheduled';

    protected $description = 'Kirim webhook article.published untuk artikel terjadwal yang baru saja tayang';

    public function handle(): int
    {
        $count = 0;
        Article::query()->whereNull('announced_at')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->orderBy('id')
            ->each(function (Article $article) use (&$count) {
                WebhookEvents::announceArticle($article);
                $count++;
            });

        $this->info("{$count} artikel diumumkan.");

        return self::SUCCESS;
    }
}
