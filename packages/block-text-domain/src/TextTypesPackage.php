<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Text\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

/**
 * Defines the blocks text is written with: headings, paragraphs, lists, quotes, code, tables, separators and spacers.
 *
 * Only their names, settings and catalogue entries, and the heading's id: how they are drawn is another package's,
 * so a plugin can draw them its own way and every page made with them still opens. The settings are fixed; changing
 * one needs a migration.
 *
 * @since 0.1.0
 */
class TextTypesPackage implements BlockPackageInterface
{
    /**
     * A length a spacer's height may be, matching the shared style vocabulary's lengths.
     */
    public const string LENGTH = '^(0|[0-9]+(\\.[0-9]+)?(px|rem|em|vh)|token:[a-z][a-z0-9-]*(\\.[a-z0-9-]+)*)$';

    public function name(): string
    {
        return 'core/text-types';
    }

    public function title(): string
    {
        return 'Text';
    }

    public function description(): string
    {
        return 'Headings, paragraphs, lists, quotes, code and tables.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $rich = fn (string $title): array => [
            'type' => 'string',
            'default' => '',
            'x-control' => 'rich-text',
            'title' => $title,
        ];
        $type = fn (array $definition) => $registrar->type(
            BlockType::fromArray(['category' => 'text', ...$definition]),
        );

        $type([
            'name' => 'core/heading',
            'title' => 'Heading',
            'description' => 'A title for a part of the page.',
            'keywords' => ['title', 'subtitle', 'h1', 'h2'],
            'settings' => ['type' => 'object', 'properties' => [
                'level' => ['type' => 'integer', 'default' => 2, 'minimum' => 1, 'maximum' => 6, 'title' => 'Level'],
                'text' => $rich('Text'),
                'anchor' => [
                    'type' => 'string',
                    'default' => '',
                    'pattern' => '^([a-z][a-z0-9-]{0,63})?$',
                    'title' => 'Anchor',
                    'description' => 'The name a link uses to jump here. Empty: made from the text.',
                ],
            ]],
            'examples' => [['level' => 2, 'text' => 'A heading']],
        ]);
        $registrar->behaviour(BlockName::fromString('core/heading'), new HeadingBehaviour());

        $type([
            'name' => 'core/paragraph',
            'title' => 'Paragraph',
            'description' => 'A block of text.',
            'keywords' => ['text', 'p'],
            'settings' => ['type' => 'object', 'properties' => ['text' => $rich('Text')]],
            'examples' => [['text' => 'Some <strong>text</strong>.']],
        ]);

        $type([
            'name' => 'core/list',
            'title' => 'List',
            'description' => 'A bulleted or numbered list.',
            'keywords' => ['bullets', 'numbers', 'ul', 'ol'],
            'settings' => ['type' => 'object', 'properties' => [
                'ordered' => ['type' => 'boolean', 'default' => false, 'title' => 'Numbered'],
                'start' => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'title' => 'Start at'],
            ]],
            'children' => ['core/list-item'],
            'examples' => [['ordered' => false]],
        ]);

        $type([
            'name' => 'core/list-item',
            'title' => 'List item',
            'description' => 'One entry in a list, which may hold a list of its own.',
            'settings' => ['type' => 'object', 'properties' => ['text' => $rich('Text')]],
            'children' => ['core/list'],
            'parents' => ['core/list'],
            'examples' => [['text' => 'First']],
        ]);

        $type([
            'name' => 'core/quote',
            'title' => 'Quote',
            'description' => 'Words from someone else, with who said them.',
            'keywords' => ['blockquote', 'citation'],
            'settings' => ['type' => 'object', 'properties' => [
                'text' => $rich('Quote'),
                'citation' => ['type' => 'string', 'default' => '', 'title' => 'Who said it'],
            ]],
            'examples' => [['text' => 'Less, but better.', 'citation' => 'Dieter Rams']],
        ]);

        $type([
            'name' => 'core/code',
            'title' => 'Code',
            'description' => 'Code shown as it is written.',
            'keywords' => ['pre', 'snippet', 'source'],
            'settings' => ['type' => 'object', 'properties' => [
                'code' => ['type' => 'string', 'default' => '', 'x-control' => 'code', 'title' => 'Code'],
                'language' => [
                    'type' => 'string',
                    'default' => '',
                    'pattern' => '^([a-z0-9+#-]{0,32})$',
                    'title' => 'Language',
                    'description' => 'Such as php or js, for highlighting. Optional.',
                ],
            ]],
            'examples' => [['code' => 'echo "Hello";', 'language' => 'php']],
        ]);

        $type([
            'name' => 'core/table',
            'title' => 'Table',
            'description' => 'Rows and columns of text.',
            'keywords' => ['grid', 'data', 'rows'],
            'settings' => ['type' => 'object', 'properties' => [
                'header' => ['type' => 'boolean', 'default' => true, 'title' => 'First row is a header'],
                'rows' => [
                    'type' => 'array',
                    'default' => [['', ''], ['', '']],
                    'items' => ['type' => 'array', 'items' => ['type' => 'string', 'x-control' => 'rich-text']],
                    'x-control' => 'table',
                    'title' => 'Rows',
                ],
                'caption' => $rich('Caption'),
            ]],
            'examples' => [['rows' => [['Name', 'Price'], ['Tea', '3']], 'caption' => 'Prices']],
        ]);

        $type([
            'name' => 'core/separator',
            'title' => 'Separator',
            'description' => 'A line between parts of the page.',
            'keywords' => ['divider', 'hr', 'line'],
            'examples' => [[]],
        ]);

        $type([
            'name' => 'core/spacer',
            'title' => 'Spacer',
            'description' => 'Empty space between blocks.',
            'keywords' => ['gap', 'space'],
            'settings' => ['type' => 'object', 'properties' => [
                'height' => [
                    'type' => ['string', 'object'],
                    'default' => '2rem',
                    'pattern' => self::LENGTH,
                    'additionalProperties' => ['type' => 'string', 'pattern' => self::LENGTH],
                    'x-control' => 'responsive',
                    'title' => 'Height',
                ],
            ]],
            'examples' => [['height' => '2rem']],
        ]);
    }
}
