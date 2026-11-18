<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Presentation;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;

/**
 * Binds the builder API, and an access rule that refuses everything unless the host has bound its own.
 *
 * @since 0.1.0
 */
class PresentationServiceProvider
{
    /**
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $container->add(BuilderApi::class, fn (BlockServiceContainer $c) => new BuilderApi($c));

        if (!$container->has(BuilderAccessInterface::class))
        {
            $container->add(BuilderAccessInterface::class, fn () => new DenyAll());
        }
    }
}
