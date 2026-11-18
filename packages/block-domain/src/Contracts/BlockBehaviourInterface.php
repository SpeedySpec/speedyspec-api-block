<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Computes a block's view from its resolved settings, keeping runtime logic out of the block type declaration.
 *
 * @since 0.1.0
 */
interface BlockBehaviourInterface
{
    /**
     * Builds the view for one block.
     *
     * @param BlockType $type
     *   The type being viewed.
     * @param array<string, mixed> $settings
     *   The block's resolved settings.
     * @param Context $context
     *   The context inherited from ancestors.
     * @param Mode $mode
     *   Where the view is produced.
     *
     * @since 0.1.0
     */
    public function view(BlockType $type, array $settings, Context $context, Mode $mode): BlockView;
}
