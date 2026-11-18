<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media;

/**
 * The icons the default outputs can draw: a small set of line drawings made for this package.
 *
 * They are drawn in the text's colour on a 24-unit grid, so they sit in a line of text and follow the shared style
 * settings. A plugin with its own icon set loads its own outputs package; the stored icon names stay the same.
 *
 * @since 0.1.0
 */
class Icons
{
    /**
     * The shapes of each icon, as the inside of an SVG.
     */
    private const array SHAPES = [
        'check' => '<path d="M4 12.5l5 5L20 6.5"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'star' => '<path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8L3.5 9.7l5.9-.8z"/>',
        'heart' => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
        'arrow-right' => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M20 12H5M11 6l-6 6 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'info' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.6v.4"/>',
        'alert' => '<path d="M12 4L21 19.5H3z"/><path d="M12 10v4.5M12 17v.4"/>',
        'phone' => '<path d="M6.5 3.5h3l1.5 4-2 1.2a10 10 0 0 0 6.3 6.3l1.2-2 4 1.5v3a2 2 0 0 1-2.2 2'
            . 'A16.5 16.5 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2z"/>',
        'mail' => '<rect x="3.5" y="5.5" width="17" height="13" rx="1.5"/><path d="M4 7l8 6 8-6"/>',
        'map-pin' => '<path d="M12 21s-6.5-6.2-6.5-11.2a6.5 6.5 0 0 1 13 0C18.5 14.8 12 21 12 21z"/>'
            . '<circle cx="12" cy="9.8" r="2.3"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'calendar' => '<rect x="4" y="5.5" width="16" height="14.5" rx="1.5"/>'
            . '<path d="M4 10h16M8.5 3.5v4M15.5 3.5v4"/>',
        'user' => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
    ];

    /**
     * Every name the set can draw.
     */
    public const array NAMES = [
        'check', 'x', 'star', 'heart', 'arrow-right', 'arrow-left', 'plus', 'minus',
        'info', 'alert', 'phone', 'mail', 'map-pin', 'clock', 'calendar', 'user',
    ];

    /**
     * Draws one icon.
     *
     * @return string|null
     *   The SVG, or null when the set has no icon of that name.
     *
     * @since 0.1.0
     */
    public static function svg(string $name): ?string
    {
        $shape = self::SHAPES[$name] ?? null;

        return $shape === null ? null : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em"'
            . ' height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"'
            . ' stroke-linejoin="round" focusable="false">' . $shape . '</svg>';
    }
}
