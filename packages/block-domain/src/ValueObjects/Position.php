<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidPositionException;

/**
 * A place in a tree: among the root blocks, or among one block's children, at an index.
 *
 * @since 0.1.0
 */
class Position
{
    /**
     * Keeps the constructor private so a position is always built through a method that says what it means.
     *
     * @since 0.1.0
     */
    private function __construct(
        public readonly ?string $parentId,
        public readonly int $index,
    )
    {
    }

    /**
     * Names a place among the document's root blocks.
     *
     * @throws InvalidPositionException
     *   When the index is negative.
     *
     * @since 0.1.0
     */
    public static function root(int $index): self
    {
        return self::at(null, $index);
    }

    /**
     * Names a place among the children of a block.
     *
     * @throws InvalidPositionException
     *   When the parent id is empty or the index is negative.
     *
     * @since 0.1.0
     */
    public static function inside(string $parentId, int $index): self
    {
        if ($parentId === '')
        {
            throw new InvalidPositionException('A position inside a block needs that block\'s id.');
        }

        return self::at($parentId, $index);
    }

    /**
     * Builds the position once the index is known to be usable.
     *
     * @since 0.1.0
     */
    private static function at(?string $parentId, int $index): self
    {
        if ($index < 0)
        {
            throw new InvalidPositionException(sprintf('A position index cannot be negative; %d given.', $index));
        }

        return new self($parentId, $index);
    }
}
