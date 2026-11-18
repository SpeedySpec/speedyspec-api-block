<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;

function heading(string $id, string $text, string $anchor = '', int $level = 2): Block
{
    return block($id, 'core/heading', ['level' => $level, 'text' => $text, 'anchor' => $anchor]);
}

function tocOf(Block ...$roots): array
{
    return viewOf('toc', [...$roots, block('toc', 'core/table-of-contents', ['from' => 1, 'to' => 6])])['items'] ?? [];
}

test('every heading on the page gets a unique id, a typed anchor keeping its own', function () {
    expect(tocOf(
        heading('a', 'Intro'),
        heading('b', 'Intro'),
        heading('c', 'Other', 'intro-2'),
        heading('d', ''),
        heading('e', '<em>Fish</em> &amp; chips', '', 3),
        heading('f', 'Καλημέρα'),
        heading('g', 'Καλησπέρα'),
    ))->toBe([
        ['level' => 2, 'text' => 'Intro', 'id' => 'intro'],
        ['level' => 2, 'text' => 'Intro', 'id' => 'intro-3'],
        ['level' => 2, 'text' => 'Other', 'id' => 'intro-2'],
        ['level' => 3, 'text' => 'Fish & chips', 'id' => 'fish-chips'],
        ['level' => 2, 'text' => 'Καλημέρα', 'id' => 'heading-2'],
        ['level' => 2, 'text' => 'Καλησπέρα', 'id' => 'heading-3'],
    ]);
});

test('the headings are told their ids, so the page and its contents agree', function () {
    $resolved = resolve([heading('a', 'Intro'), heading('b', 'Intro'), block('toc', 'core/table-of-contents')]);

    expect($resolved->find('a')?->view)->not->toBeNull();
});

test('a heading inside a repeat is left out, since it appears once per item', function () {
    $name = \SpeedySpec\Block\Domain\ValueObjects\BlockName::fromString('core/repeat');
    $repeat = new Block('r', $name, 1, [], [], [], [
        heading('in', 'Repeated'),
    ]);

    expect(array_column(tocOf(heading('a', 'Kept'), $repeat), 'text'))->toBe(['Kept']);
});

test('the page step gives the page\'s address, trail and search from the site map', function () {
    $view = viewOf('s', [block('s', 'core/search'), block('b', 'core/breadcrumbs')]);
    $crumbs = viewOf('b', [block('b', 'core/breadcrumbs')])['crumbs'];

    expect($view)->toMatchArray(['url' => '/find', 'parameter' => 'q'])
        ->and($crumbs)->toBe([
            ['label' => 'Home', 'url' => '/', 'current' => false],
            ['label' => 'Company', 'url' => '/company', 'current' => false],
            ['label' => 'About', 'url' => '', 'current' => true],
        ]);
});

function hiddenHeading(string $id, string $text): Block
{
    return new Block(
        $id,
        \SpeedySpec\Block\Domain\ValueObjects\BlockName::fromString('core/heading'),
        1,
        ['level' => 2, 'text' => $text, 'anchor' => ''],
        ['core/visibility' => ['groups' => [[]]]],
    );
}

test('a heading that visibility rules may hide is never listed, nor one inside a block they may hide', function () {
    $section = new Block(
        's',
        \SpeedySpec\Block\Domain\ValueObjects\BlockName::fromString('core/section'),
        1,
        [],
        ['core/visibility' => ['groups' => [[['condition' => 'acme/member']]]]],
        [],
        [heading('in', 'Inside a hidden section')],
    );

    expect(array_column(tocOf(heading('a', 'Shown'), hiddenHeading('h', 'Members secret'), $section), 'text'))
        ->toBe(['Shown']);
});

test('a heading whose stored settings could not be drawn is not listed and claims no id', function () {
    expect(tocOf(heading('bad', 'Broken', ' intro '), heading('ok', 'Intro')))
        ->toBe([['level' => 2, 'text' => 'Intro', 'id' => 'intro']])
        ->and(array_column(tocOf(block('lvl', 'core/heading', ['level' => 9, 'text' => 'Nine'])), 'text'))->toBe([]);
});

test('a heading whose text comes from a binding is not listed and makes its own id', function () {
    $bound = new Block(
        'b',
        \SpeedySpec\Block\Domain\ValueObjects\BlockName::fromString('core/heading'),
        1,
        ['level' => 2, 'text' => ''],
        [],
        ['text' => \SpeedySpec\Block\Domain\ValueObjects\Binding::to('acme/title')->withFallback('Fallback')],
    );
    $step = new \SpeedySpec\Block\Site\Domain\SitePageStep();
    $context = $step->context(
        new \SpeedySpec\Block\Domain\Entities\Document('d', 'page', new \SpeedySpec\Block\Domain\Entities\BlockTree(
            $bound,
            heading('e', ''),
        )),
        \SpeedySpec\Block\Domain\ValueObjects\Context::empty(),
        \SpeedySpec\Block\Domain\ValueObjects\Mode::Front,
    );

    expect($context->get('core/headings'))->toBe([])
        ->and($context->get('core/heading-ids'))->toBe(['e' => 'heading']);
});

test('an included document keeps the page\'s address, trail and contents, and avoids its ids', function () {
    $step = new \SpeedySpec\Block\Site\Domain\SitePageStep(siteMap());
    $doc = fn (string $id, Block ...$roots) => new \SpeedySpec\Block\Domain\Entities\Document(
        $id,
        'page',
        new \SpeedySpec\Block\Domain\Entities\BlockTree(...$roots),
    );
    $front = \SpeedySpec\Block\Domain\ValueObjects\Mode::Front;
    $empty = \SpeedySpec\Block\Domain\ValueObjects\Context::empty();
    $page = $step->context($doc('d', heading('p', 'Intro')), $empty, $front);
    $header = $step->context($doc('hdr', heading('h', 'Intro')), $page, $front);

    expect($header->get('core/page-url'))->toBe('https://e.test/about')
        ->and(count($header->get('core/breadcrumbs')))->toBe(3)
        ->and($header->get('core/search'))->toBe(['url' => '/find', 'parameter' => 'q'])
        ->and($header->get('core/headings'))->toBe([['level' => 2, 'text' => 'Intro', 'id' => 'intro']])
        ->and($header->get('core/heading-ids'))->toBe(['p' => 'intro', 'h' => 'intro-2']);
});
