<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Entities;

use SpeedySpec\Block\Domain\Exceptions\DuplicateBlockIdException;
use SpeedySpec\Block\Domain\Exceptions\InvalidPositionException;
use SpeedySpec\Block\Domain\ValueObjects\Position;

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
    /**
     * Builds a new tree with a block placed at a position.
     *
     * @throws InvalidPositionException
     *   When the position's parent is not in the tree, or its index is past the end of the siblings.
     * @throws DuplicateBlockIdException
     *   When an id in the placed block or its descendants is already in the tree.
     *
     * @since 0.1.0
     */
    public function insert(Block $block, Position $position): self
    {
        if ($position->parentId === null)
        {
            return new self(...$this->spliced($this->roots, $block, $position->index, 'the document root'));
        }

        $parent = $this->find($position->parentId);

        if ($parent === null)
        {
            throw new InvalidPositionException(sprintf('No block "%s" to place a block inside.', $position->parentId));
        }

        $children = $this->spliced($parent->children, $block, $position->index, sprintf('block "%s"', $parent->id));

        return $this->replace($parent->id, $parent->withChildren(...$children));
    }

    /**
     * Builds a new tree without the identified block and its descendants.
     *
     * An unknown id yields an equal tree.
     *
     * @since 0.1.0
     */
    public function remove(string $id): self
    {
        $prune = function (array $blocks) use (&$prune, $id): array {
            $kept = [];

            foreach ($blocks as $block)
            {
                if ($block->id !== $id)
                {
                    $kept[] = $block->withChildren(...$prune($block->children));
                }
            }

            return $kept;
        };

        return new self(...$prune($this->roots));
    }

    /**
     * Tells where a block sits now, so it can be put back or have something placed beside it.
     *
     * @return Position|null
     *   Null when the tree has no block with that id.
     *
     * @since 0.1.0
     */
    public function positionOf(string $id): ?Position
    {
        foreach ($this->roots as $index => $root)
        {
            if ($root->id === $id)
            {
                return Position::root($index);
            }
        }

        $parent = $this->parentOf($id);

        if ($parent === null)
        {
            return null;
        }

        foreach ($parent->children as $index => $child)
        {
            if ($child->id === $id)
            {
                return Position::inside($parent->id, $index);
            }
        }

        return null;
    }

    /**
     * Places a block among its new siblings.
     *
     * @param list<Block> $siblings
     * @param string $where
     *   Names the place for the error message.
     *
     * @return list<Block>
     *
     * @throws InvalidPositionException
     *   When the index is past the end of the siblings.
     *
     * @since 0.1.0
     */
    private function spliced(array $siblings, Block $block, int $index, string $where): array
    {
        if ($index > count($siblings))
        {
            throw new InvalidPositionException(sprintf(
                'Index %d is past the end of the %d blocks in %s.',
                $index,
                count($siblings),
                $where,
            ));
        }

        array_splice($siblings, $index, 0, [$block]);

        return $siblings;
    }
}
