<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Interactive\InteractiveStylesheet;

const COUNT_FIVE = '<p class="b-counter" data-b-counter="" data-from="0" data-to="5" data-duration="2000"'
    . ' data-decimals="0"'
    . ' data-prefix="" data-suffix=""><span class="b-counter-number" aria-hidden="true">5</span>'
    . '<span class="b-visually-hidden">5</span></p>';

test('each interactive block draws markup that works without a script', function (Block $block, string $html) {
    expect(page($block))->toBe($html);
})->with([
    'accordion' => [
        block('a', 'core/accordion', [], block(
            'i',
            'core/accordion-item',
            ['title' => 'Why?', 'open' => true],
            para('p'),
        )),
        '<div class="b-accordion"><details class="b-accordion-item" open=""><summary>Why?</summary>'
        . '<div class="b-accordion-panel">' . COUNT_FIVE . '</div></details></div>',
    ],
    'accordion, one open at a time' => [
        block('a', 'core/accordion', ['single' => true], block('i', 'core/accordion-item', ['title' => 'Q'])),
        '<div class="b-accordion"><details class="b-accordion-item" name="b-a-group"><summary>Q</summary>'
        . '<div class="b-accordion-panel"></div></details></div>',
    ],
    'tabs' => [
        block('t', 'core/tabs', [], block('one', 'core/tab', ['label' => 'One & two'], para('p'))),
        '<div id="b-t-x" class="b-tabs" data-b-tabs=""><section id="b-one-x" class="b-tab" data-b-tab="">'
        . '<h3 class="b-tab-label">One &amp; two</h3><div class="b-tab-panel">' . COUNT_FIVE . '</div></section></div>',
    ],
    'slider' => [
        block('s', 'core/slider', ['label' => 'Work', 'autoplay' => true, 'interval' => 8], block('a', 'core/slide')),
        '<section id="b-s-x" class="b-slider" aria-roledescription="carousel" aria-label="Work" data-b-slider=""'
        . ' data-autoplay="1" data-interval="8"><div class="b-slides"><div class="b-slide" role="group"'
        . ' aria-roledescription="slide"></div></div></section>',
    ],
    'countdown' => [
        block('c', 'core/countdown', ['target' => '2026-12-31T23:59', 'timeZone' => 'Europe/London']),
        '<div class="b-countdown" data-b-countdown="" data-target="2026-12-31T23:59:00Z"><p class="b-countdown-label">'
        . 'Starts in</p><p class="b-countdown-time"><time datetime="2026-12-31T23:59:00Z">31 December 2026, 23:59'
        . ' (Europe/London)</time></p><div class="b-countdown-after" hidden="">It has started.</div></div>',
    ],
    'countdown already passed' => [
        block('c', 'core/countdown', ['target' => '2026-01-01T00:00', 'after' => 'We <em>opened</em>.']),
        '<div class="b-countdown" data-b-countdown="" data-target="2026-01-01T00:00:00Z"><p class="b-countdown-label">'
        . 'Starts in</p><div class="b-countdown-after">We <em>opened</em>.</div></div>',
    ],
    'progress' => [
        block('g', 'core/progress', ['label' => 'Funded', 'value' => 65]),
        '<div class="b-progress"><span id="b-g-label" class="b-progress-label">Funded</span>'
        . '<progress max="100" value="65" aria-labelledby="b-g-label">65%</progress>'
        . '<span class="b-progress-value">65%</span></div>',
    ],
    'counter' => [
        block('n', 'core/counter', ['from' => 10, 'to' => 1200, 'prefix' => '<', 'suffix' => '+']),
        '<p class="b-counter" data-b-counter="" data-from="10" data-to="1200" data-duration="2000" data-decimals="0"'
        . ' data-prefix="&lt;" data-suffix="+"><span class="b-counter-number" aria-hidden="true">&lt;1,200+</span>'
        . '<span class="b-visually-hidden">&lt;1,200+</span></p>',
    ],
    'modal' => [
        block('m', 'core/modal', ['trigger' => 'Read more', 'title' => 'About'], para('p')),
        '<div class="b-modal"><button class="b-modal-open" type="button" popovertarget="b-m-x">Read more</button>'
        . '<div id="b-m-x" class="b-modal-panel" popover="" role="dialog" aria-labelledby="b-m-title">'
        . '<h2 id="b-m-title">About</h2>' . COUNT_FIVE . '<button class="b-modal-close" type="button"'
        . ' popovertarget="b-m-x" popovertargetaction="hide">Close</button></div></div>',
    ],
]);

test('while editing, everything that opens is open and in place, so it can be typed into', function () {
    $accordion = page(block('a', 'core/accordion', ['single' => true], block('i', 'core/accordion-item')), true);
    $modal = page(block('m', 'core/modal'), true);

    expect($accordion)->toContain('<details class="b-accordion-item" open="">')
        ->and($accordion)->toContain('<summary data-block-setting="title">Question</summary>')
        ->and($accordion)->not->toContain('name=')
        ->and($modal)->not->toContain(' popover=')
        ->and($modal)->toContain('<h2 id="b-m-title" data-block-setting="title">Details</h2>')
        ->and(page(block('t', 'core/tabs', [], block('x', 'core/tab')), true))
        ->toContain('<h3 class="b-tab-label" data-block-setting="label">Tab</h3>');
});

test('a countdown without a date is not set up', function () {
    expect(page(block('c', 'core/countdown')))->toBe('')
        ->and(page(block('c', 'core/countdown'), true))
        ->toContain('Choose a date and time in the block&apos;s settings.');
});

test('two sets of tabs, and tabs inside a tab, never share an id', function () {
    $html = rendered([
        block('t1', 'core/tabs', [], block('a', 'core/tab', [], block('t2', 'core/tabs', [], block('b', 'core/tab')))),
        block('t3', 'core/tabs', [], block('c', 'core/tab')),
    ])->output;
    preg_match_all('/ id="([^"]+)"/', $html, $ids);

    expect($ids[1])->toBe(array_values(array_unique($ids[1])))
        ->and($ids[1])->toContain('b-t1-x')
        ->and($ids[1])->toContain('b-t2-x')
        ->and($ids[1])->toContain('b-t3-x');
});

test('a slide with a name is announced by it', function () {
    expect(page(block('s', 'core/slider', [], block('a', 'core/slide', ['label' => 'Before']))))
        ->toContain('<div class="b-slide" role="group" aria-roledescription="slide" aria-label="Before">');
});

test('the shared style settings go on each block\'s outer element', function (string $type, string $tag) {
    $styled = new Block('x', BlockName::fromString($type), 1, ['target' => '2027-01-01T00:00'], [
        'core/style' => ['textAlign' => 'center'],
    ]);

    expect(page($styled))->toMatch('#^<' . $tag . '[^>]* class="[^"]*b-[a-z0-9]{10}[^"]*"#');
})->with([
    ['core/accordion', 'div'],
    ['core/tabs', 'div'],
    ['core/slider', 'section'],
    ['core/countdown', 'div'],
    ['core/progress', 'div'],
    ['core/counter', 'p'],
    ['core/modal', 'div'],
]);

test('every block brings the stylesheet and the script, each listed once per page', function () {
    $assets = rendered([block('a', 'core/progress'), block('b', 'core/counter'), block('c', 'core/tabs')])->assets;

    expect(array_count_values($assets))->toBe(['core/interactive' => 1, 'core/interactive-script' => 1]);
});

test('the stylesheet loads nothing else and slides snap without a script', function () {
    expect(InteractiveStylesheet::CSS)->not->toContain('@import')
        ->and(InteractiveStylesheet::CSS)->not->toContain('url(')
        ->and(InteractiveStylesheet::CSS)->toContain('scroll-snap-type: x mandatory')
        ->and(InteractiveStylesheet::CSS)->toContain('prefers-reduced-motion')
        ->and(InteractiveStylesheet::CSS)->toContain('.b-visually-hidden');
});

test('a slider or tab whose label was cleared still has a name for visitors', function () {
    $slider = page(block('s', 'core/slider', ['label' => '  '], block('a', 'core/slide')));
    $tab = page(block('t', 'core/tabs', [], block('a', 'core/tab', ['label' => ''])));

    expect($slider)->toContain('aria-label="Slides"')
        ->and($tab)->toContain('<h3 class="b-tab-label">Tab</h3>')
        ->and(page(block('t', 'core/tabs', [], block('a', 'core/tab', ['label' => ''])), true))
        ->toContain('<h3 class="b-tab-label" data-block-setting="label"></h3>');
});
