<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Presentation\BreakpointRegistry;
use SpeedySpec\Block\App\Presentation\PresentationPackage;
use SpeedySpec\Block\App\Storage\StorageServiceProvider;
use SpeedySpec\Block\App\Storage\StoredBreakpointRegistry;
use SpeedySpec\Block\App\Storage\StoredDesignTokenRegistry;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\Exceptions\StorageException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Infra\Memory\InMemoryRecordStore;

$widths = static fn (BreakpointRegistryInterface $registry): array => array_map(
    static fn (Breakpoint $breakpoint): array => [$breakpoint->name, $breakpoint->minWidth],
    $registry->all(),
);

test('a stored token registry reads its defaults until it is first changed, then only the store', function () {
    $store = new InMemoryRecordStore();
    $tokens = new StoredDesignTokenRegistry($store, ['space.md' => '1rem', 'color.primary' => '#000']);

    expect($tokens->all())->toBe(['color.primary' => '#000', 'space.md' => '1rem'])
        ->and($tokens->get('space.md'))->toBe('1rem')
        ->and($store->keys('design-tokens'))->toBe([]);

    $tokens->set('color.primary', '#fff');
    $again = new StoredDesignTokenRegistry($store, ['space.md' => '9rem', 'other' => 'x']);

    expect($again->all())->toBe(['color.primary' => '#fff', 'space.md' => '1rem'])
        ->and($store->get('style-registries', 'design-tokens'))->toBe('1');

    $again->remove('space.md');
    $again->remove('color.primary');

    expect((new StoredDesignTokenRegistry($store, ['space.md' => '1rem']))->all())->toBe([]);
});

test('a stored breakpoint registry starts from the usual breakpoints and keeps what is changed', function () use (
    $widths,
) {
    $store = new InMemoryRecordStore();
    $breakpoints = new StoredBreakpointRegistry($store);

    expect($widths($breakpoints))->toBe([['tablet', 768], ['desktop', 1024]])
        ->and($widths(new BreakpointRegistry(...BreakpointRegistry::defaults())))
        ->toBe([['tablet', 768], ['desktop', 1024]]);

    $breakpoints->add(new Breakpoint('wide', 1280));
    $breakpoints->add(new Breakpoint('phone', 320));

    expect($widths(new StoredBreakpointRegistry($store)))
        ->toBe([['phone', 320], ['tablet', 768], ['desktop', 1024], ['wide', 1280]])
        ->and((new StoredBreakpointRegistry($store))->get('wide')?->minWidth)->toBe(1280)
        ->and($store->get('breakpoints', 'wide'))->toBe('1280');
});

test('removing every breakpoint stays removed, rather than bringing the defaults back', function () use ($widths) {
    $store = new InMemoryRecordStore();
    $breakpoints = new StoredBreakpointRegistry($store, [new Breakpoint('tablet', 768)]);
    $breakpoints->remove('tablet');

    expect($widths(new StoredBreakpointRegistry($store, [new Breakpoint('tablet', 768)])))->toBe([]);
});

test('a bad token name or value is refused before anything is written', function (string $name, string $value) {
    $store = new InMemoryRecordStore();
    $tokens = new StoredDesignTokenRegistry($store, ['color.primary' => '#000']);

    expect(fn () => $tokens->set($name, $value))->toThrow(InvalidStyleException::class)
        ->and($store->keys('design-tokens'))->toBe([])
        ->and($store->get('style-registries', 'design-tokens'))->toBeNull();
})->with([
    ['Bad', 'red'],
    ['color.primary', 'red;} body{display:none'],
]);

test('bad defaults are refused when the registry is made', function () {
    expect(fn () => new StoredDesignTokenRegistry(new InMemoryRecordStore(), ['Bad' => 'red']))
        ->toThrow(InvalidStyleException::class)
        ->and(fn () => new StoredBreakpointRegistry(new InMemoryRecordStore(), ['tablet']))
        ->toThrow(InvalidArgumentException::class);
});

test('a stored width that is not a whole number of pixels is a storage problem naming the breakpoint', function (
    string $stored,
) {
    $store = new InMemoryRecordStore();
    (new StoredBreakpointRegistry($store))->add(new Breakpoint('wide', 1280));
    $store->put('breakpoints', 'wide', $stored);

    expect(fn () => (new StoredBreakpointRegistry($store))->all())
        ->toThrow(StorageException::class, 'wide');
})->with(['0', 'wide', '12.5', '-3', '']);

test('the storage provider binds both registries over the record store, with the defaults it is given', function () {
    $store = new InMemoryRecordStore();
    $c = new BlockServiceContainer();
    $c->add(RecordStoreInterface::class, fn () => $store);
    (new StorageServiceProvider(['color.primary' => '#123456'], [new Breakpoint('wide', 1280)]))->register($c);

    expect($c->get(DesignTokenRegistryInterface::class))->toBeInstanceOf(StoredDesignTokenRegistry::class)
        ->and($c->get(DesignTokenRegistryInterface::class)->all())->toBe(['color.primary' => '#123456'])
        ->and($c->get(BreakpointRegistryInterface::class))->toBeInstanceOf(StoredBreakpointRegistry::class)
        ->and($c->get(BreakpointRegistryInterface::class)->get('wide')?->minWidth)->toBe(1280);
});

test('styles that use a removed breakpoint or token still draw, leaving out what no longer applies', function () {
    $store = new InMemoryRecordStore();
    [$renderer, $c] = renderer(function (BlockServiceContainer $c) use ($store) {
        $c->add(RecordStoreInterface::class, fn () => $store);
        (new StorageServiceProvider(['color.primary' => '#0055ff']))->register($c);
        $c->get(BlockPackageLoaderInterface::class)->load(PresentationPackage::from($c));
        types($c)->add(type('acme/box', ['children' => true]));
        outputs($c)->set(BlockName::fromString('acme/box'), tag('div'));
    });
    $c->get(BreakpointRegistryInterface::class)->remove('tablet');
    $c->get(DesignTokenRegistryInterface::class)->remove('color.primary');
    $block = block('a', 'acme/box')->withExtension('core/style', [
        'padding' => ['base' => '1rem', 'tablet' => '2rem'],
        'color' => 'token:color.primary',
    ]);

    $output = $renderer->render(doc($block), new RenderRequest())->output;
    $css = $c->get(StyleCollectorInterface::class)->css();

    expect($output)->toContain('class="b-')
        ->and($css)->not->toContain('768px')
        ->and($css)->toContain('color:var(--color-primary)')
        ->and($css)->toContain('padding:1rem');
});
