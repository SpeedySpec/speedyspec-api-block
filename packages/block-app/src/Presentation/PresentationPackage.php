<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Presentation;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\PrepareStepInterface;
use SpeedySpec\Block\Domain\Contracts\RenderStepInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;

/**
 * The block package that gives every block type the shared style settings.
 *
 * It adds no block types: only the `core/style` decorator, a step that drops stored values that do not fit it,
 * and the step that turns its settings into classes and collected rules. A host that styles blocks another way
 * does not load it.
 *
 * @since 0.1.0
 */
class PresentationPackage implements BlockPackageInterface
{
    /**
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
        return 'core/presentation';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $registrar->decorator(StyleVocabulary::decorator());
        $registrar->step(PrepareStepInterface::class, new StyleSanitizer());
        $registrar->step(RenderStepInterface::class, new StyleStep($this->styles));
    }
}
