<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Media\Domain\MediaTypesPackage;

/**
 * Draws the media blocks that {@link MediaTypesPackage} defines.
 *
 * It defines no block types. A plugin that draws media its own way, or with its own icon set, loads its own outputs
 * package after the definitions instead of this one, and pages keep their settings.
 *
 * @since 0.1.0
 */
class MediaPackage implements BlockPackageInterface
{
    private const array BLOCKS = [
        'image', 'gallery', 'video', 'audio', 'embed', 'icon', 'icon-list', 'icon-list-item', 'map',
    ];

    /**
     * @param StyleCollectorInterface $styles
     *   Receives the gallery's column rules.
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
        return 'core/media';
    }

    public function title(): string
    {
        return 'Media, drawn';
    }

    public function description(): string
    {
        return 'The standard way to draw images, galleries, video, audio, embeds, icons and maps.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        foreach (self::BLOCKS as $block)
        {
            $registrar->output(BlockName::fromString('core/' . $block), new MediaOutput($block, $this->styles));
        }
    }
}
