<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Entities\Block;

/**
 * One block after resolving: its final settings, its view when it has one, and its resolved children.
 *
 * A resolved block is server-side and complete in both modes: `$settings` and `$block` include private settings
 * even in {@link Mode::Front}. Only the default view and the client export are safe to hand to a visitor.
 *
 * @since 0.1.0
 */
class ResolvedBlock
{
    /**
     * Records the outcome for one block.
     *
     * @param Block $block
     *   The prepared block carrying its final settings, or the stored block when it stopped before its view.
     * @param BlockType|null $type
     *   The block's registered type, or null when the type is unknown.
     * @param array<string, mixed> $settings
     *   The final settings, or whatever the last stage reached produced; private settings included.
     * @param array<string, array<string, mixed>> $extensions
     *   The final extension settings by decorator namespace.
     * @param BlockView|null $view
     *   The view, or null when the block stopped before one was built; its editor information is always null.
     * @param Context $context
     *   The context the block received from its parent.
     * @param AdminContext|null $admin
     *   The editor information in {@link Mode::Admin}, present even for a block that stopped before its view so
     *   the editor can show why; null in {@link Mode::Front}.
     * @param list<list<ResolvedBlock>> $passes
     *   The children, resolved once per pass with that pass's context.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly Block $block,
        public readonly ?BlockType $type,
        public readonly array $settings,
        public readonly array $extensions,
        public readonly ?BlockView $view,
        public readonly Context $context,
        public readonly ?AdminContext $admin = null,
        public readonly array $passes = [],
    )
    {
    }

    /**
     * Tells whether the block reached a view, so callers know whether it can be drawn.
     *
     * @since 0.1.0
     */
    public function isResolved(): bool
    {
        return $this->view !== null;
    }

    /**
     * Lists the children of the first pass, which is all of them for a block that is not repeating.
     *
     * @return list<ResolvedBlock>
     *
     * @since 0.1.0
     */
    public function children(): array
    {
        return $this->passes[0] ?? [];
    }
}
