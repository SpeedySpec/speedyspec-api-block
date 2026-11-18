<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Ids;

use RuntimeException;
use SpeedySpec\Block\Domain\Contracts\BlockDuplicatorInterface;
use SpeedySpec\Block\Domain\Contracts\BlockIdGeneratorInterface;
use SpeedySpec\Block\Domain\Entities\Block;

/**
 * Copies a block subtree under fresh ids, retrying on a clash so a weak generator cannot produce a tree
 * that {@link \SpeedySpec\Block\Domain\Entities\BlockTree} would reject.
 *
 * @since 0.1.0
 */
class BlockDuplicator implements BlockDuplicatorInterface
{
    private const int MAX_ATTEMPTS = 100;

    /**
     * Creates a duplicator.
     *
     * @param BlockIdGeneratorInterface $ids
     *   Supplies new ids.
     */
    public function __construct(private readonly BlockIdGeneratorInterface $ids)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function duplicate(Block $block, array $reservedIds = []): Block
    {
        $used = array_fill_keys($reservedIds, true);
        $this->collect($block, $used);

        return $this->copy($block, $used);
    }

    /**
     * Records every id of a subtree as taken.
     *
     * @param array<string, true> $used
     *   The taken ids, extended in place.
     */
    private function collect(Block $block, array &$used): void
    {
        $used[$block->id] = true;

        foreach ($block->children as $child)
        {
            $this->collect($child, $used);
        }
    }

    /**
     * Copies a block and its descendants under unused ids.
     *
     * @param array<string, true> $used
     *   The taken ids, extended in place.
     *
     * @throws RuntimeException
     *   When the generator keeps returning taken ids.
     */
    private function copy(Block $block, array &$used): Block
    {
        $id = $this->freshId($used);
        $children = [];

        foreach ($block->children as $child)
        {
            $children[] = $this->copy($child, $used);
        }

        return $block->withId($id)->withChildren(...$children);
    }

    /**
     * Draws an id the generator has not produced before and the copy may use.
     *
     * @param array<string, true> $used
     *   The taken ids, extended in place.
     *
     * @throws RuntimeException
     *   When the attempt limit is exceeded.
     */
    private function freshId(array &$used): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++)
        {
            $id = $this->ids->generate();

            if (!isset($used[$id]))
            {
                $used[$id] = true;

                return $id;
            }
        }

        throw new RuntimeException(sprintf(
            'The block id generator failed to produce an unused id in %d attempts.',
            self::MAX_ATTEMPTS,
        ));
    }
}
