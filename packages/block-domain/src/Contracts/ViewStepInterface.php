<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Adds decorator values to a block's view, such as visibility classes, without touching what the block itself exposes.
 *
 * @since 0.1.0
 */
interface ViewStepInterface
{
    /**
     * Extends one block's view.
     *
     * Only the returned view's `extensions` are kept, merged over the current ones per namespace; its `values`,
     * `admin` and `childPasses` are ignored so a step cannot change the block's own view.
     *
     * @param BlockView $view
     *   The view built so far.
     * @param Block $block
     *   The block carrying its final settings and extensions.
     * @param BlockType $type
     *   The block's registered type.
     * @param array<string, array<string, mixed>> $extensions
     *   The block's final extension settings by decorator namespace.
     * @param Context $context
     *   The context the block received.
     * @param Mode $mode
     *   Where the block is resolved.
     *
     * @since 0.1.0
     */
    public function extend(
        BlockView $view,
        Block $block,
        BlockType $type,
        array $extensions,
        Context $context,
        Mode $mode,
    ): BlockView;
}
