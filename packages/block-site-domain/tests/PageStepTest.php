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
        ['level' => 2, 'text' => '', 'id' => 'heading'],
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
