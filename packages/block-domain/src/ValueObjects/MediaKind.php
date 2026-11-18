<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * What a media item is, so a block can ask the library for only what it can show.
 *
 * @since 0.1.0
 */
enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';

    /**
     * Anything that is not shown inline, such as a PDF.
     */
    case File = 'file';

    /**
     * Tells the kind from a MIME type's top-level part.
     *
     * @since 0.1.0
     */
    public static function fromMimeType(string $mimeType): self
    {
        return match (strtolower(strstr($mimeType, '/', true) ?: $mimeType)) {
            'image' => self::Image,
            'video' => self::Video,
            'audio' => self::Audio,
            default => self::File,
        };
    }
}
