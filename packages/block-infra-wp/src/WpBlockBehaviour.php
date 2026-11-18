<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Gives a WordPress block's output everything the block stored, its saved markup included.
 *
 * The saved markup is a private setting so that it can never be bound to a data source. It is not a secret: it is
 * what the visitor is shown. The default view would leave it out with the other private settings, so this
 * behaviour puts it in.
 *
 * @since 0.1.0
 */
class WpBlockBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        return new BlockView($settings);
    }
}
