<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Structure;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;

/**
 * The blocks pages are laid out with: section, columns, column, grid and element.
 *
 * They hold no content of their own beyond an element's text; they exist so that every block package and every
 * builder arranges things with the same five blocks instead of each shipping its own container.
 *
 * @since 0.1.0
 */
class StructurePackage implements BlockPackageInterface
{
    public const array SECTION_TAGS = ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav'];

    public const array ELEMENT_TAGS = [
        'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'figure',
        'figcaption', 'strong', 'em', 'small',
    ];

    /**
     * @param StyleCollectorInterface $styles
     *   Receives the layout rules of the columns and grid blocks.
     * @param BreakpointRegistryInterface $breakpoints
     *   Tells the columns block which breakpoints the host has.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly StyleCollectorInterface $styles,
        private readonly BreakpointRegistryInterface $breakpoints,
    )
    {
    }

    /**
     * Builds the package around the style collector bound in a container, so the host reads the same one.
     *
     * @since 0.1.0
     */
    public static function from(BlockServiceContainer $container): self
    {
        return new self(
            $container->get(StyleCollectorInterface::class),
            $container->get(BreakpointRegistryInterface::class),
        );
    }

    public function name(): string
    {
        return 'core/structure';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $count = fn (string $title): array => [
            'type' => ['integer', 'object'],
            'default' => 1,
            'minimum' => 1,
            'maximum' => 12,
            'additionalProperties' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
            'title' => $title,
            'x-control' => 'responsive',
        ];

        $registrar->type(BlockType::fromArray([
            'name' => 'core/section',
            'title' => 'Section',
            'description' => 'A container for a part of the page.',
            'category' => 'layout',
            'keywords' => ['container', 'group', 'wrapper'],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'tag' => ['enum' => self::SECTION_TAGS, 'default' => 'div', 'title' => 'Element'],
                    'label' => [
                        'type' => 'string',
                        'default' => '',
                        'title' => 'Accessible label',
                        'description' => 'Read by screen readers to name this part of the page.',
                    ],
                ],
            ],
            'children' => true,
            'examples' => [['tag' => 'section', 'label' => 'Introduction']],
        ]));
        $registrar->output(BlockName::fromString('core/section'), new ElementOutput(self::SECTION_TAGS));

        $registrar->type(BlockType::fromArray([
            'name' => 'core/element',
            'title' => 'Element',
            'description' => 'One element, such as a heading or a paragraph, with text and blocks inside it.',
            'category' => 'layout',
            'keywords' => ['heading', 'paragraph', 'text', 'tag'],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'tag' => ['enum' => self::ELEMENT_TAGS, 'default' => 'div', 'title' => 'Element'],
                    'text' => ['type' => 'string', 'default' => '', 'title' => 'Text'],
                ],
            ],
            'children' => true,
            'examples' => [['tag' => 'h2', 'text' => 'A heading']],
        ]));
        $registrar->output(BlockName::fromString('core/element'), new ElementOutput(self::ELEMENT_TAGS));

        $registrar->type(BlockType::fromArray([
            'name' => 'core/columns',
            'title' => 'Columns',
            'description' => 'Columns that sit side by side on wide screens and stack on narrow ones.',
            'category' => 'layout',
            'keywords' => ['row', 'layout'],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'stackBelow' => [
                        'type' => 'string',
                        'default' => 'tablet',
                        'pattern' => '^([a-z][a-z0-9-]{0,31})?$',
                        'title' => 'Side by side from',
                        'description' => 'The breakpoint from which columns sit in a row. Empty: always in a row.',
                        'x-control' => 'breakpoint',
                    ],
                    'align' => ['enum' => Layout::ALIGNMENTS, 'default' => 'stretch', 'title' => 'Vertical alignment'],
                ],
            ],
            'children' => ['core/column'],
            'examples' => [[]],
        ]));
        $registrar->output(
            BlockName::fromString('core/columns'),
            new LayoutOutput($this->styles, fn (array $values): array => Layout::columns($values, array_map(
                static fn (Breakpoint $breakpoint): string => $breakpoint->name,
                $this->breakpoints->all(),
            ))),
        );

        $registrar->type(BlockType::fromArray([
            'name' => 'core/column',
            'title' => 'Column',
            'description' => 'One column, taking a share of the row.',
            'category' => 'layout',
            'settings' => ['type' => 'object', 'properties' => ['span' => $count('Share of the row')]],
            'children' => true,
            'parents' => ['core/columns'],
            'examples' => [['span' => 2]],
        ]));
        $registrar->output(BlockName::fromString('core/column'), new LayoutOutput($this->styles, Layout::column(...)));

        $registrar->type(BlockType::fromArray([
            'name' => 'core/grid',
            'title' => 'Grid',
            'description' => 'A grid of equal columns; the blocks inside fill it row by row.',
            'category' => 'layout',
            'keywords' => ['gallery', 'cards'],
            'settings' => ['type' => 'object', 'properties' => ['columns' => $count('Columns')]],
            'children' => true,
            'examples' => [['columns' => ['base' => 1, 'tablet' => 2, 'desktop' => 3]]],
        ]));
        $registrar->output(BlockName::fromString('core/grid'), new LayoutOutput($this->styles, Layout::grid(...)));
    }
}
