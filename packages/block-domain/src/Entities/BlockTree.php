<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Entities;

use SpeedySpec\Block\Domain\Exceptions\DuplicateBlockIdException;

/**
 * A forest of blocks with unique ids, so any block can be addressed by id wherever it sits.
 *
 * @since 0.1.0
 */
class BlockTree
{
    /** @var list<Block> */
    private readonly array $roots;

    /**
     * @throws DuplicateBlockIdException
     *   When an id repeats anywhere in the tree.
     */
    public function __construct(Block ...$roots)
    {
        $this->roots = array_values($roots);

        $seen = [];

        foreach ($this->walk() as $block)
        {
            if (isset($seen[$block->id]))
            {
                throw new DuplicateBlockIdException(sprintf('Block id "%s" appears more than once.', $block->id));
            }

            $seen[$block->id] = true;
        }
    }

    /**
     * Lists the top-level blocks.
     *
     * @return list<Block>
     *
     * @since 0.1.0
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * Finds a block by id at any depth.
     *
     * @since 0.1.0
     */
    public function find(string $id): ?Block
    {
        foreach ($this->walk() as $block)
        {
            if ($block->id === $id)
            {
                return $block;
            }
        }

        return null;
    }

    /**
     * Finds the block that directly contains the given id, or null for a root or an unknown id.
     *
     * @since 0.1.0
     */
    public function parentOf(string $id): ?Block
    {
        foreach ($this->walk() as $block)
        {
            foreach ($block->children as $child)
            {
                if ($child->id === $id)
                {
                    return $block;
                }
            }
        }

        return null;
    }

    /**
     * Lists every block depth-first, parents before their children.
     *
     * @return list<Block>
     *
     * @since 0.1.0
     */
    public function walk(): array
    {
        $blocks = [];
        $visit = function (Block $block) use (&$visit, &$blocks): void {
            $blocks[] = $block;

            foreach ($block->children as $child)
            {
                $visit($child);
            }
        };

        foreach ($this->roots as $root)
        {
            $visit($root);
        }

        return $blocks;
    }

    /**
     * Lists every block id in walk order.
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    public function ids(): array
    {
        return array_map(fn (Block $b): string => $b->id, $this->walk());
    }

    /**
     * Builds a new tree with the identified block swapped for another, keeping its position.
     *
     * An unknown id yields an equal tree.
     *
     * @throws DuplicateBlockIdException
     *   When the replacement makes an id repeat.
     *
     * @since 0.1.0
     */
    public function replace(string $id, Block $block): self
    {
        $swap = function (Block $current) use (&$swap, $id, $block): Block {
            if ($current->id === $id)
            {
                return $block;
            }

            return $current->withChildren(...array_map($swap, $current->children));
        };

        return new self(...array_map($swap, $this->roots));
    }
}
