<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Storage;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;

/**
 * Binds the document and template repositories, and the site's design tokens and breakpoints, over whichever
 * record store the host binds.
 *
 * A host registers this with one record store (files, a database, its own) and gets all four; it never has to
 * implement a repository itself. The record store may be bound before or after this provider, since the in-memory
 * provider only fills gaps and nothing is built until it is first used. This provider must come after the
 * application's own provider, which binds in-memory token and breakpoint registries.
 *
 * @since 0.1.0
 */
class StorageServiceProvider
{
    /**
     * @param array<string, string> $tokens
     *   The design tokens a site starts with, until its tokens are first changed.
     * @param list<Breakpoint>|null $breakpoints
     *   The breakpoints a site starts with; null for the usual ones.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly array $tokens = [],
        private readonly ?array $breakpoints = null,
    )
    {
    }

    /**
     * Binds everything lazily, so the record store may be bound before or after this call.
     *
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $container->add(
            DesignTokenRegistryInterface::class,
            fn (BlockServiceContainer $c) => new StoredDesignTokenRegistry(
                $c->get(RecordStoreInterface::class),
                $this->tokens,
            ),
        );
        $container->add(
            BreakpointRegistryInterface::class,
            fn (BlockServiceContainer $c) => new StoredBreakpointRegistry(
                $c->get(RecordStoreInterface::class),
                $this->breakpoints,
            ),
        );
        $container->add(
            DocumentRepositoryInterface::class,
            fn (BlockServiceContainer $c) => new StoredDocumentRepository(
                $c->get(RecordStoreInterface::class),
                $c->get(DocumentSerializerInterface::class),
            ),
        );
        $container->add(
            BlockTemplateRepositoryInterface::class,
            fn (BlockServiceContainer $c) => new StoredBlockTemplateRepository(
                $c->get(RecordStoreInterface::class),
                $c->get(BlockTemplateSerializerInterface::class),
            ),
        );
    }
}
