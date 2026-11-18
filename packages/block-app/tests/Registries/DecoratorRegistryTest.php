<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\DecoratorRegistry;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;

test('decorators apply to all types or to a named list', function () {
    $registry = new DecoratorRegistry(new SubsetSchemaValidator());
    $registry->add(new Decorator('acme/visibility', ['type' => 'object']));
    $registry->add(new Decorator('acme/anim', ['type' => 'object'], ['acme/box']));
    $names = fn (string $type) => array_map(fn (Decorator $d) => $d->namespace, $registry->for(BlockName::fromString($type)));
    expect($names('acme/box'))->toBe(['acme/visibility', 'acme/anim'])->and($names('acme/text'))->toBe(['acme/visibility']);
});

test('a decorator with an unsupported schema is rejected', function () {
    $registry = new DecoratorRegistry(new SubsetSchemaValidator());
    expect(fn () => $registry->add(new Decorator('acme/bad', ['type' => 'object', 'oneOf' => []])))
        ->toThrow(UnsupportedSchemaException::class)
        ->and($registry->get('acme/bad'))->toBeNull();
});

test('a decorator with the same namespace is replaced in place', function () {
    $registry = new DecoratorRegistry(new SubsetSchemaValidator());
    $registry->add(new Decorator('acme/a', ['type' => 'object']));
    $registry->add(new Decorator('acme/b', ['type' => 'object']));
    $registry->add(new Decorator('acme/a', ['type' => 'object', 'title' => 'New']));
    $all = $registry->for(BlockName::fromString('acme/box'));
    expect(array_map(fn (Decorator $d) => $d->namespace, $all))->toBe(['acme/a', 'acme/b'])
        ->and($registry->get('acme/a')->settingsSchema['title'])->toBe('New');
    $registry->remove('acme/a');
    expect($registry->get('acme/a'))->toBeNull();
});
