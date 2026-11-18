<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;
use SpeedySpec\Block\Infra\Memory\InMemorySiteMap;
use SpeedySpec\Block\Site\SiteStylesheet;

function navItem(string $id, string $label, string $url, Block ...$children): Block
{
    return block($id, 'core/navigation-link', ['label' => $label, 'url' => $url], ...$children);
}

test('a menu nests its submenus and marks the current page', function () {
    $html = page([block('nav', 'core/navigation', ['label' => 'Main'],
        navItem('home', 'Home', '/'),
        navItem('blocks', 'Blocks', '', navItem('text', 'Text', '/text'), navItem('about', 'About', '/about/')),
    )]);

    expect($html)->toBe(
        '<nav class="b-nav b-nav--horizontal" aria-label="Main"><ul>'
        . '<li class="b-nav-item"><a href="/">Home</a></li>'
        . '<li class="b-nav-item b-nav-item--has-sub"><span class="b-nav-text">Blocks</span><ul class="b-nav-sub">'
        . '<li class="b-nav-item"><a href="/text">Text</a></li>'
        . '<li class="b-nav-item"><a href="/about/" aria-current="page">About</a></li>'
        . '</ul></li></ul></nav>',
    );
});

test('a menu link opening in a new tab says so to the browser', function () {
    expect(page([block('nav', 'core/navigation', [], block('x', 'core/navigation-link', [
        'label' => 'Docs',
        'url' => 'https://e.test/docs',
        'newTab' => true,
    ]))]))->toContain('<a href="https://e.test/docs" target="_blank" rel="noopener noreferrer">Docs</a>');
});

test('a menu link to an unsafe address is drawn as text, and one with no label or address is not set up', function () {
    $unsafe = page([block('nav', 'core/navigation', [], navItem('x', 'Click', 'javascript:alert(1)'))]);
    $empty = [block('nav', 'core/navigation', [], navItem('x', '', '/a'), navItem('y', 'Nowhere', ''))];

    expect($unsafe)->toContain('<li class="b-nav-item"><span class="b-nav-text">Click</span></li>')
        ->and($unsafe)->not->toContain('javascript')
        ->and(page($empty))->toBe('<nav class="b-nav b-nav--horizontal" aria-label="Main"><ul></ul></nav>')
        ->and(page($empty, true))->toContain('Add a link in the block&apos;s settings.');
});

test('a table of contents links to the ids the page\'s headings carry', function () {
    $html = page([
        block('h1', 'core/heading', ['level' => 2, 'text' => 'Intro']),
        block('h2', 'core/heading', ['level' => 2, 'text' => 'Intro']),
        block('h3', 'core/heading', ['level' => 3, 'text' => '<em>Fish</em> &amp; chips']),
        block('toc', 'core/table-of-contents'),
    ]);

    expect($html)->toContain('<h2 id="intro">Intro</h2><h2 id="intro-2">Intro</h2><h3 id="fish-chips">')
        ->and($html)->toContain(
            '<nav class="b-toc" aria-labelledby="b-toc-title"><h2 id="b-toc-title">On this page</h2><ol>'
            . '<li class="b-toc-l2"><a href="#intro">Intro</a></li>'
            . '<li class="b-toc-l2"><a href="#intro-2">Intro</a></li>'
            . '<li class="b-toc-l3"><a href="#fish-chips">Fish &amp; chips</a></li></ol></nav>',
        );
});

test('a table of contents with no headings to list is not set up', function () {
    expect(page([block('toc', 'core/table-of-contents')]))->toBe('')
        ->and(page([block('toc', 'core/table-of-contents')], true))
        ->toContain('Add headings to the page to list them here.');
});

test('breadcrumbs end in the current page, never a link, with labels as text', function () {
    expect(page([block('b', 'core/breadcrumbs')]))->toBe(
        '<nav class="b-crumbs" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li>'
        . '<li><a href="/co">&lt;b&gt;Co&lt;/b&gt;</a></li><li><span aria-current="page">About</span></li></ol></nav>',
    );
});

test('a crumb to an unsafe address is text, and breadcrumbs with no trail are not set up', function () {
    $map = new InMemorySiteMap([], ['d' => [
        new \SpeedySpec\Block\Domain\ValueObjects\Crumb('Bad', 'javascript:x'),
        new \SpeedySpec\Block\Domain\ValueObjects\Crumb('Here'),
    ]]);

    expect(page([block('b', 'core/breadcrumbs')], false, $map))->toContain('<li><span>Bad</span></li>')
        ->and(page([block('b', 'core/breadcrumbs')], false, null, false))->toBe('')
        ->and(page([block('b', 'core/breadcrumbs')], true, null, false))
        ->toContain('The site gives this page no trail.');
});

test('a search form sends the field under the host\'s name, with a label always', function () {
    expect(page([block('s', 'core/search', ['label' => '', 'placeholder' => 'Words'])]))->toBe(
        '<form class="b-search" role="search" method="get" action="/find"><label for="b-s-search">Search</label>'
        . '<input id="b-s-search" type="search" name="q" placeholder="Words"><button type="submit">Search</button>'
        . '</form>',
    );
});

test('a search with nowhere safe to send to is not set up', function (?SearchTarget $target) {
    $map = new InMemorySiteMap([], [], $target);

    expect(page([block('s', 'core/search')], false, $map))->toBe('')
        ->and(page([block('s', 'core/search')], true, $map))->toContain('This site has no search to send to.');
})->with([
    'no search' => [null],
    'a script address' => [new SearchTarget('javascript:x', 'q')],
    'markup for a field name' => [new SearchTarget('/find', '"><script>')],
]);

test('while editing, the text of each site block can be typed into', function () {
    $html = page([
        block('nav', 'core/navigation', [], navItem('x', 'Home', '/')),
        block('h', 'core/heading', ['text' => 'A']),
        block('toc', 'core/table-of-contents'),
        block('s', 'core/search'),
    ], true);

    expect($html)->toContain('data-block-setting="label" href="/">Home</a>')
        ->and($html)->toContain('<h2 id="b-toc-title" data-block-setting="title">On this page</h2>')
        ->and($html)->toContain('<button data-block-setting="button" type="submit">Search</button>');
});

test('the shared style settings go on each block\'s outer element', function (string $type, string $tag) {
    $styled = new Block('x', BlockName::fromString($type), 1, [], ['core/style' => ['textAlign' => 'center']]);
    $page = $type === 'core/table-of-contents' ? [block('h', 'core/heading', ['text' => 'A']), $styled] : [$styled];

    expect(page($page))->toMatch('#<' . $tag . ' class="[^"]*b-[a-z0-9]{10}[^"]*"#');
})->with([
    ['core/navigation', 'nav'],
    ['core/table-of-contents', 'nav'],
    ['core/breadcrumbs', 'nav'],
    ['core/search', 'form'],
]);

test('the stylesheet opens submenus on focus and loads nothing else', function () {
    expect(SiteStylesheet::CSS)->toContain(':focus-within')
        ->and(SiteStylesheet::CSS)->not->toContain('@import')
        ->and(SiteStylesheet::CSS)->not->toContain('url(');
});

test('a closed submenu is moved out of view, not removed, so its links can still take keyboard focus', function () {
    preg_match('/\.b-nav-sub \{([^}]*)\}/', SiteStylesheet::CSS, $rule);

    expect($rule[1] ?? '')->not->toContain('display: none')
        ->and($rule[1] ?? '')->toContain('left: -9999px')
        ->and(SiteStylesheet::CSS)->toMatch('/\.b-nav-item:focus-within > \.b-nav-sub \{[^}]*left: 0/');
});
