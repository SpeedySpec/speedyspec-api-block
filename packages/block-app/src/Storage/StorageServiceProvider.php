<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Storage;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;

/**
 * Binds the document and template repositories over whichever record store the host binds.
 *
 * A host registers this with one record store (files, a database, its own) and gets both repositories; it never
 * has to implement a repository itself. Register it after a provider that binds in-memory repositories to take
 * their place.
 *
 * @since 0.1.0
 */
class StorageServiceProvider
{
    /**
     * Binds both repositories lazily, so the record store may be bound before or after this call.
     *
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
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
