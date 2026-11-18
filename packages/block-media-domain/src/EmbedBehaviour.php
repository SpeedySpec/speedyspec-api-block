<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Exceptions\UnavailableBlockException;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Turns an embed block's link into what to frame, through {@link EmbedProviders} only.
 *
 * @since 0.1.0
 */
class EmbedBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $url = is_string($settings['url'] ?? null) ? $settings['url'] : '';
        $embed = $url === '' ? null : EmbedProviders::embedFor($url);

        // Without a link the block is not set up yet; a stored link the list refuses is broken.
        if ($embed === null && $url !== '')
        {
            throw new UnavailableBlockException(
                IssueKind::UnavailableMedia,
                'Only YouTube and Vimeo links can be embedded.',
                'url',
            );
        }

        return new BlockView([
            'provider' => $embed?->provider ?? '',
            'src' => $embed?->src ?? '',
            'link' => $embed?->link ?? '',
            'title' => is_string($settings['title'] ?? null) ? $settings['title'] : 'Embedded content',
            'caption' => is_string($settings['caption'] ?? null) ? $settings['caption'] : '',
        ]);
    }
}
