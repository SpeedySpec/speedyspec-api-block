<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Exceptions\InvalidPositionException;
use SpeedySpec\Block\Domain\ValueObjects\Position;

test('a position names the root or a parent, and an index', function () {
    expect(Position::root(3)->parentId)->toBeNull()
        ->and(Position::root(3)->index)->toBe(3)
        ->and(Position::inside('a', 0)->parentId)->toBe('a');
});

test('a position rejects a negative index and an empty parent id', function () {
    expect(fn () => Position::root(-1))->toThrow(InvalidPositionException::class)
        ->and(fn () => Position::inside('a', -1))->toThrow(InvalidPositionException::class)
        ->and(fn () => Position::inside('', 0))->toThrow(InvalidPositionException::class);
});
