<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One file in the host's media library, as blocks and builders see it.
 *
 * Blocks store only the id; everything else is looked up when the block is drawn, so moving or replacing files does
 * not break pages.
 *
 * @since 0.1.0
 */
class MediaItem
{
    /**
     * @param string $id
     *   The library's id for the item.
     * @param string $url
     *   Where the whole file is served.
     * @param string $title
     *   A name for people choosing media, such as the uploaded file's name.
     * @param string $alt
     *   The library's description of an image for people who cannot see it; empty when it has none.
     * @param int|null $width
     *   In pixels, where the library knows it.
     * @param int|null $height
     *   In pixels, where the library knows it.
     * @param list<MediaSize> $sizes
     *   Smaller copies of an image the host made.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly MediaKind $kind,
        public readonly string $mimeType,
        public readonly string $title,
        public readonly string $alt = '',
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly array $sizes = [],
    )
    {
    }

    /**
     * Finds one of the item's sizes by the host's name for it.
     *
     * @since 0.1.0
     */
    public function size(string $name): ?MediaSize
    {
        foreach ($this->sizes as $size)
        {
            if ($size->name === $name)
            {
                return $size;
            }
        }

        return null;
    }

    /**
     * Describes the item for a builder.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'kind' => $this->kind->value,
            'mimeType' => $this->mimeType,
            'title' => $this->title,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'sizes' => array_map(static fn (MediaSize $size): array => $size->toArray(), $this->sizes),
        ];
    }
}
