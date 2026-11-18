<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;

/**
 * Registers the invariants every output escaper must hold, so a replacement cannot let markup through.
 *
 * @param Closure $make
 *   Builds a fresh OutputEscaperInterface for each test.
 *
 * @since 0.1.0
 */
function outputEscaperContract(Closure $make): void
{
    describe('OutputEscaperInterface contract', function () use ($make) {
        it('names a target', function () use ($make) {
            expect($make()->target())->toBeString()->not->toBe('');
        });

        it('leaves no markup characters in escaped text', function () use ($make) {
            expect($make()->text('<b>"&\''))->not->toContain('<')->not->toContain('>')->not->toContain('"');
        });

        it('leaves no raw quote in an escaped attribute value', function () use ($make) {
            expect($make()->attribute("a\"b\nc"))->not->toContain('"');
        });

        it('drops script urls, including ones split by control characters', function () use ($make) {
            expect($make()->url('javascript:alert(1)'))->toBe('')
                ->and($make()->url("java\tscript:alert(1)"))->toBe('');
        });

        it('renders nothing for an empty attribute bag', function () use ($make) {
            expect($make()->attributes(AttributeBag::empty()))->toBe('');
        });

        it('escapes attribute values taken from a bag', function () use ($make) {
            $bag = AttributeBag::empty()->withAttribute('data-x', '"><script>');

            expect($make()->attributes($bag))->not->toContain('<script>');
        });
    });
}
