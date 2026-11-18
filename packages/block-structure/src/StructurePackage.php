<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Structure;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;
use SpeedySpec\Block\Structure\Domain\StructureTypesPackage;

/**
 * Draws the layout blocks that {@link StructureTypesPackage} defines.
 *
 * It defines no block types. A plugin that draws the layout blocks its own way loads its own outputs package after
 * the definitions instead of, or after, this one.
 *
 * @since 0.1.0
 */
class StructurePackage implements BlockPackageInterface
{
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

    public function title(): string
    {
        return 'Layout, drawn';
    }

    public function description(): string
    {
        return 'The standard way to draw sections, columns and grids.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $registrar->output(
            BlockName::fromString('core/section'),
            new ElementOutput(StructureTypesPackage::SECTION_TAGS),
        );
        $registrar->output(
            BlockName::fromString('core/element'),
            new ElementOutput(StructureTypesPackage::ELEMENT_TAGS),
        );
        $registrar->output(
            BlockName::fromString('core/columns'),
            new LayoutOutput($this->styles, fn (array $values): array => Layout::columns($values, array_map(
                static fn (Breakpoint $breakpoint): string => $breakpoint->name,
                $this->breakpoints->all(),
            ))),
        );
        $registrar->output(
            BlockName::fromString('core/column'),
            new LayoutOutput($this->styles, Layout::column(...)),
        );
        $registrar->output(BlockName::fromString('core/grid'), new LayoutOutput($this->styles, Layout::grid(...)));
    }
}
