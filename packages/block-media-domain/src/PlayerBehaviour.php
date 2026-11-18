<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Looks up a video or audio block's file when the page is drawn.
 *
 * Anything that plays by itself is muted, because browsers refuse sound nobody asked for and visitors do too.
 *
 * @since 0.1.0
 */
class PlayerBehaviour implements BlockBehaviourInterface
{
    /**
     * @param MediaKind $kind
     *   Video or audio.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly MediaLookup $media,
        private readonly MediaKind $kind,
    )
    {
    }

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $item = $this->media->chosen($settings['media'] ?? null, $this->kind);
        $poster = $this->kind === MediaKind::Video
            ? $this->media->optional($settings['poster'] ?? null, MediaKind::Image)
            : null;
        $autoplay = ($settings['autoplay'] ?? false) === true;

        return new BlockView([
            'kind' => $this->kind->value,
            'url' => $item?->url ?? '',
            'mimeType' => $item?->mimeType ?? '',
            'poster' => $poster?->url ?? '',
            'controls' => ($settings['controls'] ?? true) === true,
            'autoplay' => $autoplay,
            'muted' => $autoplay,
            'loop' => ($settings['loop'] ?? false) === true,
            'caption' => is_string($settings['caption'] ?? null) ? $settings['caption'] : '',
        ]);
    }
}
