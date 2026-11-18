<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;

use function SpeedySpec\Block\ContractTests\schemaValidatorContract;

schemaValidatorContract(fn () => new SubsetSchemaValidator());

if (!function_exists('subsetValidatorSchema'))
{
    function subsetValidatorSchema(): array
    {
        return ['type' => 'object', 'required' => ['label'], 'additionalProperties' => false, 'properties' => [
            'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 5],
            'size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 3, 'default' => 2],
            'kind' => ['enum' => ['a', 'b']],
            'tags' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 2, 'items' => ['type' => 'string', 'pattern' => '^[a-z]+$']],
            'style' => ['type' => 'object', 'properties' => ['color' => ['type' => ['string', 'null'], 'default' => null]]],
        ]];
    }
}

test('reports each failing rule with its path and keyword', function (array $value, string $path, string $keyword) {
    $errors = (new SubsetSchemaValidator())->validate($value, subsetValidatorSchema())->errors();
    expect($errors)->toHaveCount(1)->and($errors[0]->path)->toBe($path)->and($errors[0]->keyword)->toBe($keyword);
})->with([
    [[], 'label', 'required'],
    [['label' => 'toolong'], 'label', 'maxLength'],
    [['label' => ''], 'label', 'minLength'],
    [['label' => 'ok', 'size' => 9], 'size', 'maximum'],
    [['label' => 'ok', 'size' => 0], 'size', 'minimum'],
    [['label' => 'ok', 'size' => '2'], 'size', 'type'],
    [['label' => 'ok', 'kind' => 'c'], 'kind', 'enum'],
    [['label' => 'ok', 'tags' => []], 'tags', 'minItems'],
    [['label' => 'ok', 'tags' => ['a', 'b', 'c']], 'tags', 'maxItems'],
    [['label' => 'ok', 'tags' => ['a', 'B']], 'tags.1', 'pattern'],
    [['label' => 'ok', 'extra' => 1], 'extra', 'additionalProperties'],
    [['label' => 'ok', 'style' => ['color' => 5]], 'style.color', 'type'],
]);

test('a valid value passes', function () {
    $value = ['label' => 'ok', 'size' => 3, 'kind' => 'a', 'tags' => ['a'], 'style' => ['color' => null]];
    expect((new SubsetSchemaValidator())->validate($value, subsetValidatorSchema())->isValid())->toBeTrue();
});

test('a pattern cannot be bypassed with a trailing newline', function () {
    expect((new SubsetSchemaValidator())->validate(['label' => 'ok', 'tags' => ["a\n"]], subsetValidatorSchema())->isValid())->toBeFalse();
});

test('a pattern containing slashes and delimiter-like characters still matches', function () {
    $schema = ['type' => 'string', 'pattern' => '^a/b#c~d$'];
    $v = new SubsetSchemaValidator();
    expect($v->validate('a/b#c~d', $schema)->isValid())->toBeTrue()
        ->and($v->validate('a/b', $schema)->isValid())->toBeFalse();
});

test('a pattern is unicode aware', function () {
    $schema = ['type' => 'string', 'pattern' => '^\p{L}+$', 'maxLength' => 2];
    expect((new SubsetSchemaValidator())->validate('éé', $schema)->isValid())->toBeTrue();
});

test('number accepts ints and floats but not numeric strings', function () {
    $v = new SubsetSchemaValidator();
    $schema = ['type' => 'number'];
    expect($v->validate(1, $schema)->isValid())->toBeTrue()
        ->and($v->validate(1.5, $schema)->isValid())->toBeTrue()
        ->and($v->validate('1.5', $schema)->isValid())->toBeFalse();
});

test('integer rejects floats', function () {
    expect((new SubsetSchemaValidator())->validate(1.0, ['type' => 'integer'])->isValid())->toBeFalse();
});

test('a type list accepts any listed type', function () {
    $v = new SubsetSchemaValidator();
    $schema = ['type' => ['string', 'null']];
    expect($v->validate('a', $schema)->isValid())->toBeTrue()
        ->and($v->validate(null, $schema)->isValid())->toBeTrue()
        ->and($v->validate(1, $schema)->isValid())->toBeFalse();
});

test('const requires strict equality', function () {
    $v = new SubsetSchemaValidator();
    expect($v->validate('x', ['const' => 'x'])->isValid())->toBeTrue()
        ->and($v->validate('y', ['const' => 'x'])->errors()[0]->keyword)->toBe('const')
        ->and($v->validate(0, ['const' => false])->isValid())->toBeFalse();
});

test('minimum applies to floats', function () {
    expect((new SubsetSchemaValidator())->validate(0.5, ['type' => 'number', 'minimum' => 1])->errors()[0]->keyword)
        ->toBe('minimum');
});

test('an empty array satisfies both array and object', function () {
    $v = new SubsetSchemaValidator();
    expect($v->validate([], ['type' => 'array'])->isValid())->toBeTrue()
        ->and($v->validate([], ['type' => 'object'])->isValid())->toBeTrue()
        ->and($v->validate([1], ['type' => 'object'])->isValid())->toBeFalse()
        ->and($v->validate(['a' => 1], ['type' => 'array'])->isValid())->toBeFalse();
});

test('additionalProperties given as a schema validates extra properties against it', function () {
    $schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'additionalProperties' => ['type' => 'integer']];
    $errors = (new SubsetSchemaValidator())->validate(['a' => 'x', 'b' => 1, 'c' => 'no'], $schema)->errors();
    expect($errors)->toHaveCount(1)->and($errors[0]->path)->toBe('c')->and($errors[0]->keyword)->toBe('type');
});

test('validate reports a dotted path through nested items', function () {
    $schema = ['type' => 'object', 'properties' => ['rows' => ['type' => 'array', 'items' => [
        'type' => 'object', 'required' => ['label'], 'properties' => ['label' => ['type' => 'string']],
    ]]]];
    $errors = (new SubsetSchemaValidator())->validate(['rows' => [['label' => 'a'], ['label' => 3], []]], $schema)->errors();
    expect(array_map(fn ($e) => $e->path . ':' . $e->keyword, $errors))->toBe(['rows.1.label:type', 'rows.2.label:required']);
});

test('validate ignores unsupported keywords instead of throwing', function () {
    expect((new SubsetSchemaValidator())->validate('x', ['type' => 'string', 'format' => 'email', 'oneOf' => []])->isValid())->toBeTrue();
});

test('withDefaults fills nested defaults and keeps present values', function () {
    expect((new SubsetSchemaValidator())->withDefaults(['label' => 'x', 'style' => []], subsetValidatorSchema()))
        ->toBe(['label' => 'x', 'style' => ['color' => null], 'size' => 2]);
});

test('withDefaults never overwrites a present key, even a null one', function () {
    $schema = ['properties' => ['a' => ['default' => 1]]];
    expect((new SubsetSchemaValidator())->withDefaults(['a' => null], $schema))->toBe(['a' => null]);
});

test('unsupported keywords are rejected with their path', function (array $schema, string $needle) {
    expect(fn () => (new SubsetSchemaValidator())->assertSupported($schema))
        ->toThrow(UnsupportedSchemaException::class, $needle);
})->with([
    [['oneOf' => []], 'oneOf'],
    [['type' => 'object', 'properties' => ['a' => ['format' => 'email']]], 'properties.a.format'],
    [['type' => 'array', 'items' => ['$ref' => '#/x']], 'items.$ref'],
    [['type' => 'object', 'additionalProperties' => ['anyOf' => []]], 'additionalProperties.anyOf'],
    [['type' => 'object', 'properties' => ['a' => ['x-private' => true]]], 'x-private'],
]);

test('a supported schema with annotations passes', function () {
    (new SubsetSchemaValidator())->assertSupported(subsetValidatorSchema() + ['title' => 'T', 'description' => 'D', 'x-control' => 'c', 'x-group' => 'g']);
    expect(true)->toBeTrue();
});

test('x-private is allowed only on top-level properties when asked', function () {
    $v = new SubsetSchemaValidator();
    $top = ['type' => 'object', 'properties' => ['to' => ['type' => 'string', 'x-private' => true], 'b' => ['type' => 'string']]];
    $deep = ['type' => 'object', 'properties' => ['a' => ['type' => 'object', 'properties' => ['b' => ['x-private' => true]]]]];
    $v->assertSupported($top, allowPrivate: true);
    expect($v->privateProperties($top))->toBe(['to'])
        ->and(fn () => $v->assertSupported($deep, allowPrivate: true))->toThrow(UnsupportedSchemaException::class, 'properties.a.properties.b.x-private');
});

test('subschema follows a property path', function () {
    $v = new SubsetSchemaValidator();
    expect($v->subschema(subsetValidatorSchema(), ['style', 'color']))->toBe(['type' => ['string', 'null'], 'default' => null])
        ->and($v->subschema(subsetValidatorSchema(), ['nope']))->toBeNull()
        ->and($v->subschema(subsetValidatorSchema(), []))->toBe(subsetValidatorSchema());
});
