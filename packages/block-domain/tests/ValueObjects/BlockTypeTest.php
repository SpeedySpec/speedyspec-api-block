<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\CatalogueMetadata;
use SpeedySpec\Block\Domain\ValueObjects\ChildrenPolicy;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\ParentPolicy;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException;

test('a block type round-trips through an array', function () {
    $data = ['name' => 'acme/field', 'version' => 2, 'title' => 'Field', 'description' => 'd', 'category' => 'forms',
        'keywords' => ['input'], 'icon' => 'text', 'settings' => ['type' => 'object', 'properties' => ['label' => ['type' => 'string']]],
        'view' => null, 'children' => false, 'parents' => ['acme/form'], 'provides' => [], 'uses' => ['acme/form-id'],
        'assets' => ['acme-field'], 'examples' => [['label' => 'Name']], 'transforms' => []];
    expect(BlockType::fromArray($data)->toArray())->toBe($data);
});

test('a block type needs a name, a title and a positive version', function (array $data) {
    expect(fn () => BlockType::fromArray($data))->toThrow(InvalidBlockTypeException::class);
})->with([[['title' => 'T']], [['name' => 'acme/x']], [['name' => 'acme/x', 'title' => 'T', 'version' => 0]]]);

test('a block type applies defaults for omitted keys', function () {
    $type = BlockType::fromArray(['name' => 'acme/x', 'title' => 'X']);
    expect($type->toArray())->toBe(['name' => 'acme/x', 'version' => 1, 'title' => 'X', 'description' => '',
        'category' => '', 'keywords' => [], 'icon' => null, 'settings' => ['type' => 'object'], 'view' => null,
        'children' => false, 'parents' => true, 'provides' => [], 'uses' => [], 'assets' => [], 'examples' => [],
        'transforms' => []])
        ->and($type->children->allows(BlockName::fromString('acme/y')))->toBeFalse()
        ->and($type->parents->allows(null))->toBeTrue();
});

test('Binding targets a source and can carry a fallback', function () {
    $plain = Binding::to('acme/post-title', ['id' => 1]);
    expect($plain->source)->toBe('acme/post-title')->and($plain->arguments)->toBe(['id' => 1])
        ->and($plain->hasFallback)->toBeFalse();
    $withNull = $plain->withFallback(null);
    expect($withNull->hasFallback)->toBeTrue()->and($withNull->fallback)->toBeNull()
        ->and($plain->hasFallback)->toBeFalse();
});

test('Binding round-trips through an array with and without a fallback', function () {
    $plain = Binding::to('acme/src', ['a' => 1]);
    $fb = $plain->withFallback('x');
    expect($plain->toArray())->toBe(['source' => 'acme/src', 'arguments' => ['a' => 1]])
        ->and(Binding::fromArray($plain->toArray())->hasFallback)->toBeFalse()
        ->and(Binding::fromArray($fb->toArray())->toArray())->toBe($fb->toArray())
        ->and(Binding::fromArray($fb->toArray())->fallback)->toBe('x');
    $nul = $plain->withFallback(null);
    expect(Binding::fromArray($nul->toArray())->hasFallback)->toBeTrue();
});

test('Decorator applies to all types or only listed ones', function () {
    $all = new Decorator('acme/visibility', ['type' => 'object']);
    $some = new Decorator('acme/visibility', ['type' => 'object'], ['acme/box']);
    $box = BlockName::fromString('acme/box');
    $other = BlockName::fromString('acme/other');
    expect($all->appliesTo($other))->toBeTrue()->and($some->appliesTo($box))->toBeTrue()
        ->and($some->appliesTo($other))->toBeFalse();
});

test('Decorator rejects an invalid namespace', function () {
    expect(fn () => new Decorator('Bad', ['type' => 'object']))->toThrow(InvalidBlockNameException::class);
});

test('policies round-trip through arrays', function () {
    $form = BlockName::fromString('acme/form');
    expect(ChildrenPolicy::none()->toArray())->toBeFalse()
        ->and(ChildrenPolicy::any()->toArray())->toBeTrue()
        ->and(ChildrenPolicy::only($form)->toArray())->toBe(['acme/form'])
        ->and(ChildrenPolicy::fromArray(null)->toArray())->toBeFalse()
        ->and(ChildrenPolicy::fromArray([])->toArray())->toBeFalse()
        ->and(ChildrenPolicy::fromArray(true)->toArray())->toBeTrue()
        ->and(ChildrenPolicy::fromArray(['acme/form'])->toArray())->toBe(['acme/form'])
        ->and(ParentPolicy::any()->toArray())->toBeTrue()
        ->and(ParentPolicy::only($form)->toArray())->toBe(['acme/form'])
        ->and(ParentPolicy::fromArray(null)->toArray())->toBeTrue()
        ->and(ParentPolicy::fromArray(true)->toArray())->toBeTrue()
        ->and(ParentPolicy::fromArray(['acme/form'])->toArray())->toBe(['acme/form']);
});

test('a malformed block type name throws InvalidBlockTypeException', function () {
    expect(fn () => BlockType::fromArray(['name' => 'Bad', 'title' => 'T']))->toThrow(InvalidBlockTypeException::class);
});

test('block type array fields must have the right shape', function (array $extra) {
    expect(fn () => BlockType::fromArray(['name' => 'acme/x', 'title' => 'T'] + $extra))
        ->toThrow(InvalidBlockTypeException::class);
})->with([[['settings' => 'x']], [['view' => 'x']], [['provides' => 'x']], [['uses' => 'x']], [['assets' => 'x']],
    [['examples' => 'x']], [['keywords' => 'x']]]);

test('Binding::fromArray needs a string source', function (array $data) {
    expect(fn () => Binding::fromArray($data))->toThrow(InvalidArgumentException::class);
})->with([[[]], [['source' => 5]]]);

test('a block type defined by data rejects a bad value in any field, naming the field', function (string $field, mixed $value) {
    expect(fn () => BlockType::fromArray(['name' => 'acme/x', 'title' => 'T', $field => $value]))
        ->toThrow(InvalidBlockTypeException::class, sprintf('"%s"', $field));
})->with([
    'provides with a list value' => ['provides', ['acme/k' => ['x']]],
    'provides with an empty view key' => ['provides', ['acme/k' => '']],
    'provides with an empty context key' => ['provides', ['' => 'x']],
    'provides as a list' => ['provides', ['x']],
    'uses with a non-string' => ['uses', [5]],
    'uses as a map' => ['uses', ['a' => 'acme/k']],
    'assets with a nested array' => ['assets', [['x']]],
    'keywords with a non-string' => ['keywords', [true]],
    'keywords as a string' => ['keywords', 'x'],
    'examples with a string' => ['examples', ['oops']],
    'examples as a map' => ['examples', ['a' => []]],
    'title as an array' => ['title', ['T']],
    'description as an array' => ['description', ['d']],
    'category as an int' => ['category', 3],
    'icon as an array' => ['icon', ['i']],
    'children with a bad name' => ['children', ['Bad Name']],
    'children with a nested array' => ['children', [['acme/y']]],
    'children as a string' => ['children', 'acme/y'],
    'children as a map' => ['children', ['a' => 'acme/y']],
    'parents with a bad name' => ['parents', ['Bad Name']],
    'parents as false' => ['parents', false],
    'parents as a string' => ['parents', 'acme/y'],
    'version as a string' => ['version', '2'],
    'version as a float' => ['version', 2.0],
]);

test('a block type defined by data accepts null and boolean policies and a null icon', function () {
    $type = BlockType::fromArray(['name' => 'acme/x', 'title' => 'T', 'children' => null, 'parents' => null, 'icon' => null]);
    $any = BlockType::fromArray(['name' => 'acme/x', 'title' => 'T', 'children' => true, 'parents' => true]);
    expect($type->children->toArray())->toBeFalse()->and($type->parents->toArray())->toBeTrue()
        ->and($type->catalogue->icon)->toBeNull()->and($any->children->toArray())->toBeTrue();
});

test('a block type built in code cannot bypass the shape of its lists and maps', function (array $named) {
    expect(fn () => new BlockType(BlockName::fromString('acme/x'), new CatalogueMetadata('T'), ...$named))
        ->toThrow(InvalidBlockTypeException::class);
})->with([
    'provides' => [['provides' => ['acme/k' => ['x']]]],
    'uses' => [['uses' => [5]]],
    'assets' => [['assets' => [null]]],
    'examples' => [['examples' => ['oops']]],
]);
