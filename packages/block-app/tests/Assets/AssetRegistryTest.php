<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Assets\AssetRegistry;
use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Exceptions\BlockPackageException;
use SpeedySpec\Block\Domain\Exceptions\InvalidAssetException;
use SpeedySpec\Block\Domain\ValueObjects\Asset;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;

$script = fn (string $name, string ...$needs) => Asset::inline($name, AssetKind::Script, $name, $needs);
$names = fn (array $assets) => array_map(fn (Asset $asset) => $asset->name, $assets);

test('resolving gives dependencies first, each once, in a stable order', function () use ($script, $names) {
    $registry = new AssetRegistry();
    $registry->add($script('acme/c'));
    $registry->add($script('acme/a', 'acme/c'));
    $registry->add($script('acme/b', 'acme/c', 'acme/a'));

    expect($names($registry->resolve(['acme/b', 'acme/a'])))->toBe(['acme/c', 'acme/a', 'acme/b'])
        ->and($names($registry->resolve(['acme/a', 'acme/a', 'acme/c'])))->toBe(['acme/c', 'acme/a'])
        ->and($registry->resolve([]))->toBe([]);
});

test('a missing or circular dependency is named', function () use ($script) {
    $registry = new AssetRegistry();
    $registry->add($script('acme/loop', 'acme/back'));
    $registry->add($script('acme/back', 'acme/loop'));
    $registry->add($script('acme/needs-gone', 'acme/gone'));

    expect(fn () => $registry->resolve(['acme/loop']))->toThrow(InvalidAssetException::class, 'acme/loop')
        ->and(fn () => $registry->resolve(['acme/needs-gone']))->toThrow(InvalidAssetException::class, 'acme/gone')
        ->and(fn () => $registry->resolve(['acme/nowhere']))->toThrow(InvalidAssetException::class, 'acme/nowhere');
});

test('an asset can be found, replaced and removed by name', function () use ($script) {
    $registry = new AssetRegistry();
    $registry->add($script('acme/a'));
    $registry->add(Asset::inline('acme/a', AssetKind::Style, 'new'));

    expect($registry->get('acme/a')->content)->toBe('new')->and($registry->get('acme/none'))->toBeNull();

    $registry->remove('acme/a');

    expect($registry->get('acme/a'))->toBeNull();
});

test('a package registers its assets with the rest of its load, under its own vendor', function () use ($script) {
    [, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);
    $registry = $c->get(AssetRegistryInterface::class);
    $loader->load(package('acme/blocks', function ($r) use ($script) {
        $r->type(type('acme/tabs'));
        $r->asset($script('acme/tabs'));
    }));

    expect($registry->get('acme/tabs'))->not->toBeNull()
        ->and(fn () => $loader->load(package('acme/more', fn ($r) => $r->asset($script('other/tabs')))))
        ->toThrow(BlockPackageException::class)
        ->and(fn () => $loader->load(package('acme/again', fn ($r) => $r->asset($script('acme/tabs')))))
        ->toThrow(BlockPackageException::class);

    expect(fn () => $loader->load(package('acme/broken', function ($r) use ($script) {
        $r->asset($script('acme/leftover'));
        $r->type(type('acme/tabs'));
    })))->toThrow(BlockPackageException::class)
        ->and($registry->get('acme/leftover'))->toBeNull();
});
