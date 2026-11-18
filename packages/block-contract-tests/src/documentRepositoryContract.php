<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Registers the invariants every document repository must hold, so a replacement behaves like the default.
 *
 * @param Closure $make
 *   Builds a fresh, empty DocumentRepositoryInterface for each test.
 *
 * @since 0.1.0
 */
function documentRepositoryContract(Closure $make): void
{
    describe('DocumentRepositoryInterface contract', function () use ($make) {
        $node = fn (string $id, Block ...$children): Block => new Block(
            $id,
            BlockName::fromString('acme/box'),
            1,
            [],
            [],
            [],
            array_values($children),
        );
        $document = fn (string $id, string $kind, Block ...$roots): Document => new Document(
            $id,
            $kind,
            new BlockTree(...$roots),
            2,
        );

        it('returns null for an unknown id', function () use ($make) {
            expect($make()->find('nope'))->toBeNull();
        });

        it('finds a saved document with the same id, kind, version and block ids', function () use (
            $make,
            $node,
            $document,
        ) {
            $repository = $make();
            $repository->save($document('a', 'page', $node('r1', $node('c1')), $node('r2')));

            $found = $repository->find('a');

            expect($found)->not->toBeNull()
                ->and($found->id)->toBe('a')
                ->and($found->kind)->toBe('page')
                ->and($found->formatVersion)->toBe(2)
                ->and($found->tree->ids())->toBe(['r1', 'c1', 'r2']);
        });

        it('replaces a document saved again under the same id', function () use ($make, $node, $document) {
            $repository = $make();
            $repository->save($document('a', 'page', $node('r1', $node('c1'))));
            $repository->save($document('a', 'post', $node('r9', $node('c9'))));

            $found = $repository->find('a');

            expect($found->kind)->toBe('post')
                ->and($found->tree->ids())->toBe(['r9', 'c9']);
        });

        it('keeps documents saved under other ids apart', function () use ($make, $node, $document) {
            $repository = $make();
            $repository->save($document('a', 'page', $node('r1', $node('c1'))));
            $repository->save($document('b', 'post', $node('r2', $node('c2'))));

            expect($repository->find('a')->tree->ids())->toBe(['r1', 'c1'])
                ->and($repository->find('b')->tree->ids())->toBe(['r2', 'c2']);
        });

        it('forgets a removed document and leaves the others', function () use ($make, $node, $document) {
            $repository = $make();
            $repository->save($document('a', 'page', $node('r1', $node('c1'))));
            $repository->save($document('b', 'page', $node('r2', $node('c2'))));
            $repository->remove('a');

            expect($repository->find('a'))->toBeNull()
                ->and($repository->find('b'))->not->toBeNull();
        });

        it('does not throw when removing an unknown id', function () use ($make) {
            $make()->remove('nope');

            expect(true)->toBeTrue();
        });
    });
}
