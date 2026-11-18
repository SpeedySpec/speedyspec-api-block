<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Entities\Document;

/**
 * A whole document after resolving, with every issue found along the way in tree order.
 *
 * Like its blocks, a resolved document is server-side and complete: its blocks' settings include private settings
 * in both modes. Only the default view and the client export are safe to hand to a visitor.
 *
 * @since 0.1.0
 */
class ResolvedDocument
{
    /**
     * Records the outcome for one document.
     *
     * @param Document $document
     *   The stored document that was resolved.
     * @param Mode $mode
     *   Where it was resolved.
     * @param list<ResolvedBlock> $roots
     *   The resolved root blocks; misplaced roots are left out.
     * @param list<Issue> $issues
     *   Every issue, depth-first: a block's own issues before its children's, pass by pass.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly Document $document,
        public readonly Mode $mode,
        public readonly array $roots,
        public readonly array $issues,
    )
    {
    }

    /**
     * Finds the first resolved block with the given id, searching depth-first through first passes only.
     *
     * @since 0.1.0
     */
    public function find(string $id): ?ResolvedBlock
    {
        $pending = $this->roots;

        while ($pending !== [])
        {
            $block = array_shift($pending);

            if ($block->block->id === $id)
            {
                return $block;
            }

            array_unshift($pending, ...$block->children());
        }

        return null;
    }

    /**
     * Lists the issues recorded against one block, so an editor can show them beside it.
     *
     * @return list<Issue>
     *
     * @since 0.1.0
     */
    public function issuesFor(string $id): array
    {
        return array_values(array_filter($this->issues, static fn (Issue $issue): bool => $issue->blockId === $id));
    }

    /**
     * Tells whether anything went wrong anywhere in the document.
     *
     * @since 0.1.0
     */
    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }
}
