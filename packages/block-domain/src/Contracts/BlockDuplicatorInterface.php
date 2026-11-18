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
     * @since 0.1.0
     */
    public function duplicate(Block $block): Block;
}
