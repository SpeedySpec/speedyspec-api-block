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

        it('rejects an unsupported keyword', function () use ($make) {
            expect(fn () => $make()->assertSupported(['oneOf' => []]))
                ->toThrow(UnsupportedSchemaException::class);
        });
    });
}
