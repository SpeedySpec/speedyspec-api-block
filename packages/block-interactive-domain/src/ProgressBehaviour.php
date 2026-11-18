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
 * Keeps a progress bar's value within its maximum, which the schema cannot compare, and gives it as a percentage.
 *
 * @since 0.1.0
 */
class ProgressBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $number = static fn (mixed $value, int|float $default): int|float => is_int($value) || is_float($value)
            ? $value
            : $default;
        $max = max(1, $number($settings['max'] ?? null, 100));
        $value = min($max, max(0, $number($settings['value'] ?? null, 0)));

        return new BlockView([
            'label' => is_string($settings['label'] ?? null) ? $settings['label'] : '',
            'value' => $value,
            'max' => $max,
            'percent' => (int) round($value / $max * 100),
        ]);
    }
}
