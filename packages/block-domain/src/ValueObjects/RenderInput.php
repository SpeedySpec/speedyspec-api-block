<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Contracts\OutputEscaperInterface;

/**
 * Everything a block's output is given to draw one block.
 *
 * An output never sees stored settings: it draws the view, applies the attribute bag to its root element, places
 * the already-drawn children, and writes every value through the escaper.
 *
 * @since 0.1.0
 */
class RenderInput
{
    /**
     * Gathers the inputs into one object so the output contract can grow without changing its signature.
     *
     * @param list<string> $childPasses
     *   The drawn children, one string per pass; a block that does not repeat its children has one pass.
     * @param Context $context
     *   The context the block received from its ancestors.
     * @param AdminContext|null $admin
     *   Extra information for the person editing; always null when drawing for a visitor.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $blockId,
        public readonly BlockType $type,
        public readonly BlockView $view,
        public readonly AttributeBag $attributes,
        public readonly array $childPasses,
        public readonly Context $context,
        public readonly RenderRequest $request,
        public readonly OutputEscaperInterface $escaper,
        public readonly ?AdminContext $admin = null,
    )
    {
    }

    /**
     * Joins every child pass, for the common case of an output that places its children in one spot.
     *
     * @since 0.1.0
     */
    public function children(): string
    {
        return implode('', $this->childPasses);
    }
}
