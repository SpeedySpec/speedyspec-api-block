<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Interactive\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Gives a block's view an element id, for the blocks whose parts point at each other, such as a tab and its panel.
 *
 * @since 0.1.0
 */
class IdBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        return new BlockView([...$settings, 'id' => ElementIds::for($block->id, 'x')]);
    }
}
