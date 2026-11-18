<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;

/**
 * Registers the invariants every schema validator must hold, so a replacement behaves like the default.
 *
 * @param Closure $make
 *   Builds a fresh SchemaValidatorInterface for each test.
 *
 * @since 0.1.0
 */
function schemaValidatorContract(Closure $make): void
{
    describe('SchemaValidatorInterface contract', function () use ($make) {
        $schema = [
            'type' => 'object',
            'required' => ['label'],
            'properties' => [
                'label' => ['type' => 'string'],
                'size' => ['type' => 'integer', 'default' => 2],
            ],
        ];

        it('accepts a value matching a typed object schema', function () use ($make, $schema) {
            expect($make()->validate(['label' => 'x', 'size' => 1], $schema)->isValid())->toBeTrue();
        });

        it('reports a missing required property with the required keyword', function () use ($make, $schema) {
            $errors = $make()->validate([], $schema)->errors();

            expect($errors)->toHaveCount(1)
                ->and($errors[0]->path)->toBe('label')
                ->and($errors[0]->keyword)->toBe('required');
        });

        it('fills a missing property from its default and keeps a present one', function () use ($make, $schema) {
            $validator = $make();

            expect($validator->withDefaults(['label' => 'x'], $schema)['size'])->toBe(2)
                ->and($validator->withDefaults(['label' => 'x', 'size' => 3], $schema)['size'])->toBe(3);
        });

        it('rejects a keyword no validator implements', function () use ($make) {
            expect(fn () => $make()->assertSupported(['x-not-a-keyword-anyone-supports' => true]))
                ->toThrow(UnsupportedSchemaException::class);
        });

        $private = [
            'type' => 'object',
            'properties' => [
                'to' => ['type' => 'string', 'x-private' => true],
                'token' => ['type' => 'string', 'x-private' => true],
                'shown' => ['type' => 'string', 'x-private' => false],
                'title' => ['type' => 'string'],
                'style' => ['type' => 'object', 'properties' => ['color' => ['type' => 'string']]],
            ],
        ];

        it('lists exactly the top-level properties marked private', function () use ($make, $private) {
            expect($make()->privateProperties($private))->toEqualCanonicalizing(['to', 'token'])
                ->and($make()->privateProperties(['type' => 'object']))->toBe([]);
        });

        it('accepts top-level x-private only when private settings are allowed', function () use ($make, $private) {
            $validator = $make();
            $validator->assertSupported($private, allowPrivate: true);

            expect(fn () => $validator->assertSupported($private))->toThrow(UnsupportedSchemaException::class);
        });

        it('rejects x-private below the top level even when private settings are allowed', function () use ($make) {
            $nested = ['type' => 'object', 'properties' => [
                'style' => ['type' => 'object', 'properties' => ['color' => ['type' => 'string', 'x-private' => true]]],
            ]];
            $items = ['type' => 'object', 'properties' => [
                'list' => ['type' => 'array', 'items' => ['type' => 'string', 'x-private' => true]],
            ]];

            expect(fn () => $make()->assertSupported($nested, allowPrivate: true))
                ->toThrow(UnsupportedSchemaException::class)
                ->and(fn () => $make()->assertSupported($items, allowPrivate: true))
                ->toThrow(UnsupportedSchemaException::class);
        });

        it('finds top-level and nested subschemas, or null when undeclared', function () use ($make, $private) {
            $validator = $make();

            expect($validator->subschema($private, ['title']))->toBe(['type' => 'string'])
                ->and($validator->subschema($private, ['style', 'color']))->toBe(['type' => 'string'])
                ->and($validator->subschema($private, ['missing']))->toBeNull()
                ->and($validator->subschema($private, ['style', 'missing']))->toBeNull();
        });
    });
}
