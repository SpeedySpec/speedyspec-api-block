<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Text\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\HeadingAnchor;
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
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $anchor = is_string($settings['anchor'] ?? null) ? $settings['anchor'] : '';

        return new BlockView([
            'level' => is_int($settings['level'] ?? null) ? $settings['level'] : 2,
            'text' => is_string($settings['text'] ?? null) ? $settings['text'] : '',
            'anchor' => $anchor,
            'id' => $this->id($block, $anchor, (string) ($settings['text'] ?? ''), $context),
        ]);
    }

    /**
     * Takes the id the page gave this heading, when a package that sees the whole page made ids unique, and otherwise
     * its anchor or one made from its text.
     */
    private function id(Block $block, string $anchor, string $text, Context $context): string
    {
        $ids = $context->has('core/heading-ids') ? $context->get('core/heading-ids') : null;
        $given = is_array($ids) ? ($ids[$block->id] ?? null) : null;

        if (is_string($given) && $given !== '')
        {
            return $given;
        }

        return $anchor !== '' ? $anchor : HeadingAnchor::fromText($text);
    }

    /**
     * Derives an id from a heading's text: the words, lower-cased and joined by hyphens.
     *
     * Kept for callers of part 2; the rules are {@link HeadingAnchor::fromText()}'s, shared with anything that
     * links to headings.
     *
     * @since 0.1.0
     */
    public static function idFor(string $text): string
    {
        return HeadingAnchor::fromText($text);
    }
}
