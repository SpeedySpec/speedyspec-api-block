<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Actions\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Looks up a testimonial's photo, which it can do without: the words matter, so a missing photo is simply left out.
 *
 * @since 0.1.0
 */
class TestimonialBehaviour implements BlockBehaviourInterface
{
    /**
     * @param MediaLibraryInterface|null $library
     *   The host's media, or null on a host with none.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly ?MediaLibraryInterface $library)
    {
    }

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $id = is_string($settings['media'] ?? null) ? $settings['media'] : '';
        $item = $id === '' || $this->library === null ? null : $this->library->find($id);
        $text = fn (string $key): string => is_string($settings[$key] ?? null) ? $settings[$key] : '';

        return new BlockView([
            'quote' => $text('quote'),
            'name' => $text('name'),
            'role' => $text('role'),
            'photo' => $item === null || $item->kind !== MediaKind::Image
                ? null
                : ['url' => $item->url, 'alt' => $item->alt, 'width' => $item->width, 'height' => $item->height],
        ]);
    }
}
