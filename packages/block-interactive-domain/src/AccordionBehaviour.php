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
 * Names an accordion's group when only one item may be open at a time, so its items share it.
 *
 * Items with the same name are one group to the browser, which closes the others as one opens, without a script.
 *
 * @since 0.1.0
 */
class AccordionBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $single = ($settings['single'] ?? false) === true;

        return new BlockView([
            'single' => $single,
            'group' => $single ? ElementIds::for($block->id, 'group') : '',
        ]);
    }
}
