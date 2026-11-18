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
 * Writes a counter's final value as text, which the page shows until a script counts up to it, and always to
 * screen readers.
 *
 * @since 0.1.0
 */
class CounterBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $number = static fn (string $key, int|float $default): int|float => is_int($settings[$key] ?? null)
            || is_float($settings[$key] ?? null) ? $settings[$key] : $default;
        $text = static fn (string $key): string => is_string($settings[$key] ?? null) ? $settings[$key] : '';
        $decimals = (int) max(0, min(3, $number('decimals', 0)));
        $to = $number('to', 100);

        return new BlockView([
            'from' => $number('from', 0),
            'to' => $to,
            'prefix' => $text('prefix'),
            'suffix' => $text('suffix'),
            'duration' => (int) max(0, min(10000, $number('duration', 2000))),
            'decimals' => $decimals,
            'text' => $text('prefix') . number_format((float) $to, $decimals) . $text('suffix'),
        ]);
    }
}
