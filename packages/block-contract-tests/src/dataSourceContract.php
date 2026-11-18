<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Registers the invariants every data source must hold, so a replacement behaves like the default.
 *
 * @param Closure $make
 *   Builds a fresh DataSourceInterface for each test.
 * @param Closure $makeValidator
 *   Builds the SchemaValidatorInterface used to check the source's schemas and values.
 * @param array<string, mixed> $knownArguments
 *   Arguments the source resolves to a value.
 * @param array<string, mixed> $unknownArguments
 *   Arguments valid for the source that resolve to no value.
 *
 * @since 0.1.0
 */
function dataSourceContract(
    Closure $make,
    Closure $makeValidator,
    array $knownArguments,
    array $unknownArguments,
): void
{
    describe('DataSourceInterface contract', function () use (
        $make,
        $makeValidator,
        $knownArguments,
        $unknownArguments,
    ) {
        it('names itself with a non-empty string', function () use ($make) {
            expect($make()->name())->toBeString()->not->toBeEmpty();
        });

        it('declares schemas the validator supports', function () use ($make, $makeValidator) {
            $source = $make();
            $validator = $makeValidator();

            $validator->assertSupported($source->argumentsSchema());
            $validator->assertSupported($source->valueSchema());

            expect(true)->toBeTrue();
        });

        it('resolves known arguments to a value matching the value schema', function () use (
            $make,
            $makeValidator,
            $knownArguments,
        ) {
            $source = $make();
            $validator = $makeValidator();
            $result = $source->resolve($knownArguments, Context::empty(), Mode::Front);

            expect($validator->validate($knownArguments, $source->argumentsSchema())->isValid())->toBeTrue()
                ->and($result->hasValue())->toBeTrue()
                ->and($validator->validate($result->get(), $source->valueSchema())->isValid())->toBeTrue();
        });

        it('resolves unknown arguments to none', function () use ($make, $unknownArguments) {
            $source = $make();

            expect($source->resolve($unknownArguments, Context::empty(), Mode::Front)->hasValue())->toBeFalse()
                ->and($source->resolve($unknownArguments, Context::empty(), Mode::Admin)->hasValue())->toBeFalse();
        });

        it('resolves the same arguments to the same result in both modes', function () use (
            $make,
            $knownArguments,
        ) {
            $source = $make();

            foreach ([Mode::Front, Mode::Admin] as $mode)
            {
                $first = $source->resolve($knownArguments, Context::empty(), $mode);
                $second = $source->resolve($knownArguments, Context::empty(), $mode);

                expect($second->hasValue())->toBe($first->hasValue())
                    ->and($second->get())->toEqual($first->get());
            }
        });
    });
}
