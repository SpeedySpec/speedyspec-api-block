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

        it('never lets a script or an event attribute through formatted text', function () use ($make) {
            $escaper = $make();

            foreach ([
                '<script>alert(1)</script>',
                '<img src=x onerror=alert(1)>',
                '<a href="javascript:alert(1)">x</a>',
                '<svg onload=alert(1)>',
                '<strong onmouseover="x">y</strong>',
            ] as $hostile)
            {
                $out = strtolower($escaper->richText($hostile));

                expect($out)->not->toContain('<script')->not->toMatch('/\son[a-z]+\s*=/')->not->toContain('javascript:');
            }
        });

        it('renders nothing for an empty attribute bag', function () use ($make) {
            expect($make()->attributes(AttributeBag::empty()))->toBe('');
        });

        it('drops a script url placed in a link-bearing attribute of a bag', function () use ($make) {
            foreach (['href', 'src', 'action', 'formaction', 'xlink:href', 'poster', 'HREF'] as $name)
            {
                $bag = AttributeBag::empty()->withAttribute($name, 'javascript:alert(1)');

                expect(strtolower($make()->attributes($bag)))->not->toContain('javascript:');
            }
        });

        it('keeps a safe url placed in a link-bearing attribute of a bag', function () use ($make) {
            $bag = AttributeBag::empty()->withAttribute('href', 'https://example.test/a');

            expect($make()->attributes($bag))->toContain('https://example.test/a');
        });

        it('leaves no raw single quote in an escaped attribute value', function () use ($make) {
            expect($make()->attribute("it's"))->not->toContain("'");
        });

        it('drops data urls and urls led by a control character', function () use ($make) {
            expect($make()->url('data:text/html,x'))->toBe('')
                ->and($make()->url("\x01javascript:alert(1)"))->toBe('');
        });

        it('escapes attribute values taken from a bag', function () use ($make) {
            $bag = AttributeBag::empty()->withAttribute('data-x', '"><script>');

            expect($make()->attributes($bag))->not->toContain('<script>');
        });
    });
}
