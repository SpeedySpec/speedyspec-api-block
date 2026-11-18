<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Computes a block's view from its resolved settings, keeping runtime logic out of the block type declaration.
 *
 * The block and settings a behaviour receives are server-side and complete: they include private settings in both
 * modes, so a form can read its notification recipient. A behaviour must not copy a private setting into a view it
 * returns in {@link Mode::Front}, because its view is not filtered the way the default view is.
 *
 * @since 0.1.0
 */
interface BlockBehaviourInterface
{
    /**
     * Builds the view for one block.
     *
     * @param Block $block
     *   The working block: the stored block carrying the final settings and extensions, so the behaviour can read
     *   its id, children, extensions and bindings.
     * @param BlockType $type
     *   The type being viewed.
     * @param array<string, mixed> $settings
     *   The block's resolved settings, private ones included.
     * @param Context $context
     *   The context inherited from ancestors.
     * @param Mode $mode
     *   Where the view is produced.
     *
     * @since 0.1.0
     */
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView;
}
