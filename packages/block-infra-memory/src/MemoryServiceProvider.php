<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;

/**
 * Binds the in-memory implementations so a host without storage works out of the box.
 *
 * @since 0.1.0
 */
class MemoryServiceProvider
{
    /**
     * Binds the document and template repositories and a record store, each only where nothing is bound yet.
     *
     * It fills gaps and never replaces: whatever order the providers are registered in, real storage a host
     * bound stays in place, so nothing is saved to memory by accident and lost at the end of the request.
     *
     * @param BlockServiceContainer $container
     *   The container to bind into.
     *
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $defaults = [
            DocumentRepositoryInterface::class => fn () => new InMemoryDocumentRepository(),
            BlockTemplateRepositoryInterface::class => fn () => new InMemoryBlockTemplateRepository(),
            RecordStoreInterface::class => fn () => new InMemoryRecordStore(),
        ];

        foreach ($defaults as $contract => $factory)
        {
            if (!$container->has($contract))
            {
                $container->add($contract, $factory);
            }
        }
    }
}
