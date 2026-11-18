<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;
use SpeedySpec\Block\Infra\Memory\ArrayCollectionSource;
use SpeedySpec\Block\Infra\Memory\InMemorySiteMap;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;

test('the memory site map gives what it was given, and nothing for pages it does not know', function () {
    $trail = [new Crumb('Home', '/'), new Crumb('About')];
    $search = new SearchTarget('/search', 'q');
    $map = new InMemorySiteMap(['about' => '/about'], ['about' => $trail], $search);

    expect($map->url('about'))->toBe('/about')
        ->and($map->url('other'))->toBeNull()
        ->and($map->trail('about'))->toBe($trail)
        ->and($map->trail('other'))->toBe([])
        ->and($map->search())->toBe($search)
        ->and((new InMemorySiteMap())->search())->toBeNull();
});

test('the memory provider binds no site map, so a host without one has none', function () {
    $container = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($container);

    expect($container->has(SiteMapInterface::class))->toBeFalse();
});

test('an array collection gives its items as they are', function () {
    $items = [['title' => 'One'], ['title' => 'Two']];
    $source = new ArrayCollectionSource('acme/posts', $items);

    expect($source->name())->toBe('acme/posts')
        ->and($source->argumentsSchema())->toBe(['type' => 'object', 'properties' => []])
        ->and($source->items([], Context::empty(), Mode::Front))->toBe($items);
});
