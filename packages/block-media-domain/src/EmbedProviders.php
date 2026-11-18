<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

/**
 * The only sites whose content a page may embed, and how each one's links become embed addresses.
 *
 * The list is fixed so an embed can never frame an arbitrary page. A link is checked twice, by the settings schema
 * through {@link EmbedProviders::PATTERN} and again here, because stored settings may not have passed the editor.
 *
 * @since 0.1.0
 */
class EmbedProviders
{
    /**
     * The links the embed block accepts, as a settings schema pattern; empty is allowed so a new block can be saved.
     */
    public const string PATTERN = '^$|^https://(www\\.)?youtube\\.com/watch\\?v=[A-Za-z0-9_-]{11}(&[^#\\s]*)?$'
        . '|^https://www\\.youtube\\.com/shorts/[A-Za-z0-9_-]{11}$'
        . '|^https://youtu\\.be/[A-Za-z0-9_-]{11}(\\?[^#\\s]*)?$'
        . '|^https://vimeo\\.com/[0-9]{1,12}$';

    private const string VIDEO_ID = '/^[A-Za-z0-9_-]{11}$/';

    /**
     * Turns a link into what to embed.
     *
     * @return Embed|null
     *   The embed, or null when the link is not from an allowed provider in a form this list knows.
     *
     * @since 0.1.0
     */
    public static function embedFor(string $url): ?Embed
    {
        if (preg_match('/[\s\x00-\x1F]/', $url) === 1)
        {
            return null;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']))
        {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $youtube = match (true) {
            in_array($host, ['www.youtube.com', 'youtube.com'], true) && $path === '/watch' => $query['v'] ?? null,
            $host === 'www.youtube.com' && str_starts_with($path, '/shorts/') => substr($path, 8),
            $host === 'youtu.be' => substr($path, 1),
            default => null,
        };

        if (is_string($youtube) && preg_match(self::VIDEO_ID, $youtube) === 1)
        {
            return new Embed(
                'youtube',
                'https://www.youtube-nocookie.com/embed/' . $youtube,
                'https://www.youtube.com/watch?v=' . $youtube,
            );
        }

        if ($host === 'vimeo.com' && preg_match('#^/([0-9]{1,12})$#', $path, $match) === 1)
        {
            return new Embed('vimeo', 'https://player.vimeo.com/video/' . $match[1], 'https://vimeo.com/' . $match[1]);
        }

        return null;
    }

    /**
     * Builds the OpenStreetMap embed around a point, at a zoom from 1 (the world) to 19 (a street).
     *
     * @since 0.1.0
     */
    public static function mapFor(float $latitude, float $longitude, int $zoom): Embed
    {
        $span = 360 / 2 ** $zoom;
        $bbox = implode(',', array_map(self::number(...), [
            $longitude - $span,
            $latitude - $span / 2,
            $longitude + $span,
            $latitude + $span / 2,
        ]));
        $lat = self::number($latitude);
        $lon = self::number($longitude);

        return new Embed(
            'openstreetmap',
            "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker={$lat},{$lon}",
            "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lon}#map={$zoom}/{$lat}/{$lon}",
        );
    }

    /**
     * Writes a coordinate with at most six decimals, about a tenth of a metre, and no trailing zeros.
     */
    private static function number(float $value): string
    {
        $text = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
