<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Text\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Gives a heading the id a link or a table of contents points at.
 *
 * The id is worked out here, in the definition, so every output of a heading, and the table of contents, agree on it.
 *
 * @since 0.1.0
 */
class HeadingBehaviour implements BlockBehaviourInterface
{
    private const int MAX_LENGTH = 64;

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $anchor = is_string($settings['anchor'] ?? null) ? $settings['anchor'] : '';

        return new BlockView([
            'level' => is_int($settings['level'] ?? null) ? $settings['level'] : 2,
            'text' => is_string($settings['text'] ?? null) ? $settings['text'] : '',
            'anchor' => $anchor,
            'id' => $anchor !== '' ? $anchor : self::idFor((string) ($settings['text'] ?? '')),
        ]);
    }

    /**
     * Derives an id from a heading's text: the words, lower-cased and joined by hyphens.
     *
     * Only `a-z` and digits survive, so the id is safe in any attribute and the same in every output.
     *
     * @since 0.1.0
     */
    public static function idFor(string $text): string
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
}
