<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Decides whether a block is shown, such as visibility rules, before any data source is asked for a value.
 *
 * @since 0.1.0
 */
interface GateStepInterface
{
    /**
     * Tells whether the block should be shown.
     *
     * On the front end a hidden block is dropped; in the admin it is kept and marked as hidden on the front end.
     *
     * @param Block $block
     *   The block carrying its prepared settings and extensions.
     * @param BlockType $type
     *   The block's registered type.
     * @param Context $context
     *   The context inherited from ancestors.
     * @param Mode $mode
     *   Where the block is resolved.
     *
     * @since 0.1.0
     */
    public function shown(Block $block, BlockType $type, Context $context, Mode $mode): bool;
}
