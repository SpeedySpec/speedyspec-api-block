<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

function mediaType(string $name): BlockType
{
    $type = mediaContainer()->get(BlockTypeRegistryInterface::class)->get(BlockName::fromString($name));

    expect($type)->not->toBeNull();

    return $type;
}

function mediaSettingsValid(string $name, array $settings): bool
{
    return mediaContainer()->get(SchemaValidatorInterface::class)
        ->validate($settings, mediaType($name)->settingsSchema)->isValid();
}

const MEDIA_BLOCKS = [
    'core/image', 'core/gallery', 'core/video', 'core/audio', 'core/embed',
    'core/icon', 'core/icon-list', 'core/icon-list-item', 'core/map',
];

test('every media block is in the media category with a title, description and example', function () {
    foreach (MEDIA_BLOCKS as $name)
    {
        $type = mediaType($name);

        expect($type->catalogue->category)->toBe('media')
            ->and($type->catalogue->title)->not->toBe('')
            ->and($type->catalogue->description)->not->toBe('')
            ->and($type->examples)->not->toBe([]);
    }
});

test('media settings store ids and say what kind they hold', function () {
    $image = mediaType('core/image')->settingsSchema['properties']['media'];
    $gallery = mediaType('core/gallery')->settingsSchema['properties']['media'];
    $poster = mediaType('core/video')->settingsSchema['properties']['poster'];

    expect($image)->toMatchArray(['type' => 'string', 'default' => '', 'x-control' => 'media'])
        ->and($image['x-media-kind'])->toBe('image')
        ->and($gallery)->toMatchArray(['type' => 'array', 'default' => [], 'x-control' => 'media-list'])
        ->and($gallery['x-media-kind'])->toBe('image')
        ->and($poster['x-media-kind'])->toBe('image')
        ->and(mediaType('core/audio')->settingsSchema['properties']['media']['x-media-kind'])->toBe('audio');
});

test('icon list items sit only in icon lists and take the list\'s icon', function () {
    $item = mediaType('core/icon-list-item');
    $list = mediaType('core/icon-list');

    expect($item->parents->allows(BlockName::fromString('core/icon-list')))->toBeTrue()
        ->and($item->parents->allows(BlockName::fromString('core/image')))->toBeFalse()
        ->and($list->children->allows(BlockName::fromString('core/icon-list-item')))->toBeTrue()
        ->and($list->children->allows(BlockName::fromString('core/image')))->toBeFalse()
        ->and($list->provides)->toBe(['core/icon' => 'icon'])
        ->and($item->uses)->toBe(['core/icon']);
});

test('the settings refuse what the blocks cannot use', function (string $name, array $settings) {
    expect(mediaSettingsValid($name, $settings))->toBeFalse();
})->with([
    'lookalike embed' => ['core/embed', ['url' => 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ']],
    'embed query trick' => ['core/embed', ['url' => 'https://evil.test/?u=youtube.com/watch?v=dQw4w9WgXcQ']],
    'embed user part' => ['core/embed', ['url' => 'https://youtu.be@evil.test/x']],
    'embed script' => ['core/embed', ['url' => 'javascript:alert(1)']],
    'embed http' => ['core/embed', ['url' => 'http://youtu.be/dQw4w9WgXcQ']],
    'gallery columns' => ['core/gallery', ['columns' => 7]],
    'latitude' => ['core/map', ['latitude' => 91]],
    'longitude' => ['core/map', ['longitude' => -181]],
    'zoom' => ['core/map', ['zoom' => 20]],
    'icon name' => ['core/icon', ['name' => 'Star!']],
    'image size' => ['core/image', ['size' => '../x']],
    'long address' => ['core/map', ['address' => str_repeat('a', 201)]],
]);

test('the settings accept what the blocks use', function (string $name, array $settings) {
    expect(mediaSettingsValid($name, $settings))->toBeTrue();
})->with([
    'youtube' => ['core/embed', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
    'empty embed' => ['core/embed', ['url' => '']],
    'decorative image' => ['core/image', ['media' => 'photo', 'alt' => '']],
    'gallery columns by width' => ['core/gallery', ['columns' => ['base' => 1, 'tablet' => 3]]],
    'map coordinates' => ['core/map', ['latitude' => 51.5, 'longitude' => -0.12, 'zoom' => 12]],
]);

test('defaults are fixed', function () {
    $defaults = fn (string $name): array => array_map(
        fn (array $property) => $property['default'] ?? null,
        mediaType($name)->settingsSchema['properties'],
    );

    expect($defaults('core/image'))
        ->toMatchArray(['media' => '', 'alt' => null, 'caption' => '', 'link' => '', 'size' => ''])
        ->and($defaults('core/gallery'))->toMatchArray(['media' => [], 'columns' => 3, 'caption' => ''])
        ->and($defaults('core/video'))->toMatchArray(['controls' => true, 'autoplay' => false, 'loop' => false])
        ->and($defaults('core/icon'))->toMatchArray(['name' => 'star', 'label' => ''])
        ->and($defaults('core/icon-list'))->toMatchArray(['icon' => 'check'])
        ->and($defaults('core/map'))->toMatchArray(['zoom' => 15, 'latitude' => null, 'longitude' => null])
        ->and($defaults('core/embed'))->toMatchArray(['url' => '', 'title' => 'Embedded content']);
});
