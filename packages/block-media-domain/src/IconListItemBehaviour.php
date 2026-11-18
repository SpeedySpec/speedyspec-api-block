<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Gives an icon list item its list's icon unless it has one of its own, so a list's icons are set in one place.
 *
 * @since 0.1.0
 */
class IconListItemBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $own = is_string($settings['icon'] ?? null) ? $settings['icon'] : '';
        $inherited = $context->has('core/icon') ? $context->get('core/icon') : '';
        $inherited = is_string($inherited) ? $inherited : '';

        return new BlockView([
            'icon' => $own !== '' ? $own : ($inherited !== '' ? $inherited : 'check'),
            'text' => is_string($settings['text'] ?? null) ? $settings['text'] : '',
        ]);
    }
}
