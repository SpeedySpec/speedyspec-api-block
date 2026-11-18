<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;
use SpeedySpec\Block\Domain\ValueObjects\ResponsiveValue;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

test('a breakpoint has a plain name and a positive width', function () {
    $tablet = new Breakpoint('tablet', 768);

    expect($tablet->name)->toBe('tablet')->and($tablet->minWidth)->toBe(768);
});

test('a breakpoint refuses a name or width that cannot go into a stylesheet', function (string $name, int $width) {
    expect(fn () => new Breakpoint($name, $width))->toThrow(InvalidStyleException::class);
})->with([
    'reserved' => ['base', 768],
    'upper case' => ['Tablet', 768],
    'markup' => ['a){}', 768],
    'empty' => ['', 768],
    'zero' => ['tablet', 0],
    'negative' => ['tablet', -1],
]);

test('a single stored value is the base value at every width', function () {
    $value = ResponsiveValue::from('1rem');

    expect($value->base())->toBe('1rem')->and($value->overrides())->toBe([])->and($value->isEmpty())->toBeFalse();
});

test('a stored map gives a base value and one override per breakpoint', function () {
    $value = ResponsiveValue::from(['base' => '1rem', 'tablet' => '2rem', 'desktop' => '3rem']);

    expect($value->base())->toBe('1rem')
        ->and($value->overrides())->toBe(['tablet' => '2rem', 'desktop' => '3rem'])
        ->and($value->at('tablet'))->toBe('2rem')
        ->and($value->at('watch'))->toBeNull();
});

test('a responsive value can have overrides and no base, and nothing stored is empty', function () {
    expect(ResponsiveValue::from(['tablet' => 'x'])->base())->toBeNull()
        ->and(ResponsiveValue::from(['tablet' => 'x'])->isEmpty())->toBeFalse()
        ->and(ResponsiveValue::from(null)->isEmpty())->toBeTrue()
        ->and(ResponsiveValue::from([])->isEmpty())->toBeTrue()
        ->and(ResponsiveValue::from(['base' => null, 'tablet' => null])->isEmpty())->toBeTrue();
});

test('a style rule keeps safe declarations and the breakpoint they apply from', function () {
    $rule = new StyleRule(['padding' => '1rem 2rem', 'color' => 'var(--color-primary)'], 'tablet');

    expect($rule->declarations)->toBe(['padding' => '1rem 2rem', 'color' => 'var(--color-primary)'])
        ->and($rule->breakpoint)->toBe('tablet')
        ->and((new StyleRule(['width' => 'calc(100% - 2rem)']))->breakpoint)->toBeNull()
        ->and((new StyleRule(['grid-template-columns' => 'repeat(3, minmax(0, 1fr))']))->declarations)
        ->toBe(['grid-template-columns' => 'repeat(3, minmax(0, 1fr))']);
});

test('a style rule refuses anything that could break out of its declaration', function (
    string $property,
    mixed $value,
) {
    expect(fn () => new StyleRule([$property => $value]))->toThrow(InvalidStyleException::class);
})->with([
    'unclosed function' => ['width', 'calc(1px'],
    'unclosed nested function' => ['width', 'var(--x, calc('],
    'stray closing bracket' => ['width', '1px)'],
    'brackets out of order' => ['width', ')('],
    'semicolon' => ['color', 'red; background: url(x)'],
    'closing brace' => ['color', 'red} body{display:none'],
    'url' => ['background', 'url(https://evil.example/x.png)'],
    'url upper case' => ['background', 'URL(//evil.example)'],
    'expression' => ['width', 'expression(alert(1))'],
    'unknown function' => ['width', 'attr(data-x)'],
    'import' => ['color', '@import "x"'],
    'quote' => ['color', '"red"'],
    'backslash' => ['color', 'r\\65 d'],
    'markup' => ['color', '</style><script>'],
    'comment' => ['color', 'red/**/'],
    'line break' => ['color', "red\nblue"],
    'empty' => ['color', ''],
    'not text' => ['color', ['red']],
    'too long' => ['color', str_repeat('a', 201)],
    'property with space' => ['color x', 'red'],
    'property with colon' => ['color:red;x', 'red'],
    'upper case property' => ['COLOR', 'red'],
    'empty property' => ['', 'red'],
]);

test('a style rule refuses a breakpoint name that could not be a breakpoint', function () {
    expect(fn () => new StyleRule(['color' => 'red'], 'a){}'))->toThrow(InvalidStyleException::class)
        ->and(fn () => new StyleRule([]))->toThrow(InvalidStyleException::class);
});
