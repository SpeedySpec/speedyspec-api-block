<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\MediaPage;
use SpeedySpec\Block\Domain\ValueObjects\MediaQuery;
use SpeedySpec\Block\Domain\ValueObjects\MediaSize;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;

test('a media kind is told from a MIME type', function (string $mime, MediaKind $kind) {
    expect(MediaKind::fromMimeType($mime))->toBe($kind);
})->with([
    ['image/webp', MediaKind::Image],
    ['video/mp4', MediaKind::Video],
    ['audio/ogg', MediaKind::Audio],
    ['application/pdf', MediaKind::File],
    ['IMAGE/PNG', MediaKind::Image],
]);

test('a media query keeps its paging within bounds', function () {
    $wide = new MediaQuery(page: 0, perPage: 1000);
    $narrow = new MediaQuery(perPage: 0);

    expect($wide->page)->toBe(1)
        ->and($wide->perPage)->toBe(100)
        ->and($narrow->perPage)->toBe(1)
        ->and((new MediaQuery())->perPage)->toBe(20);
});

test('a media item finds its sizes by name', function () {
    $thumb = new MediaSize('thumb', 'https://e.test/t.jpg', 150, 100);
    $item = new MediaItem('1', 'https://e.test/a.jpg', MediaKind::Image, 'image/jpeg', 'a', sizes: [$thumb]);

    expect($item->size('thumb'))->toBe($thumb)->and($item->size('none'))->toBeNull();
});

test('a media item becomes an array a builder can read', function () {
    $item = new MediaItem(
        '1',
        'https://e.test/a.jpg',
        MediaKind::Image,
        'image/jpeg',
        'A',
        'Alt',
        300,
        200,
        [new MediaSize('thumb', 'https://e.test/t.jpg', 150, 100)],
    );

    expect($item->toArray())->toBe([
        'id' => '1',
        'url' => 'https://e.test/a.jpg',
        'kind' => 'image',
        'mimeType' => 'image/jpeg',
        'title' => 'A',
        'alt' => 'Alt',
        'width' => 300,
        'height' => 200,
        'sizes' => [['name' => 'thumb', 'url' => 'https://e.test/t.jpg', 'width' => 150, 'height' => 100]],
    ]);
});

test('an upload and a page carry what they were given', function () {
    $upload = new MediaUpload('a.mp3', 'audio/mpeg', 'ID3');
    $page = new MediaPage([], 7);

    expect($upload->kind())->toBe(MediaKind::Audio)->and($page->total)->toBe(7)->and($page->items)->toBe([]);
});

test('an upload is named by the type found in it, not the one it was given', function (
    string $type,
    string $extension,
) {
    expect((new MediaUpload('photo.html', $type, 'x'))->extension())->toBe($extension)
        ->and((new MediaUpload('photo.html', $type, 'x'))->storedName())->toBe('photo.' . $extension);
})->with([
    ['image/jpeg', 'jpg'],
    ['image/png', 'png'],
    ['video/mp4', 'mp4'],
    ['audio/mpeg', 'mp3'],
    ['application/pdf', 'pdf'],
    ['application/x-unknown', 'bin'],
]);

test('an upload\'s stored name keeps its words but not its path or extension', function (string $name, string $stored) {
    expect((new MediaUpload($name, 'image/png', 'x'))->storedName())->toBe($stored);
})->with([
    ['../../etc/passwd', 'passwd.png'],
    ['C:\\me\\Holiday photo.jpeg', 'Holiday photo.png'],
    ['no-extension', 'no-extension.png'],
    ['.htaccess', 'upload.png'],
    ['', 'upload.png'],
]);
