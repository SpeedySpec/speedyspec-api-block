<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One page of a media search.
 *
 * @since 0.1.0
 */
class MediaPage
{
    /**
     * @param list<MediaItem> $items
     *   The page's items, newest first.
     * @param int $total
     *   How many items match across every page, so a builder knows whether there is more.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    )
    {
    }
}
