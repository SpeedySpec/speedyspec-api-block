<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Ids;

use SpeedySpec\Block\Domain\Contracts\BlockDuplicatorInterface;
use SpeedySpec\Block\Domain\Contracts\BlockIdGeneratorInterface;
use SpeedySpec\Block\Domain\Entities\Block;

/**
 * Copies a block subtree under fresh ids, regenerating on a clash so a weak generator cannot produce a tree
 * that {@link \SpeedySpec\Block\Domain\Entities\BlockTree} would reject.
 *
 * @since 0.1.0
 */
class BlockDuplicator implements BlockDuplicatorInterface
{
    public function __construct(private readonly BlockIdGeneratorInterface $ids)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function duplicate(Block $block): Block
    {
        $used = [];

        return $this->copy($block, $used);
    }

    /**
     * @param array<string, true> $used
     *   Ids already handed out within this copy.
     */
    private function copy(Block $block, array &$used): Block
    {
        do
        {
            $id = $this->ids->generate();
        }
        while (isset($used[$id]));

        $used[$id] = true;

        $children = [];

        foreach ($block->children as $child)
        {
            $children[] = $this->copy($child, $used);
        }

        return $block->withId($id)->withChildren(...$children);
    }
}
