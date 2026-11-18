<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Actions\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Keeps a rating within its scale, to the nearest half, and says it in words.
 *
 * The schema cannot compare the value with the scale, so a value above it is brought down here. The words are what
 * a page shows to everyone; stars are only decoration beside them.
 *
 * @since 0.1.0
 */
class RatingBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $outOf = is_int($settings['outOf'] ?? null) ? max(1, $settings['outOf']) : 5;
        $stored = $settings['value'] ?? 0;
        $value = round(min($outOf, max(0, is_int($stored) || is_float($stored) ? (float) $stored : 0.0)) * 2) / 2;
        $shown = floor($value) === $value ? (int) $value : $value;
        $label = is_string($settings['label'] ?? null) ? trim($settings['label']) : '';
        $words = sprintf('rated %s out of %d', (string) $shown, $outOf);

        return new BlockView([
            'value' => $shown,
            'outOf' => $outOf,
            'label' => $label,
            'text' => $label === '' ? ucfirst($words) : $label . ': ' . $words,
        ]);
    }
}
