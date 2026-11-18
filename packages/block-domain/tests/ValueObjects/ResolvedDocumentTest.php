<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;

/**
 * Builds a resolved block with the given passes of children.
 */
function resolvedBlock(string $id, array $passes = []): ResolvedBlock
{
    $block = new Block($id, BlockName::fromString('acme/box'), 1, [], [], [], []);

    return new ResolvedBlock($block, null, [], [], null, Context::empty(), null, $passes);
}

/**
 * Wraps resolved roots in a resolved document.
 */
function resolvedDocument(ResolvedBlock ...$roots): ResolvedDocument
{
    return new ResolvedDocument(new Document('d', 'page', new BlockTree()), Mode::Front, array_values($roots), []);
}

test('find locates the last block of a large flat document quickly', function () {
    $document = resolvedDocument(...array_map(fn (int $i) => resolvedBlock('b' . $i), range(1, 5000)));
    $started = microtime(true);

    for ($i = 0; $i < 100; $i++)
    {
        $found = $document->find('b5000');
    }

    expect($found->block->id)->toBe('b5000')->and(microtime(true) - $started)->toBeLessThan(1.0);
});

test('find locates the innermost block of a deeply nested document', function () {
    $block = resolvedBlock('n2000');

    for ($i = 1999; $i >= 1; $i--)
    {
        $block = resolvedBlock('n' . $i, [[$block]]);
    }

    expect(resolvedDocument($block)->find('n2000')->block->id)->toBe('n2000');
});

test('find searches depth-first through first passes only and returns the first match', function () {
    $first = resolvedBlock('dup');
    $second = resolvedBlock('dup');
    $hidden = resolvedBlock('later');
    $document = resolvedDocument(
        resolvedBlock('a', [[resolvedBlock('a1', [[$first]])], [$hidden]]),
        $second,
    );
    expect($document->find('dup'))->toBe($first)->and($document->find('later'))->toBeNull()
        ->and($document->find('a1')->block->id)->toBe('a1');
});
