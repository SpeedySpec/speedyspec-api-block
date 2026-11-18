<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One block of a document, drawn on its own.
 *
 * @since 0.1.0
 */
class BlockPreview
{
    /**
     * @param ResolvedBlock|null $block
     *   Null when the block is in the document but resolving never reached it, because an ancestor stopped or
     *   the block is misplaced.
     * @param list<Issue> $issues
     *   Issues of the block and its descendants, from resolving and from drawing.
     * @param list<string> $assets
     *   Names of the assets the drawn blocks need.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $blockId,
        public readonly ?ResolvedBlock $block,
        public readonly string $output,
        public readonly array $issues,
        public readonly array $assets,
    )
    {
    }
}
