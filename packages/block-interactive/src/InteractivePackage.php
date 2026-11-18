<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Interactive;

use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\ValueObjects\Asset;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Interactive\Domain\InteractiveTypesPackage;

/**
 * Draws the interactive blocks that {@link InteractiveTypesPackage} defines, with a stylesheet and a small script.
 *
 * The script is a file in this package, so the host decides where it is served through its asset locator. Pages
 * work without it.
 *
 * @since 0.1.0
 */
class InteractivePackage implements BlockPackageInterface
{
    public const string PACKAGE = 'speedyspec/block-interactive';

    public const string STYLESHEET = 'core/interactive';

    public const string SCRIPT = 'core/interactive-script';

    private const array BLOCKS = [
        'accordion', 'accordion-item', 'tabs', 'tab', 'slider', 'slide', 'countdown', 'progress', 'counter', 'modal',
    ];

    public function name(): string
    {
        return 'core/interactive';
    }

    public function title(): string
    {
        return 'Interactive, drawn';
    }

    public function description(): string
    {
        return 'The standard way to draw accordions, tabs, sliders, countdowns, progress bars, counters and modals.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $registrar->asset(Asset::inline(self::STYLESHEET, AssetKind::Style, InteractiveStylesheet::CSS));
        $registrar->asset(Asset::file(self::SCRIPT, AssetKind::Module, self::PACKAGE, 'assets/interactive.js'));

        foreach (self::BLOCKS as $block)
        {
            $registrar->output(BlockName::fromString('core/' . $block), new InteractiveOutput($block));
        }
    }
}
