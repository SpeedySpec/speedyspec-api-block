<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;

/**
 * Binds the in-memory implementations so a host without storage works out of the box.
 *
 * @since 0.1.0
 */
class MemoryServiceProvider
{
    /**
     * Binds the document and template repositories; a host replaces either by adding its own binding before first
     * use.
     *
     * @param BlockServiceContainer $container
     *   The container to bind into.
     *
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $container->add(DocumentRepositoryInterface::class, fn () => new InMemoryDocumentRepository());
        $container->add(BlockTemplateRepositoryInterface::class, fn () => new InMemoryBlockTemplateRepository());
    }
}
