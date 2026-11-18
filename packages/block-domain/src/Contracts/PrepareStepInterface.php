<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Adjusts a block's settings or extensions before defaults are applied, such as filling values from the host.
 *
 * @since 0.1.0
 */
interface PrepareStepInterface
{
    /**
     * Gives back the block with changed settings or extensions.
     *
     * Only the returned settings and extensions are kept; a different id, type or child list is ignored so a step
     * cannot reshape the tree.
     *
     * @param Block $block
     *   The block with migrated settings and any earlier steps' changes.
     * @param BlockType $type
     *   The block's registered type.
     * @param Context $context
     *   The context inherited from ancestors.
     * @param Mode $mode
     *   Where the block is resolved.
     *
     * @since 0.1.0
     */
    public function prepare(Block $block, BlockType $type, Context $context, Mode $mode): Block;
}
