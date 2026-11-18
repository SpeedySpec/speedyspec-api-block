<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Exceptions\DuplicateBlockIdException;
use SpeedySpec\Block\Domain\Exceptions\InvalidPositionException;
use SpeedySpec\Block\Domain\ValueObjects\Position;

if (!function_exists('walkIds'))
{
    function walkIds(BlockTree $tree): array
    {
        return array_map(fn (Block $b) => $b->id, $tree->walk());
    }
}

test('insert places a block among roots or children at an index', function () {
    $tree = new BlockTree(block('a', 'acme/box', [], block('b'), block('c')), block('d'));

    expect(walkIds($tree->insert(block('x'), Position::root(0))))->toBe(['x', 'a', 'b', 'c', 'd'])
        ->and(walkIds($tree->insert(block('x'), Position::root(2))))->toBe(['a', 'b', 'c', 'd', 'x'])
        ->and(walkIds($tree->insert(block('x'), Position::inside('a', 1))))->toBe(['a', 'b', 'x', 'c', 'd'])
        ->and(walkIds($tree->insert(block('x'), Position::inside('b', 0))))->toBe(['a', 'b', 'x', 'c', 'd'])
        ->and(walkIds($tree))->toBe(['a', 'b', 'c', 'd']);
});

test('insert works on an empty tree', function () {
    expect(walkIds((new BlockTree())->insert(block('x'), Position::root(0))))->toBe(['x']);
});

test('insert refuses a missing parent, an index past the end, and a colliding id', function () {
    $tree = new BlockTree(block('a', 'acme/box', [], block('b')));

    expect(fn () => $tree->insert(block('x'), Position::inside('nope', 0)))->toThrow(InvalidPositionException::class)
        ->and(fn () => $tree->insert(block('x'), Position::root(2)))->toThrow(InvalidPositionException::class)
        ->and(fn () => $tree->insert(block('x'), Position::inside('a', 2)))->toThrow(InvalidPositionException::class)
        ->and(fn () => $tree->insert(block('b'), Position::root(0)))->toThrow(DuplicateBlockIdException::class)
        ->and(fn () => $tree->insert(block('x', 'acme/box', [], block('a')), Position::root(0)))
        ->toThrow(DuplicateBlockIdException::class);
});

test('remove takes a block and its descendants and ignores an unknown id', function () {
    $tree = new BlockTree(block('a', 'acme/box', [], block('b', 'acme/box', [], block('c'))), block('d'));

    expect($tree->remove('b')->ids())->toBe(['a', 'd'])
        ->and($tree->remove('a')->ids())->toBe(['d'])
        ->and($tree->remove('zz')->ids())->toBe(['a', 'b', 'c', 'd'])
        ->and($tree->ids())->toBe(['a', 'b', 'c', 'd']);
});

test('positionOf reports where a block sits', function () {
    $tree = new BlockTree(block('a', 'acme/box', [], block('b'), block('c')), block('d'));

    expect($tree->positionOf('d'))->toEqual(Position::root(1))
        ->and($tree->positionOf('a'))->toEqual(Position::root(0))
        ->and($tree->positionOf('c'))->toEqual(Position::inside('a', 1))
        ->and($tree->positionOf('zz'))->toBeNull();
});
