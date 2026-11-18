<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;
use SpeedySpec\Block\Infra\Memory\InMemorySiteMap;

function navLink(string $id, string $url, Block ...$children): Block
{
    return block($id, 'core/navigation-link', ['label' => $id, 'url' => $url], ...$children);
}

function menu(Block ...$links): Block
{
    return block('nav', 'core/navigation', [], ...$links);
}

test('the link to the page being drawn is current, whatever its slash, query or fragment', function (
    string $url,
    bool $current,
) {
    expect(viewOf('l', [menu(navLink('l', $url))])['current'])->toBe($current);
})->with([
    'the same address' => ['https://e.test/about', true],
    'a path on the same site' => ['/about', true],
    'a trailing slash' => ['/about/', true],
    'a query' => ['/about?x=1', true],
    'a part of the page' => ['/about#team', false],
    'another site with the same path' => ['https://other.test/about', false],
    'another page' => ['/aboutus', false],
    'the home page' => ['/', false],
    'no address' => ['', false],
]);

test('with no site map nothing is current', function () {
    expect(viewOf('l', [menu(navLink('l', '/about'))], false)['current'])->toBeFalse();
});

test('links count how deep they are, and a third submenu level is refused', function () {
    $resolved = resolve([menu(navLink('a', '/a', navLink('b', '/b', navLink('c', '/c', navLink('d', '/d')))))]);

    expect($resolved->find('a')?->view?->values['depth'])->toBe(1)
        ->and($resolved->find('c')?->view?->values['depth'])->toBe(3)
        ->and($resolved->find('d')?->view)->toBeNull()
        ->and(issuesOf('d', $resolved))
        ->toBe([[IssueKind::DisallowedChild, 'Menus go two levels deep at most.', null]]);
});

test('a table of contents lists only its levels, either way round', function () {
    $page = [
        block('h1', 'core/heading', ['level' => 1, 'text' => 'One']),
        block('h2', 'core/heading', ['level' => 2, 'text' => 'Two']),
        block('h4', 'core/heading', ['level' => 4, 'text' => 'Four']),
    ];

    expect(array_column(viewOf('t', [...$page, block('t', 'core/table-of-contents')])['items'], 'text'))->toBe(['Two'])
        ->and(array_column(
            viewOf('t', [...$page, block('t', 'core/table-of-contents', ['from' => 4, 'to' => 1])])['items'],
            'text',
        ))
        ->toBe(['One', 'Two', 'Four'])
        ->and(viewOf('t', [...$page, block('t', 'core/table-of-contents')])['title'])->toBe('On this page');
});

test('a long trail keeps its first crumb and its last nineteen, the last always current', function () {
    $trail = array_map(fn (int $n): Crumb => new Crumb("C{$n}", "/c{$n}"), range(1, 40));
    $map = new InMemorySiteMap(['d' => '/d'], ['d' => $trail]);
    $crumbs = siteContainer($map)->get(\SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface::class)
        ->resolve(
            new \SpeedySpec\Block\Domain\Entities\Document('d', 'page', new \SpeedySpec\Block\Domain\Entities\BlockTree(
                block('b', 'core/breadcrumbs'),
            )),
            \SpeedySpec\Block\Domain\ValueObjects\Mode::Front,
        )->find('b')?->view?->values['crumbs'];

    expect($crumbs)->toHaveCount(20)
        ->and($crumbs[0]['label'])->toBe('C1')
        ->and($crumbs[1]['label'])->toBe('C22')
        ->and($crumbs[19])->toBe(['label' => 'C40', 'url' => '', 'current' => true]);
});

test('a search is set up only with a target and a parameter that can be a field name', function (
    ?SearchTarget $target,
    string $url,
) {
    $map = new InMemorySiteMap([], [], $target);
    $view = siteContainer($map)->get(\SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface::class)
        ->resolve(
            new \SpeedySpec\Block\Domain\Entities\Document('d', 'page', new \SpeedySpec\Block\Domain\Entities\BlockTree(
                block('s', 'core/search', ['label' => '']),
            )),
            \SpeedySpec\Block\Domain\ValueObjects\Mode::Front,
        )->find('s')?->view?->values;

    expect($view['url'])->toBe($url)->and($view['label'])->toBe('Search')->and($view['id'])->toBe('b-s-search');
})->with([
    'no search' => [null, ''],
    'a field name' => [new SearchTarget('/find', 'q'), '/find'],
    'markup for a field name' => [new SearchTarget('/find', '"><script>'), ''],
    'a field name starting with a digit' => [new SearchTarget('/find', '1q'), ''],
]);

test('two searches on one page have their own ids', function () {
    $resolved = resolve([block('one', 'core/search'), block('two', 'core/search')]);

    expect($resolved->find('one')?->view?->values['id'])->toBe('b-one-search')
        ->and($resolved->find('two')?->view?->values['id'])->toBe('b-two-search');
});

test('with no site map, search and breadcrumbs are not set up and raise no issue', function () {
    $resolved = resolve([block('s', 'core/search'), block('b', 'core/breadcrumbs')], false);

    expect($resolved->find('s')?->view?->values['url'])->toBe('')
        ->and($resolved->find('b')?->view?->values['crumbs'])->toBe([])
        ->and($resolved->issues)->toBe([]);
});

test('under addresses that name pages by their query, only the link with the same query is current', function () {
    $map = new InMemorySiteMap(['d' => 'https://e.test/?page_id=5']);
    $current = fn (string $url): bool => siteContainer($map)
        ->get(\SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface::class)
        ->resolve(
            new \SpeedySpec\Block\Domain\Entities\Document('d', 'page', new \SpeedySpec\Block\Domain\Entities\BlockTree(
                menu(navLink('l', $url)),
            )),
            \SpeedySpec\Block\Domain\ValueObjects\Mode::Front,
        )->find('l')?->view?->values['current'];

    expect($current('https://e.test/?page_id=5'))->toBeTrue()
        ->and($current('/?page_id=5'))->toBeTrue()
        ->and($current('https://e.test/?page_id=6'))->toBeFalse()
        ->and($current('https://e.test/'))->toBeFalse();
});
