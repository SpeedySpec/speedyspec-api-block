<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Interactive\Domain;

/**
 * Makes element ids for interactive blocks from their block ids.
 *
 * Block ids are unique in a document, so ids made from them never clash, even with two sets of tabs or two sliders
 * on one page; anything that could not sit in an id is replaced.
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
        return 'b-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $blockId) . '-' . $suffix;
    }
}
