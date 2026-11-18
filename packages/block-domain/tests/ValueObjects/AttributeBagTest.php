<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Exceptions\InvalidAttributeException;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;

test('collects classes, attributes, styles and an id immutably', function () {
    $empty = AttributeBag::empty();
    $bag = $empty->withClass('a', 'b', 'a')->withAttribute('data-x', '1')->withStyle('color', 'red')->withId('top');

    expect($empty->isEmpty())->toBeTrue()
        ->and($bag->isEmpty())->toBeFalse()
        ->and($bag->classes())->toBe(['a', 'b'])
        ->and($bag->attributes())->toBe(['data-x' => '1'])
        ->and($bag->styles())->toBe(['color' => 'red'])
        ->and($bag->id())->toBe('top');
});

test('merge unions classes and lets the other bag win elsewhere', function () {
    $a = AttributeBag::empty()->withClass('a')->withAttribute('data-x', '1')->withStyle('color', 'red')->withId('one');
    $b = AttributeBag::empty()->withClass('b', 'a')->withAttribute('data-x', '2')->withStyle('color', 'blue')->withId('two');
    $merged = $a->merge($b);

    expect($merged->classes())->toBe(['a', 'b'])
        ->and($merged->attributes())->toBe(['data-x' => '2'])
        ->and($merged->styles())->toBe(['color' => 'blue'])
        ->and($merged->id())->toBe('two')
        ->and($a->id())->toBe('one');
});

test('merging an empty bag keeps the id', function () {
    expect(AttributeBag::empty()->withId('one')->merge(AttributeBag::empty())->id())->toBe('one');
});

test('rejects attribute names that could execute or break out', function (string $name) {
    expect(fn () => AttributeBag::empty()->withAttribute($name, 'x'))->toThrow(InvalidAttributeException::class);
})->with(['onclick', 'ONLOAD', 'style', 'Class', 'id', 'a b', 'a>', '', '1x', "x\n", 'x"y']);

test('accepts ordinary attribute names and stores values as given', function () {
    $bag = AttributeBag::empty()->withAttribute('aria-label', 'A & "B"')->withAttribute('data-id', '7')->withAttribute('xml:lang', 'en');

    expect($bag->attributes())->toBe(['aria-label' => 'A & "B"', 'data-id' => '7', 'xml:lang' => 'en']);
});

test('rejects style properties and values that could execute or break out', function (string $property, string $value) {
    expect(fn () => AttributeBag::empty()->withStyle($property, $value))->toThrow(InvalidAttributeException::class);
})->with([
    ['color', 'red; background: url(x)'],
    ['color', 'expression(alert(1))'],
    ['background', 'url(JavaScript:alert(1))'],
    ['color', '</style>'],
    ['color', ''],
    ['col or', 'red'],
    ['color', "red\n"],
    ['color', '@import "x"'],
    ['color', 'a{b}'],
]);

test('accepts custom properties and ordinary values', function () {
    $bag = AttributeBag::empty()->withStyle('--gap', '1rem')->withStyle('margin-top', 'calc(1px + 2%)');

    expect($bag->styles())->toBe(['--gap' => '1rem', 'margin-top' => 'calc(1px + 2%)']);
});

test('rejects unsafe classes and ids', function () {
    expect(fn () => AttributeBag::empty()->withClass('a b'))->toThrow(InvalidAttributeException::class)
        ->and(fn () => AttributeBag::empty()->withClass('a"'))->toThrow(InvalidAttributeException::class)
        ->and(fn () => AttributeBag::empty()->withClass(''))->toThrow(InvalidAttributeException::class)
        ->and(fn () => AttributeBag::empty()->withClass('a=b'))->toThrow(InvalidAttributeException::class)
        ->and(fn () => AttributeBag::empty()->withId('a b'))->toThrow(InvalidAttributeException::class)
        ->and(fn () => AttributeBag::empty()->withId(''))->toThrow(InvalidAttributeException::class);
});

test('rejects an attribute that embeds a document', function (string $name) {
    expect(fn () => AttributeBag::empty()->withAttribute($name, '<p>x</p>'))->toThrow(InvalidAttributeException::class);
})->with(['srcdoc', 'SrcDoc']);
