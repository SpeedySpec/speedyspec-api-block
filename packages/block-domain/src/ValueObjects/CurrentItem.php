<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * The item a repeating block is currently producing children for, so descendants can bind to it.
 *
 * @since 0.1.0
 */
class CurrentItem
{
    /**
     * @param mixed $item
     *   The item itself.
     * @param int $index
     *   Its zero-based position in the collection.
     * @param string $collectionId
     *   Identifies the collection, so nested repeaters can be told apart.
     */
    public function __construct(
        public readonly mixed $item,
        public readonly int $index,
        public readonly string $collectionId,
    )
    {
    }
}
