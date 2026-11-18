<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Presentation;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\RenderStepInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;

/**
 * Binds the builder API, and an access rule that refuses everything unless the host has bound its own.
 *
 * Register it after the application provider: it adds the step that names blocks in an editor's drawing to the
 * step registry that provider binds.
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

        // Added as the step registry is first built, so the provider itself resolves nothing.
        if ($container->has(StepRegistryInterface::class))
        {
            $container->extend(StepRegistryInterface::class, function (object $steps): object {
                $steps->add(RenderStepInterface::class, new BuilderAnnotations(), 100);

                return $steps;
            });
        }

        if (!$container->has(BuilderAccessInterface::class))
        {
            $container->add(BuilderAccessInterface::class, fn () => new DenyAll());
        }
    }
}
