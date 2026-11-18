<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Example\Listing;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Adds the number of items to a list's view, which only the block itself can know.
 *
 * @since 0.1.0
 */
class ListBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        return new BlockView([
            'ordered' => (bool) ($settings['ordered'] ?? false),
            'label' => (string) ($settings['label'] ?? ''),
            'count' => count($block->children),
        ]);
    }
}
