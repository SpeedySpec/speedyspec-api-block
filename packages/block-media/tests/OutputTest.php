<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Media\Icons;

const SANDBOX = ' sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" loading="lazy"'
    . ' referrerpolicy="strict-origin-when-cross-origin"';

test('each media block draws its markup', function (Block $block, string $html) {
    expect(page($block))->toBe($html);
})->with([
    'image with sizes and caption' => [
        block('i', 'core/image', ['media' => 'photo', 'caption' => 'A <em>cat</em>']),
        '<figure><img src="https://e.test/photo.jpg" srcset="https://e.test/photo-150.jpg 150w, '
        . 'https://e.test/photo.jpg 1200w" sizes="100vw" width="1200" height="800" alt="A &quot;cat&quot;"'
        . ' loading="lazy" decoding="async"><figcaption>A <em>cat</em></figcaption></figure>',
    ],
    'image of unknown size, linked' => [
        block('i', 'core/image', ['media' => 'plain', 'link' => '/about', 'alt' => '']),
        '<figure><a href="/about"><img src="https://e.test/plain.png" alt="" loading="lazy" decoding="async"></a>'
        . '</figure>',
    ],
    'image with a script link' => [
        block('i', 'core/image', ['media' => 'plain', 'link' => 'javascript:alert(1)', 'alt' => 'x']),
        '<figure><img src="https://e.test/plain.png" alt="x" loading="lazy" decoding="async"></figure>',
    ],
    'video' => [
        block('v', 'core/video', ['media' => 'clip', 'poster' => 'plain', 'autoplay' => true, 'loop' => true]),
        '<figure><video src="https://e.test/clip.mp4" controls="" autoplay="" muted="" loop="" playsinline=""'
        . ' poster="https://e.test/plain.png" preload="metadata">'
        . '<a href="https://e.test/clip.mp4">Download the video</a>'
        . '</video></figure>',
    ],
    'audio without controls' => [
        block('a', 'core/audio', ['media' => 'song', 'controls' => false]),
        '<figure><audio src="https://e.test/song.mp3" preload="metadata">'
        . '<a href="https://e.test/song.mp3">Download the audio file</a></audio></figure>',
    ],
    'embed' => [
        block('e', 'core/embed', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Our <video>']),
        '<figure><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Our &lt;video&gt;"'
        . SANDBOX . ' allowfullscreen=""></iframe>'
        . '<a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">Our &lt;video&gt;</a></figure>',
    ],
    'map with coordinates' => [
        block('m', 'core/map', ['latitude' => 51.5, 'longitude' => -0.12, 'title' => 'Office']),
        '<figure><iframe src="https://www.openstreetmap.org/export/embed.html?bbox=-0.130986,51.494507,-0.109014,'
        . '51.505493&amp;layer=mapnik&amp;marker=51.5,-0.12" title="Office"' . SANDBOX . '></iframe>'
        . '<a href="https://www.openstreetmap.org/?mlat=51.5&amp;mlon=-0.12#map=15/51.5/-0.12">Office</a></figure>',
    ],
    'map with only an address' => [
        block('m', 'core/map', ['address' => '1 High St']),
        '<figure><a href="https://www.openstreetmap.org/search?query=1%20High%20St">1 High St</a></figure>',
    ],
    'decorative icon' => [
        block('c', 'core/icon', ['name' => 'check']),
        '<span class="b-icon" aria-hidden="true">' . Icons::svg('check') . '</span>',
    ],
    'labelled icon' => [
        block('c', 'core/icon', ['name' => 'phone', 'label' => 'Call us']),
        '<span class="b-icon" role="img" aria-label="Call us">' . Icons::svg('phone') . '</span>',
    ],
    'icon list' => [
        block(
            'l',
            'core/icon-list',
            ['icon' => 'star'],
            block('a', 'core/icon-list-item', ['text' => 'Fast']),
            block('b', 'core/icon-list-item', ['icon' => 'heart', 'text' => 'Kind']),
        ),
        '<ul class="b-icon-list"><li><span class="b-icon" aria-hidden="true">' . Icons::svg('star') . '</span>'
        . '<span>Fast</span></li><li><span class="b-icon" aria-hidden="true">' . Icons::svg('heart') . '</span>'
        . '<span>Kind</span></li></ul>',
    ],
]);

test('a gallery draws its images in a grid laid out by width', function () {
    $gallery = block('g', 'core/gallery', ['media' => ['photo', 'plain'], 'columns' => ['base' => 1, 'tablet' => 3]]);
    [$result, $css] = rendered([$gallery]);

    expect($result->output)->toMatch(
        '#^<figure><ul class="b-[a-z0-9]+"><li><img src="https://e.test/photo.jpg" width="1200" height="800" '
        . 'alt="A &quot;cat&quot;" loading="lazy" decoding="async"></li><li><img src="https://e.test/plain.png" '
        . 'alt="" loading="lazy" decoding="async"></li></ul></figure>$#',
    )
        ->and($css)->toContain('grid-template-columns:repeat(1, minmax(0, 1fr))')
        ->and($css)->toContain('@media (min-width:768px)')
        ->and($css)->toContain('repeat(3, minmax(0, 1fr))');
});

test('a block not set up yet draws nothing for visitors and a prompt for editors', function (
    string $type,
    string $prompt,
) {
    expect(page(block('x', $type)))->toBe('')
        ->and(page(block('x', $type), true))->toContain($prompt);
})->with([
    ['core/image', 'Choose an image'],
    ['core/gallery', 'Choose images'],
    ['core/video', 'Choose a video'],
    ['core/audio', 'Choose an audio file'],
    ['core/embed', 'Paste a YouTube or Vimeo link'],
    ['core/map', 'Give an address or coordinates'],
]);

test('while editing, an empty caption is drawn so it can be typed into', function () {
    expect(page(block('i', 'core/image', ['media' => 'photo']), true))
        ->toContain('<figcaption data-block-setting="caption"></figcaption>')
        ->and(page(block('i', 'core/image', ['media' => 'photo'])))->not->toContain('figcaption');
});

test('the shared style settings go on each block\'s outer element', function (
    string $type,
    array $settings,
    string $tag,
) {
    $styled = new Block(
        'x',
        \SpeedySpec\Block\Domain\ValueObjects\BlockName::fromString($type),
        1,
        $settings,
        ['core/style' => ['textAlign' => 'center']],
    );

    expect(page($styled))->toMatch('#^<' . $tag . ' class="b-[a-z0-9]+[^"]*"#');
})->with([
    ['core/image', ['media' => 'photo'], 'figure'],
    ['core/gallery', ['media' => ['photo']], 'figure'],
    ['core/video', ['media' => 'clip'], 'figure'],
    ['core/embed', ['url' => 'https://vimeo.com/1'], 'figure'],
    ['core/map', ['address' => 'x'], 'figure'],
    ['core/icon', ['name' => 'star'], 'span'],
    ['core/icon-list', [], 'ul'],
]);

test('an icon list item\'s text is marked for typing while editing', function () {
    $html = page(block('l', 'core/icon-list', [], block('a', 'core/icon-list-item', ['text' => 'Fast'])), true);

    expect($html)->toContain('<span data-block-setting="text">Fast</span>');
});

test('an icon the set does not have draws nothing for visitors and its name for editors', function () {
    expect(page(block('c', 'core/icon', ['name' => 'unicorn'])))->toBe('')
        ->and(page(block('c', 'core/icon', ['name' => 'unicorn']), true))->toContain('>unicorn</span>');
});

test('the icon set is original line art in the current colour', function () {
    expect(Icons::NAMES)->toHaveCount(16);

    foreach (Icons::NAMES as $name)
    {
        expect(Icons::svg($name))->toStartWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"')
            ->and(Icons::svg($name))->toContain('stroke="currentColor"')
            ->and(Icons::svg($name))->not->toContain('<script');
    }

    expect(Icons::svg('nope'))->toBeNull();
});

test('every frame is sandboxed and its address is the provider\'s, never the stored link', function () {
    $html = page(block('e', 'core/embed', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&autoplay=1']));

    expect($html)->toContain('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"')
        ->and($html)->not->toContain('autoplay=1"')
        ->and($html)->toContain(SANDBOX);
});
