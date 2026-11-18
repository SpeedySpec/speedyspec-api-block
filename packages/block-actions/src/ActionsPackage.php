<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Actions;

use SpeedySpec\Block\Actions\Domain\ActionsTypesPackage;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\ValueObjects\Asset;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Draws the actions blocks that {@link ActionsTypesPackage} defines, with one stylesheet for all of them.
 *
 * It defines no block types. A plugin with its own buttons, or brand logos for social links, loads its own outputs
 * package instead, and pages keep their settings.
 *
 * @since 0.1.0
 */
class ActionsPackage implements BlockPackageInterface
{
    public const string STYLESHEET = 'core/actions';

    private const array BLOCKS = [
        'button', 'buttons', 'call-to-action', 'testimonial', 'pricing-table', 'pricing-plan', 'rating',
        'social-links', 'social-link',
    ];

    /**
     * @param StyleCollectorInterface $styles
     *   Receives the pricing table's column rules.
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
        return 'core/actions';
    }

    public function title(): string
    {
        return 'Actions, drawn';
    }

    public function description(): string
    {
        return 'The standard way to draw buttons, calls to action, testimonials, pricing, ratings and social links.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $registrar->asset(Asset::inline(self::STYLESHEET, AssetKind::Style, ActionsStylesheet::CSS));

        foreach (self::BLOCKS as $block)
        {
            $registrar->output(BlockName::fromString('core/' . $block), new ActionsOutput($block, $this->styles));
        }
    }
}
