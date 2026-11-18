<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

test('accepts vendor/name and exposes its parts', function () {
    $name = BlockName::fromString('speedyspec/text-field');
    expect($name->vendor())->toBe('speedyspec')->and($name->name())->toBe('text-field')
        ->and($name->toString())->toBe('speedyspec/text-field');
});

test('rejects names that are not lower-case vendor/name', function (string $value) {
    expect(fn () => BlockName::fromString($value))->toThrow(InvalidBlockNameException::class);
})->with(['paragraph', 'Core/Paragraph', 'core/', '/x', 'a/b/c', 'core/para graph', "core/x\n", '']);

test('equals is true for equal names and false otherwise', function () {
    $name = BlockName::fromString('core/paragraph');
    expect($name->equals(BlockName::fromString('core/paragraph')))->toBeTrue()
        ->and($name->equals(BlockName::fromString('core/heading')))->toBeFalse()
        ->and($name->equals(BlockName::fromString('other/paragraph')))->toBeFalse();
});

test('mode cases carry their string values', function () {
    expect(Mode::Front->value)->toBe('front')->and(Mode::Admin->value)->toBe('admin');
});
