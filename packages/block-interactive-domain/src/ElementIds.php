<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Interactive\Domain;

/**
 * Makes element ids for interactive blocks from their block ids.
 *
 * Block ids are unique in a document, so ids made from them never clash, even with two sets of tabs or two sliders
 * on one page. Anything that could not sit in an id is written as `_` and its byte in hex, `_` itself included, so
 * two different block ids can never give the same element id.
 *
 * @since 0.1.0
 */
class ElementIds
{
    /**
     * @param string $suffix
     *   What the element is, such as `panel`, so one block can name several elements.
     *
     * @since 0.1.0
     */
    public static function for(string $blockId, string $suffix): string
    {
        $encoded = (string) preg_replace_callback(
            '/[^A-Za-z0-9-]/',
            static fn (array $match): string => '_' . bin2hex($match[0]),
            $blockId,
        );

        return 'b-' . $encoded . '-' . $suffix;
    }
}
