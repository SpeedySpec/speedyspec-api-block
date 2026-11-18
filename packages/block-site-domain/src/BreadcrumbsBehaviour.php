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
 * Turns the host's trail for the page into breadcrumbs, the page itself last and never a link.
 *
 * A very long trail keeps its first step and its last nineteen, so it stays short enough to read.
 *
 * @since 0.1.0
 */
class BreadcrumbsBehaviour implements BlockBehaviourInterface
{
    private const int MAX_CRUMBS = 20;

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $trail = $context->has(SitePageStep::BREADCRUMBS) ? $context->get(SitePageStep::BREADCRUMBS) : [];
        $crumbs = array_values(array_filter(is_array($trail) ? $trail : [], 'is_array'));

        if (count($crumbs) > self::MAX_CRUMBS)
        {
            $crumbs = [$crumbs[0], ...array_slice($crumbs, -(self::MAX_CRUMBS - 1))];
        }

        $last = count($crumbs) - 1;

        return new BlockView([
            'label' => is_string($settings['label'] ?? null) ? $settings['label'] : '',
            'crumbs' => array_map(static fn (array $crumb, int $at): array => [
                'label' => is_string($crumb['label'] ?? null) ? $crumb['label'] : '',
                'url' => $at === $last || !is_string($crumb['url'] ?? null) ? '' : $crumb['url'],
                'current' => $at === $last,
            ], $crumbs, array_keys($crumbs)),
        ]);
    }
}
