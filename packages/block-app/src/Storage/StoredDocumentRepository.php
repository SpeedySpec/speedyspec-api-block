<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Storage;

use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidDocumentException;
use SpeedySpec\Block\Domain\Exceptions\StorageException;

/**
 * Keeps documents as serialized text in a record store, so one repository works over any storage.
 *
 * @since 0.1.0
 */
class StoredDocumentRepository implements DocumentRepositoryInterface
{
    /**
     * @param string $collection
     *   The record collection documents are kept in.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly RecordStoreInterface $store,
        private readonly DocumentSerializerInterface $serializer,
        private readonly string $collection = 'documents',
    )
    {
    }

    /**
     * {@inheritDoc}
     *
     * @throws StorageException
     *   When the stored record is not a document; it is reported, never treated as missing, so it cannot be
     *   silently overwritten with an empty one.
     *
     * @since 0.1.0
     */
    public function find(string $id): ?Document
    {
        $stored = $this->store->get($this->collection, $id);

        if ($stored === null)
        {
            return null;
        }

        try
        {
            return $this->serializer->parse($stored);
        }
        catch (InvalidDocumentException $e)
        {
            throw new StorageException(
                sprintf('The stored document "%s" cannot be read: %s', $id, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    public function save(Document $document): void
    {
        $this->store->put($this->collection, $document->id, $this->serializer->serialize($document));
    }

    public function remove(string $id): void
    {
        $this->store->delete($this->collection, $id);
    }
}
