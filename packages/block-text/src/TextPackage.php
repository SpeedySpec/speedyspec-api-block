<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Text;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Draws the text blocks that {@link \SpeedySpec\Block\Text\Domain\TextTypesPackage} defines.
 *
 * It defines no block types. A plugin that draws the text blocks its own way loads its own outputs package after the
 * definitions, instead of this one or after it.
 *
 * @since 0.1.0
 */
class TextPackage implements BlockPackageInterface
{
    private const array BLOCKS = [
        'heading', 'paragraph', 'list', 'list-item', 'quote', 'code', 'table', 'separator', 'spacer',
    ];

    /**
     * @param StyleCollectorInterface $styles
     *   Receives the spacer's height rules.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly StyleCollectorInterface $styles)
    {
    }

    /**
     * Builds the package around the style collector bound in a container, so the host reads the same one.
     *
     * @since 0.1.0
     */
    public static function from(BlockServiceContainer $container): self
    {
        return new self($container->get(StyleCollectorInterface::class));
    }

    public function name(): string
    {
        return 'core/text';
    }

    public function title(): string
    {
        return 'Text, drawn';
    }

    public function description(): string
    {
        return 'The standard way to draw headings, paragraphs, lists, quotes, code and tables.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        foreach (self::BLOCKS as $block)
        {
            $registrar->output(BlockName::fromString('core/' . $block), new TextOutput($block, $this->styles));
        }
    }
}
