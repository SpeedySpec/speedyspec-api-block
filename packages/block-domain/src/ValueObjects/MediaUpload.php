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
     * The file extension for each accepted type.
     */
    public const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'application/pdf' => 'pdf',
    ];

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

    /**
     * Gives the extension the file's real type has, so a library never stores it under the one it was named with.
     *
     * @since 0.1.0
     */
    public function extension(): string
    {
        return self::EXTENSIONS[$this->mimeType] ?? 'bin';
    }

    /**
     * Names the file for a library that keeps people's names: the uploaded name without any path or extension, and
     * the extension of the type found in the content.
     *
     * @since 0.1.0
     */
    public function storedName(): string
    {
        $parts = preg_split('#[/\\\\]#', $this->name) ?: [''];
        $base = (string) end($parts);
        $dot = strrpos($base, '.');
        $base = trim($dot === false ? $base : substr($base, 0, $dot));

        return ($base === '' ? 'upload' : $base) . '.' . $this->extension();
    }
}
