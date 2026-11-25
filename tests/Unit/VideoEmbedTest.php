<?php

use App\Support\VideoEmbed;

it('builds the official embed player for each supported platform', function (string $url, string $provider, string $src, bool $portrait) {
    expect(VideoEmbed::fromUrl($url))->toBe(['provider' => $provider, 'src' => $src, 'portrait' => $portrait]);
})->with([
    'youtube' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false],
    'youtube shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', true],
    'instagram reel' => ['https://www.instagram.com/reel/C1a2B3c4D5e/?igsh=abc', 'instagram', 'https://www.instagram.com/reel/C1a2B3c4D5e/embed/', true],
    'instagram post' => ['https://www.instagram.com/p/C1a2B3c4D5e/', 'instagram', 'https://www.instagram.com/p/C1a2B3c4D5e/embed/', true],
    'tiktok' => ['https://www.tiktok.com/@jayaintero/video/7312345678901234567?lang=id', 'tiktok', 'https://www.tiktok.com/player/v1/7312345678901234567?rel=0', true],
    'facebook video' => ['https://www.facebook.com/jayaintero/videos/1234567890/', 'facebook', 'https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Fjayaintero%2Fvideos%2F1234567890&show_text=false', false],
    'facebook reel' => ['https://www.facebook.com/reel/1234567890', 'facebook', 'https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Freel%2F1234567890&show_text=false', true],
]);

it('rejects links that are not embeddable videos', function (?string $url) {
    expect(VideoEmbed::fromUrl($url))->toBeNull();
})->with([
    'kosong' => [''],
    'null' => [null],
    'profil instagram' => ['https://www.instagram.com/jayaintero/'],
    'profil tiktok' => ['https://www.tiktok.com/@jayaintero'],
    'tautan pendek tiktok' => ['https://vt.tiktok.com/ZSabc123/'],
    'http tanpa tls' => ['http://www.tiktok.com/@jayaintero/video/7312345678901234567'],
    'domain tiruan' => ['https://www.instagram.com.evil.test/reel/C1a2B3c4D5e/'],
    'javascript' => ['javascript:alert(1)'],
]);
