<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * The rules for a heading's anchor: made from its words, and unique on the page.
 *
 * Kept with the contracts so the heading that draws an id and anything that links to it, such as a table of
 * contents, always agree.
 *
 * @since 0.1.0
 */
class HeadingAnchor
{
    private const int MAX_LENGTH = 64;

    /**
     * Makes an anchor from a heading's text: the words, lower-cased and joined by hyphens.
     *
     * Only `a-z` and digits survive, so the anchor is safe in any attribute and the same in every output; a heading
     * with none of them is `heading`.
     *
     * @since 0.1.0
     */
    public static function fromText(string $text): string
    {
        $plain = strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $id = trim((string) preg_replace('/[^a-z0-9]+/', '-', $plain), '-');

        if ($id !== '' && ctype_digit($id[0]))
        {
            $id = 'h-' . $id;
        }

        $id = rtrim(substr($id, 0, self::MAX_LENGTH), '-');

        return $id === '' ? 'heading' : $id;
    }

    /**
     * Makes a page's anchors unique, in page order: the first use of each keeps it, and each repeat is numbered
     * from 2, skipping numbers another heading wants for itself.
     *
     * @param list<string> $wanted
     * @param list<string> $used
     *   Ids already used elsewhere on the page, such as by the page around an included document.
     * @return list<string>
     *
     * @since 0.1.0
     */
    public static function unique(array $wanted, array $used = []): array
    {
        $reserved = array_fill_keys($wanted, true);
        $taken = array_fill_keys($used, true);
        $ids = [];

        foreach ($wanted as $id)
        {
            if (!isset($taken[$id]))
            {
                $taken[$id] = true;
                $ids[] = $id;

                continue;
            }

            $number = 2;

            while (isset($reserved[$id . '-' . $number]) || isset($taken[$id . '-' . $number]))
            {
                $number++;
            }

            $taken[$id . '-' . $number] = true;
            $ids[] = $id . '-' . $number;
        }

        return $ids;
    }
}
