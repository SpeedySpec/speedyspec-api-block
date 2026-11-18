<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\BlockTypeRegistry;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

test('registering a type with an unsupported schema keyword throws', function () {
    $registry = new BlockTypeRegistry(new SubsetSchemaValidator());
    expect(fn () => $registry->add(type('acme/x', ['settings' => ['type' => 'object', 'properties' => ['a' => ['format' => 'email']]]])))
        ->toThrow(UnsupportedSchemaException::class);
});

test('registering a type with an unsupported view schema throws', function () {
    $registry = new BlockTypeRegistry(new SubsetSchemaValidator());
    expect(fn () => $registry->add(type('acme/x', ['view' => ['type' => 'object', 'oneOf' => []]])))
        ->toThrow(UnsupportedSchemaException::class);
});

test('registering a type whose example fails its own schema throws', function () {
    $registry = new BlockTypeRegistry(new SubsetSchemaValidator());
    $type = type('acme/x', ['settings' => ['type' => 'object', 'required' => ['label'], 'properties' => ['label' => ['type' => 'string']]], 'examples' => [['label' => 'ok'], []]]);
    expect(fn () => $registry->add($type))->toThrow(InvalidBlockTypeException::class, '1')
        ->and($registry->has(BlockName::fromString('acme/x')))->toBeFalse();
});

test('an example is validated after defaults are applied', function () {
    $registry = new BlockTypeRegistry(new SubsetSchemaValidator());
    $registry->add(type('acme/x', ['settings' => ['type' => 'object', 'required' => ['label'], 'properties' => ['label' => ['type' => 'string', 'default' => 'hi']]], 'examples' => [[]]]));
    expect($registry->has(BlockName::fromString('acme/x')))->toBeTrue();
});

test('types can be added, replaced, listed and removed', function () {
    $registry = new BlockTypeRegistry(new SubsetSchemaValidator());
    $registry->add(type('acme/a'));
    $registry->add(type('acme/a', ['title' => 'Second']));
    $registry->add(type('acme/b'));
    expect($registry->get(BlockName::fromString('acme/a'))->catalogue->title)->toBe('Second')
        ->and(count($registry->all()))->toBe(2);
    $registry->remove(BlockName::fromString('acme/a'));
    expect($registry->has(BlockName::fromString('acme/a')))->toBeFalse()
        ->and($registry->get(BlockName::fromString('acme/a')))->toBeNull();
});

test('registration order is kept and a replaced type keeps its position', function () {
    $registry = new BlockTypeRegistry(new SubsetSchemaValidator());
    $registry->add(type('acme/a'));
    $registry->add(type('acme/b'));
    $registry->add(type('acme/a', ['title' => 'Again']));
    expect(array_map(fn ($t) => $t->name->toString(), $registry->all()))->toBe(['acme/a', 'acme/b']);
});
