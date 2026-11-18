<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Presentation;

use SpeedySpec\Block\Domain\Contracts\PrepareStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Drops style settings that do not fit the vocabulary before a block is checked.
 *
 * The editor refuses such values, so they can only be in content that was imported or stored before a format was
 * tightened. Without this, one of them would fail the block's check and remove the block, and everything inside
 * it, from the page.
 *
 * @since 0.1.0
 */
class StyleSanitizer implements PrepareStepInterface
{
    public function prepare(Block $block, BlockType $type, Context $context, Mode $mode): Block
    {
        $stored = $block->extensions[StyleVocabulary::NAMESPACE] ?? null;

        if (!is_array($stored))
        {
            return $block;
        }

        return $block->withExtension(StyleVocabulary::NAMESPACE, StyleVocabulary::clean($stored));
    }
}
