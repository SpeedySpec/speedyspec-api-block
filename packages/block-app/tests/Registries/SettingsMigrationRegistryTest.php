<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\SettingsMigrationRegistry;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

test('a migration chain is ordered, empty when equal, and null when a step is missing', function () {
    $registry = new SettingsMigrationRegistry();
    $name = BlockName::fromString('acme/x');
    $registry->add($name, migration(from: 2));
    $registry->add($name, migration(from: 1));
    expect(array_map(fn ($m) => $m->from(), $registry->chain($name, 1, 3)))->toBe([1, 2])
        ->and($registry->chain($name, 3, 3))->toBe([])
        ->and($registry->chain($name, 1, 4))->toBeNull()
        ->and($registry->chain($name, 3, 1))->toBeNull();
});

test('a chain can start part way and migrations are per type', function () {
    $registry = new SettingsMigrationRegistry();
    $registry->add(BlockName::fromString('acme/x'), migration(from: 1));
    $registry->add(BlockName::fromString('acme/x'), migration(from: 2));
    expect(array_map(fn ($m) => $m->from(), $registry->chain(BlockName::fromString('acme/x'), 2, 3)))->toBe([2])
        ->and($registry->chain(BlockName::fromString('acme/y'), 1, 2))->toBeNull();
});

test('adding a migration for a version already registered replaces it', function () {
    $registry = new SettingsMigrationRegistry();
    $name = BlockName::fromString('acme/x');
    $first = migration(from: 1);
    $second = migration(from: 1);
    $registry->add($name, $first);
    $registry->add($name, $second);
    expect($registry->chain($name, 1, 2))->toBe([$second]);
});
