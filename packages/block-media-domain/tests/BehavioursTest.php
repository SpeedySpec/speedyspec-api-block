<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\IssueKind;

function issuesOf(string $id, \SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument $resolved): array
{
    return array_values(array_map(
        fn ($issue) => [$issue->kind, $issue->message, $issue->path],
        array_filter($resolved->issues, fn ($issue) => $issue->blockId === $id),
    ));
}

test('an image is drawn at the chosen size with every size offered', function () {
    $view = viewOf(block('i', 'core/image', ['media' => 'photo', 'size' => 'large', 'caption' => 'Hi']));

    expect($view)->toMatchArray([
        'url' => 'https://e.test/photo-800.jpg',
        'width' => 800,
        'height' => 533,
        'alt' => 'A cat',
        'caption' => 'Hi',
        'link' => '',
        'srcset' => 'https://e.test/photo-150.jpg 150w, https://e.test/photo-800.jpg 800w, '
            . 'https://e.test/photo.jpg 1200w',
    ]);
});

test('an image without a size, or with one it does not have, is the whole file', function () {
    expect(viewOf(block('i', 'core/image', ['media' => 'photo']))['url'])->toBe('https://e.test/photo.jpg')
        ->and(viewOf(block('i', 'core/image', ['media' => 'photo', 'size' => 'huge']))['width'])->toBe(1200);
});

test('an image\'s alt text is the library\'s unless the block gives its own, which may be empty', function () {
    expect(viewOf(block('i', 'core/image', ['media' => 'photo', 'alt' => 'Mine']))['alt'])->toBe('Mine')
        ->and(viewOf(block('i', 'core/image', ['media' => 'photo', 'alt' => '']))['alt'])->toBe('')
        ->and(viewOf(block('i', 'core/image', ['media' => 'other']))['srcset'])->toBe('https://e.test/other.png 40w');
});

test('a block whose media is missing or wrong is unavailable and says why', function (
    string $type,
    array $settings,
    string $message,
) {
    $resolved = resolve(block('x', $type, $settings), block('ok', 'core/icon'));

    expect($resolved->find('x')?->view)->toBeNull()
        ->and($resolved->find('ok'))->not->toBeNull()
        ->and(issuesOf('x', $resolved))->toBe([[IssueKind::UnavailableMedia, $message, 'media']]);
})->with([
    'image gone' => ['core/image', ['media' => 'gone'], 'The chosen image no longer exists.'],
    'not an image' => ['core/image', ['media' => 'doc'], 'The chosen file is not an image.'],
    'a sound in a video block' => ['core/video', ['media' => 'song'], 'The chosen file is not a video.'],
    'audio gone' => ['core/audio', ['media' => 'gone'], 'The chosen audio file no longer exists.'],
    'gallery all gone' => ['core/gallery', ['media' => ['gone', 'doc']], 'None of the chosen images exist any more.'],
]);

test('a block with nothing chosen yet is not set up: an empty view and no issue', function (
    string $type,
    string $key,
    mixed $empty,
) {
    $resolved = resolve(block('x', $type));

    expect($resolved->find('x')?->view?->values[$key])->toBe($empty)->and(issuesOf('x', $resolved))->toBe([]);
})->with([
    'image' => ['core/image', 'url', ''],
    'gallery' => ['core/gallery', 'items', []],
    'video' => ['core/video', 'url', ''],
    'audio' => ['core/audio', 'url', ''],
    'embed' => ['core/embed', 'src', ''],
    'map' => ['core/map', 'src', ''],
]);

test('a gallery shows the images it can find, in order', function () {
    $view = viewOf(block('g', 'core/gallery', ['media' => ['other', 'gone', 'doc', 'photo'], 'columns' => 2]));

    expect(array_column($view['items'], 'url'))->toBe(['https://e.test/other.png', 'https://e.test/photo.jpg'])
        ->and($view['items'][1])
        ->toBe(['url' => 'https://e.test/photo.jpg', 'width' => 1200, 'height' => 800, 'alt' => 'A cat'])
        ->and($view['columns'])->toBe(2);
});

test('a video plays muted whenever it plays by itself, and a missing poster is dropped', function () {
    $view = viewOf(block('v', 'core/video', ['media' => 'clip', 'poster' => 'gone', 'autoplay' => true]));
    $quiet = viewOf(block('v', 'core/video', ['media' => 'clip', 'poster' => 'photo']));

    expect($view)->toMatchArray([
        'kind' => 'video',
        'url' => 'https://e.test/clip.mp4',
        'mimeType' => 'video/mp4',
        'poster' => '',
        'controls' => true,
        'autoplay' => true,
        'muted' => true,
        'loop' => false,
    ])->and($quiet['muted'])->toBeFalse()->and($quiet['poster'])->toBe('https://e.test/photo.jpg');
});

test('an audio block plays its sound', function () {
    expect(viewOf(block('a', 'core/audio', ['media' => 'song'])))->toMatchArray([
        'kind' => 'audio',
        'url' => 'https://e.test/song.mp3',
        'poster' => '',
    ]);
});

test('an embed gives the provider\'s address and link', function () {
    expect(viewOf(block('e', 'core/embed', ['url' => 'https://youtu.be/dQw4w9WgXcQ'])))->toMatchArray([
        'provider' => 'youtube',
        'src' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Embedded content',
    ]);
});

test('an embed whose stored link is not allowed is unavailable', function () {
    $resolved = resolve(block('e', 'core/embed', ['url' => 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ']));

    expect(issuesOf('e', $resolved)[0][0])->toBe(IssueKind::InvalidSettings);
});

test('a map with coordinates is embedded; with only an address it is a link', function () {
    $placed = viewOf(block('m', 'core/map', ['latitude' => 51.5, 'longitude' => -0.12, 'address' => 'London']));
    $named = viewOf(block('m', 'core/map', ['address' => 'Baker Street, London']));

    expect($placed['src'])->toStartWith('https://www.openstreetmap.org/export/embed.html?bbox=')
        ->and($placed['address'])->toBe('London')
        ->and($named['src'])->toBe('')
        ->and($named['link'])->toBe('https://www.openstreetmap.org/search?query=Baker%20Street%2C%20London')
        ->and($named['title'])->toBe('Map');
});

test('a map with one coordinate and no address is not set up yet', function () {
    $resolved = resolve(block('m', 'core/map', ['latitude' => 51.5]));

    expect($resolved->find('m')?->view?->values)->toMatchArray(['src' => '', 'link' => ''])
        ->and(issuesOf('m', $resolved))->toBe([]);
});

test('an icon list item takes the list\'s icon unless it has its own', function () {
    $resolved = resolve(block(
        'l',
        'core/icon-list',
        ['icon' => 'star'],
        block('a', 'core/icon-list-item', ['text' => 'Inherits']),
        block('b', 'core/icon-list-item', ['icon' => 'heart', 'text' => 'Own']),
    ));

    expect($resolved->find('a')?->view->values)->toBe(['icon' => 'star', 'text' => 'Inherits'])
        ->and($resolved->find('b')?->view->values['icon'])->toBe('heart');
});
