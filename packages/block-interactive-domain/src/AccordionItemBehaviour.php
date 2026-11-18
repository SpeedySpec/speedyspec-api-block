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
 * Gives an accordion item its accordion's group, which only a behaviour can read from the context.
 *
 * @since 0.1.0
 */
class AccordionItemBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $group = $context->has('core/accordion-group') ? $context->get('core/accordion-group') : '';

        return new BlockView([
            'title' => is_string($settings['title'] ?? null) ? $settings['title'] : '',
            'open' => ($settings['open'] ?? false) === true,
            'group' => is_string($group) ? $group : '',
        ]);
    }
}
