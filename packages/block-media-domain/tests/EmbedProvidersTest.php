<?php

declare(strict_types=1);

use SpeedySpec\Block\Media\Domain\EmbedProviders;

test('a link from an allowed provider is embedded through the provider\'s own address', function (
    string $url,
    string $provider,
    string $src,
    string $link,
) {
    $embed = EmbedProviders::embedFor($url);

    expect($embed?->provider)->toBe($provider)->and($embed?->src)->toBe($src)->and($embed?->link)->toBe($link);
})->with([
    'youtube watch' => [
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'youtube',
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ],
    'youtube without www, with more query' => [
        'https://youtube.com/watch?v=dQw4w9WgXcQ&t=42',
        'youtube',
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ],
    'youtu.be' => [
        'https://youtu.be/dQw4w9WgXcQ',
        'youtube',
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ],
    'shorts' => [
        'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        'youtube',
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ],
    'vimeo' => [
        'https://vimeo.com/76979871',
        'vimeo',
        'https://player.vimeo.com/video/76979871',
        'https://vimeo.com/76979871',
    ],
]);

test('a link that only looks allowed is not embedded', function (string $url) {
    expect(EmbedProviders::embedFor($url))->toBeNull()
        ->and(preg_match('#' . str_replace('#', '\\#', EmbedProviders::PATTERN) . '#D', $url))->toBe(0);
})->with([
    'lookalike host' => ['https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ'],
    'allowed name in the query' => ['https://evil.test/?u=youtube.com/watch?v=dQw4w9WgXcQ'],
    'user part' => ['https://youtu.be@evil.test/dQw4w9WgXcQ'],
    'script' => ['javascript:alert(1)'],
    'plain http' => ['http://youtu.be/dQw4w9WgXcQ'],
    'short id' => ['https://youtu.be/dQw4w9WgXc'],
    'port' => ['https://www.youtube.com:8443/watch?v=dQw4w9WgXcQ'],
    'vimeo letters' => ['https://vimeo.com/abc'],
    'trailing newline' => ["https://youtu.be/dQw4w9WgXcQ\n"],
]);

test('a map is embedded from OpenStreetMap around its coordinates', function () {
    $map = EmbedProviders::mapFor(51.5, -0.12, 15);

    expect($map->provider)->toBe('openstreetmap')
        ->and($map->src)->toBe(
            'https://www.openstreetmap.org/export/embed.html?bbox=-0.130986,51.494507,-0.109014,51.505493'
            . '&layer=mapnik&marker=51.5,-0.12',
        )
        ->and($map->link)->toBe('https://www.openstreetmap.org/?mlat=51.5&mlon=-0.12#map=15/51.5/-0.12');
});
