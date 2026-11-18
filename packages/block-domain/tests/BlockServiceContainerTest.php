<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Exceptions\ServiceAlreadyResolvedException;
use SpeedySpec\Block\Domain\Exceptions\ServiceNotRegisteredException;

afterEach(function () {
    BlockServiceContainer::resetShared();
});

test('get resolves lazily, passes the container, and caches', function () {
    $c = new BlockServiceContainer();
    $calls = 0;
    $c->add('x', function (BlockServiceContainer $inner) use (&$calls, $c) {
        $calls++;
        expect($inner)->toBe($c);
        return new stdClass();
    });
    expect($calls)->toBe(0)->and($c->get('x'))->toBe($c->get('x'))->and($calls)->toBe(1);
});

test('a later add replaces an unresolved binding and drops its decorators', function () {
    $c = (new BlockServiceContainer())->add('x', fn () => new ArrayObject(['first']));
    $c->extend('x', fn (ArrayObject $inner) => new ArrayObject([$inner[0], 'decorated']));
    $c->add('x', fn () => new ArrayObject(['second']));
    expect($c->get('x')->getArrayCopy())->toBe(['second']);
});

test('decorators stack with the first added innermost', function () {
    $c = (new BlockServiceContainer())->add('x', fn () => new ArrayObject(['inner']));
    $c->extend('x', fn (ArrayObject $i) => new ArrayObject([...$i->getArrayCopy(), 'a']));
    $c->extend('x', fn (ArrayObject $i) => new ArrayObject([...$i->getArrayCopy(), 'b']));
    expect($c->get('x')->getArrayCopy())->toBe(['inner', 'a', 'b']);
});

test('add, remove and extend throw once the contract was resolved', function () {
    $c = (new BlockServiceContainer())->add('x', fn () => new stdClass());
    $c->get('x');
    expect(fn () => $c->add('x', fn () => new stdClass()))->toThrow(ServiceAlreadyResolvedException::class)
        ->and(fn () => $c->remove('x'))->toThrow(ServiceAlreadyResolvedException::class)
        ->and(fn () => $c->extend('x', fn ($i) => $i))->toThrow(ServiceAlreadyResolvedException::class);
});

test('get and extend throw for an unbound contract and has reports it', function () {
    $c = new BlockServiceContainer();
    expect($c->has('x'))->toBeFalse()
        ->and(fn () => $c->get('x'))->toThrow(ServiceNotRegisteredException::class)
        ->and(fn () => $c->extend('x', fn ($i) => $i))->toThrow(ServiceNotRegisteredException::class);
});

test('shared returns one instance until reset', function () {
    $a = BlockServiceContainer::shared();
    expect(BlockServiceContainer::shared())->toBe($a);
    BlockServiceContainer::resetShared();
    expect(BlockServiceContainer::shared())->not->toBe($a);
});
