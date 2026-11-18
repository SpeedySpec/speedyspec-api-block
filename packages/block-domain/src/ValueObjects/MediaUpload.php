<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * A file a person uploaded, already checked, on its way into the media library.
 *
 * It checks nothing itself: the host's upload rules run before one is made, so a library can trust its type.
 *
 * @since 0.1.0
 */
class MediaUpload
{
    /**
     * @param string $name
     *   The name the file was uploaded with; a library keeps it only as a title.
     * @param string $mimeType
     *   The type found in the file's content.
     * @param string $bytes
     *   The file.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $name,
        public readonly string $mimeType,
        public readonly string $bytes,
    )
    {
    }

    /**
     * @since 0.1.0
     */
    public function kind(): MediaKind
    {
        return MediaKind::fromMimeType($this->mimeType);
    }
}
