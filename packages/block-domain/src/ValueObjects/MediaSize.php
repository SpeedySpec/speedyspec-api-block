<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One smaller copy of an image that the host made, so pages can offer browsers a choice of widths.
 *
 * @since 0.1.0
 */
class MediaSize
{
    /**
     * @param string $name
     *   The host's name for the size, such as `thumbnail`.
     * @param string $url
     *   Where the copy is served.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly int $width,
        public readonly int $height,
    )
    {
    }

    /**
     * Describes the size for a builder.
     *
     * @return array{name: string, url: string, width: int, height: int}
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'url' => $this->url, 'width' => $this->width, 'height' => $this->height];
    }
}
