<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Media;

/**
 * Tells what a file is from its first bytes, for the kinds the block system accepts.
 *
 * A file's name and the type a browser declares are only claims; the content decides. The signatures are written
 * out rather than taken from `fileinfo`, because this list is the upload policy: any type not here is refused, and
 * SVG and HTML, which can carry script, are never here.
 *
 * @since 0.1.0
 */
class MediaTypeDetector
{
    /**
     * Reads the type from a file's content.
     *
     * @return string|null
     *   The MIME type, or null when the file is none of the accepted kinds.
     *
     * @since 0.1.0
     */
    public function detect(string $bytes): ?string
    {
        $riff = str_starts_with($bytes, 'RIFF') ? substr($bytes, 8, 4) : null;
        $brand = substr($bytes, 4, 4) === 'ftyp' ? substr($bytes, 8, 4) : null;

        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($bytes, 'GIF87a'), str_starts_with($bytes, 'GIF89a') => 'image/gif',
            $riff === 'WEBP' => 'image/webp',
            $riff === 'WAVE' => 'audio/wav',
            $brand === 'avif', $brand === 'avis' => 'image/avif',
            $brand !== null && strlen($brand) === 4 => 'video/mp4',
            str_starts_with($bytes, "\x1A\x45\xDF\xA3") => 'video/webm',
            str_starts_with($bytes, 'ID3') => 'audio/mpeg',
            strlen($bytes) >= 2 && $bytes[0] === "\xFF" && (ord($bytes[1]) & 0xE0) === 0xE0 => 'audio/mpeg',
            str_starts_with($bytes, 'OggS') => 'audio/ogg',
            str_starts_with($bytes, '%PDF-') => 'application/pdf',
            default => null,
        };
    }
}
