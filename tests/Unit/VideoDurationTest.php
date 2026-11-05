<?php

use App\Support\Seo\VideoDuration;
use App\Support\YouTube;

it('parses admin duration input into seconds', function (mixed $input, ?int $seconds) {
    expect(VideoDuration::parse($input))->toBe($seconds);
})->with([
    'menit:detik' => ['4:32', 272],
    'jam:menit:detik' => ['1:02:10', 3730],
    'detik saja' => ['272', 272],
    'kosong' => ['', null],
    'detik > 59' => ['4:75', null],
    'teks' => ['empat menit', null],
]);

it('converts seconds to ISO 8601 and back', function (int $seconds, string $iso) {
    expect(VideoDuration::iso($seconds))->toBe($iso)
        ->and(VideoDuration::fromIso($iso))->toBe($seconds);
})->with([
    [0, 'PT0S'],
    [59, 'PT59S'],
    [272, 'PT4M32S'],
    [3600, 'PT1H'],
    [3730, 'PT1H2M10S'],
]);

it('extracts the YouTube video id from every supported URL format', function (string $url) {
    expect(YouTube::videoId($url))->toBe('abcDEF12345');
})->with([
    'https://www.youtube.com/watch?v=abcDEF12345',
    'https://www.youtube.com/watch?feature=share&v=abcDEF12345',
    'https://youtu.be/abcDEF12345?si=xyz',
    'https://www.youtube.com/shorts/abcDEF12345',
    'https://www.youtube.com/embed/abcDEF12345',
    'https://www.youtube-nocookie.com/embed/abcDEF12345',
    'https://www.youtube.com/live/abcDEF12345',
]);

it('rejects non-YouTube URLs', function () {
    expect(YouTube::videoId('https://vimeo.com/123456789'))->toBeNull()
        ->and(YouTube::videoId(null))->toBeNull();
});
