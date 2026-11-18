<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;

/**
 * Adds to a block's attribute bag before the block is drawn.
 *
 * This is how a feature that applies to many block types (animation, custom attributes, visibility hints) changes
 * output: through classes, attributes and styles, never by editing markup.
 *
 * @since 0.1.0
 */
interface RenderStepInterface
{
    /**
     * Builds the bag to pass on, usually the given bag with entries added.
     *
     * @param AttributeBag $bag
     *   The bag as earlier steps left it.
     *
     * @since 0.1.0
     */
    public function attributes(AttributeBag $bag, ResolvedBlock $block, RenderRequest $request): AttributeBag;
}
