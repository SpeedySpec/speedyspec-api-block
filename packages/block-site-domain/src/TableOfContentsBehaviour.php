<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Site\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Lists the page's headings between two levels, from what {@link SitePageStep} found.
 *
 * @since 0.1.0
 */
class TableOfContentsBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $from = is_int($settings['from'] ?? null) ? $settings['from'] : 2;
        $to = is_int($settings['to'] ?? null) ? $settings['to'] : 3;
        [$low, $high] = $from <= $to ? [$from, $to] : [$to, $from];
        $headings = $context->has(SitePageStep::HEADINGS) ? $context->get(SitePageStep::HEADINGS) : [];

        return new BlockView([
            'title' => is_string($settings['title'] ?? null) ? $settings['title'] : '',
            'items' => array_values(array_filter(
                is_array($headings) ? $headings : [],
                static fn (mixed $heading): bool => is_array($heading)
                    && ($heading['level'] ?? 0) >= $low
                    && ($heading['level'] ?? 0) <= $high,
            )),
        ]);
    }
}
