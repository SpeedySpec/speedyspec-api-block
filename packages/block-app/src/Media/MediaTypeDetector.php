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
     * The brands of ISO media files that are MP4 video. The same container also holds HEIC photos, M4A sound and
     * QuickTime films, which are not accepted kinds, so only these brands count.
     */
    private const array MP4_BRANDS = ['isom', 'iso2', 'iso4', 'iso5', 'iso6', 'mp41', 'mp42', 'avc1', 'dash', 'M4V '];

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
        $brands = $brand === null ? [] : $this->compatibleBrands($bytes);

        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($bytes, 'GIF87a'), str_starts_with($bytes, 'GIF89a') => 'image/gif',
            $riff === 'WEBP' => 'image/webp',
            $riff === 'WAVE' => 'audio/wav',
            $brand === 'avif', $brand === 'avis' => 'image/avif',
            in_array($brand, ['mif1', 'msf1'], true) && array_intersect(['avif', 'avis'], $brands) !== []
                => 'image/avif',
            in_array($brand, self::MP4_BRANDS, true) => 'video/mp4',
            str_starts_with($bytes, "\x1A\x45\xDF\xA3") => 'video/webm',
            str_starts_with($bytes, 'ID3') => 'audio/mpeg',
            strlen($bytes) >= 2 && $bytes[0] === "\xFF" && (ord($bytes[1]) & 0xE0) === 0xE0 => 'audio/mpeg',
            str_starts_with($bytes, 'OggS') => 'audio/ogg',
            str_starts_with($bytes, '%PDF-') => 'application/pdf',
            default => null,
        };
    }

    /**
     * Lists the brands an ISO media file says it is also compatible with, after its major brand and version.
     *
     * @return list<string>
     */
    private function compatibleBrands(string $bytes): array
    {
        $size = strlen($bytes) >= 4 ? unpack('N', substr($bytes, 0, 4))[1] : 0;
        $end = min(max($size, 16), strlen($bytes), 256);

        return str_split(substr($bytes, 16, max(0, $end - 16)), 4);
    }
}
