<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;

/**
 * Draws a stand-in for a block that could not be resolved, so a builder's canvas has something to select.
 *
 * It is only asked in admin mode; a visitor never sees a block that produced no view.
 *
 * @since 0.1.0
 */
interface UnresolvedBlockOutputInterface
{
    /**
     * Draws the stand-in for one block.
     *
     * @param ResolvedBlock $block
     *   A block with no view; its type is null when the type is not registered.
     * @param OutputEscaperInterface $escaper
     *   The escaper for the request's target; every value drawn must go through it.
     *
     * @since 0.1.0
     */
    public function render(ResolvedBlock $block, RenderRequest $request, OutputEscaperInterface $escaper): string;
}
