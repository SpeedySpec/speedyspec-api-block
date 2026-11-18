<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;

test('set adds a value and a missing key argument yields none', function () {
    $source = new ArrayDataSource('acme/values');
    $source->set('n', 3);

    expect($source->resolve(['key' => 'n'], Context::empty(), Mode::Front)->get())->toBe(3)
        ->and($source->resolve([], Context::empty(), Mode::Front)->hasValue())->toBeFalse();
});

test('a stored null is a value, not none', function () {
    $source = new ArrayDataSource('acme/values', ['x' => null]);

    expect($source->resolve(['key' => 'x'], Context::empty(), Mode::Admin)->hasValue())->toBeTrue();
});

test('a non-string key argument yields none', function () {
    $source = new ArrayDataSource('acme/values', ['1' => 'one']);

    expect($source->resolve(['key' => 1], Context::empty(), Mode::Front)->hasValue())->toBeFalse();
});

test('it reports the configured name and value schema', function () {
    $source = new ArrayDataSource('acme/values', [], ['type' => 'string']);

    expect($source->name())->toBe('acme/values')
        ->and($source->valueSchema())->toBe(['type' => 'string']);
});

test('an unconfigured value schema accepts any value', function () {
    expect((new ArrayDataSource('acme/values'))->valueSchema())->toBe([]);
});

test('it describes its arguments as an object with a required string key', function () {
    expect((new ArrayDataSource('acme/values'))->argumentsSchema())->toBe([
        'type' => 'object',
        'required' => ['key'],
        'properties' => ['key' => ['type' => 'string']],
    ]);
});
