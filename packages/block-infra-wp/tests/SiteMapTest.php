<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Infra\WP\Ports\WpSiteInterface;
use SpeedySpec\Block\Infra\WP\WpServiceProvider;
use SpeedySpec\Block\Infra\WP\WpSiteMap;

/**
 * Describes a small site the way WordPress would: a page with a parent and a grandparent.
 */
class FakeSite implements WpSiteInterface
{
    public array $titles = [3 => 'Team', 2 => 'About', 1 => 'Company'];

    public array $parents = [3 => [1, 2], 2 => [1], 1 => []];

    public function permalink(int $id): ?string
    {
        return isset($this->titles[$id]) ? "https://wp.test/{$id}/" : null;
    }

    public function title(int $id): string
    {
        return $this->titles[$id] ?? '';
    }

    public function ancestors(int $id): array
    {
        return $this->parents[$id] ?? [];
    }

    public function homeUrl(): string
    {
        return 'https://wp.test/';
    }

    public function siteName(): string
    {
        return 'Example';
    }
}

test('a page\'s trail runs from the site through its ancestors to itself', function () {
    $map = new WpSiteMap(new FakeSite());

    expect($map->trail('3'))->toEqual([
        new Crumb('Example', 'https://wp.test/'),
        new Crumb('Company', 'https://wp.test/1/'),
        new Crumb('About', 'https://wp.test/2/'),
        new Crumb('Team'),
    ])->and($map->url('3'))->toBe('https://wp.test/3/');
});

test('an id that is not a post has no address and no trail', function (string $id) {
    $map = new WpSiteMap(new FakeSite());

    expect($map->url($id))->toBeNull()->and($map->trail($id))->toBe([]);
})->with(['abc', '0', '-3', '99']);

test('WordPress search goes to the home page as `s`', function () {
    $search = (new WpSiteMap(new FakeSite()))->search();

    expect($search?->url)->toBe('https://wp.test/')->and($search?->parameter)->toBe('s');
});

test('the provider binds the WordPress site map', function () {
    $container = new BlockServiceContainer();
    (new WpServiceProvider(site: new FakeSite()))->register($container);

    expect($container->get(SiteMapInterface::class))->toBeInstanceOf(WpSiteMap::class);
});
