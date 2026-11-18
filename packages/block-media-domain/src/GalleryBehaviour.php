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
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Looks up a gallery's images when the page is drawn.
 *
 * An image that has gone is left out rather than losing the whole gallery; only a gallery with nothing left to
 * show is unavailable.
 *
 * @since 0.1.0
 */
class GalleryBehaviour implements BlockBehaviourInterface
{
    public function __construct(private readonly MediaLookup $media)
    {
    }

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $ids = is_array($settings['media'] ?? null) ? $settings['media'] : [];
        $items = [];

        foreach ($ids as $id)
        {
            $item = $this->media->optional($id, MediaKind::Image);

            if ($item !== null)
            {
                $items[] = [
                    'url' => $item->url,
                    'width' => $item->width,
                    'height' => $item->height,
                    'alt' => $item->alt,
                ];
            }
        }

        // An empty gallery is not set up yet; one whose every image has gone is broken.
        if ($items === [] && $ids !== [])
        {
            throw new UnavailableBlockException(
                IssueKind::UnavailableMedia,
                'None of the chosen images exist any more.',
                'media',
            );
        }

        return new BlockView([
            'items' => $items,
            'columns' => $settings['columns'] ?? 3,
            'caption' => is_string($settings['caption'] ?? null) ? $settings['caption'] : '',
        ]);
    }
}
