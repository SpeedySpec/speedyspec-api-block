<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\MediaItem;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Looks up an image block's file when the page is drawn, so the block stores only the id.
 *
 * Every size the library has is offered to the browser, so it can load the smallest that fits.
 *
 * @since 0.1.0
 */
class ImageBehaviour implements BlockBehaviourInterface
{
    public function __construct(private readonly MediaLookup $media)
    {
    }

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $item = $this->media->chosen($settings['media'] ?? null, MediaKind::Image);
        $name = is_string($settings['size'] ?? null) ? $settings['size'] : '';
        $size = $name !== '' ? $item?->size($name) : null;

        return new BlockView([
            'url' => $size?->url ?? $item?->url ?? '',
            'width' => $size?->width ?? $item?->width,
            'height' => $size?->height ?? $item?->height,
            'alt' => is_string($settings['alt'] ?? null) ? $settings['alt'] : ($item?->alt ?? ''),
            'srcset' => $item === null ? '' : self::srcset($item),
            'caption' => is_string($settings['caption'] ?? null) ? $settings['caption'] : '',
            'link' => is_string($settings['link'] ?? null) ? $settings['link'] : '',
        ]);
    }

    /**
     * Lists an image's sizes and whole file by width, as a `srcset`; files of unknown width are left out.
     *
     * @since 0.1.0
     */
    public static function srcset(MediaItem $item): string
    {
        $widths = [];

        foreach ($item->sizes as $size)
        {
            if ($size->width > 0)
            {
                $widths[$size->url] = $size->width;
            }
        }

        if ($item->width !== null && $item->width > 0)
        {
            $widths[$item->url] = $item->width;
        }

        asort($widths);

        return implode(', ', array_map(
            static fn (string $url, int $width): string => "{$url} {$width}w",
            array_keys($widths),
            $widths,
        ));
    }
}
