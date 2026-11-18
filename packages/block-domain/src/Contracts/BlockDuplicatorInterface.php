<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Block;

/**
 * Copies a block subtree so it can be inserted into a document without clashing ids.
 *
 * @since 0.1.0
 */
interface BlockDuplicatorInterface
{
    /**
     * Deep-copies a block, giving it and every descendant a newly generated id and changing nothing else.
     *
     * No new id equals an id in the original subtree or in `$reservedIds`.
     *
     * @param Block $block
     *   The block to copy.
     * @param list<string> $reservedIds
     *   Ids the copy must also avoid, such as every id of the document the copy will be inserted into.
     *
     * @throws \RuntimeException
     *   When the id generator cannot produce an unused id.
     *
     * @since 0.1.0
     */
    public function duplicate(Block $block, array $reservedIds = []): Block;
}
