<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\HeadingAnchor;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;

test('a heading\'s anchor is made from its words', function (string $text, string $id) {
    expect(HeadingAnchor::fromText($text))->toBe($id);
})->with([
    ['Getting started', 'getting-started'],
    ['<em>Fish</em> &amp; chips', 'fish-chips'],
    ['2026 plans', 'h-2026-plans'],
    ['', 'heading'],
    ['Καλημέρα', 'heading'],
    [str_repeat('a', 70), str_repeat('a', 64)],
]);

test('repeated anchors are numbered, skipping any already wanted', function () {
    expect(HeadingAnchor::unique(['intro', 'intro', 'a', 'intro-2', 'intro']))
        ->toBe(['intro', 'intro-3', 'a', 'intro-2', 'intro-4'])
        ->and(HeadingAnchor::unique(['heading', 'heading', 'heading']))->toBe(['heading', 'heading-2', 'heading-3'])
        ->and(HeadingAnchor::unique([]))->toBe([]);
});

test('a crumb and a search target carry what they were given', function () {
    $crumb = new Crumb('Home', '/');
    $here = new Crumb('Here');
    $search = new SearchTarget('/search', 'q');

    expect($crumb->label)->toBe('Home')
        ->and($crumb->url)->toBe('/')
        ->and($here->url)->toBe('')
        ->and($search->url)->toBe('/search')
        ->and($search->parameter)->toBe('q');
});

test('anchors already used elsewhere on the page are skipped too', function () {
    expect(HeadingAnchor::unique(['intro', 'menu'], ['intro', 'intro-2']))->toBe(['intro-3', 'menu']);
});
