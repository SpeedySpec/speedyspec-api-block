<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Entities\Document;

/**
 * Keeps documents in process memory for tests and hosts without storage.
 *
 * Documents are immutable, so the stored instance is handed back as is.
 *
 * @since 0.1.0
 */
class InMemoryDocumentRepository implements DocumentRepositoryInterface
{
    /**
     * @var array<string, Document>
     */
    private array $documents = [];

    /**
     * @since 0.1.0
     */
    public function find(string $id): ?Document
    {
        return $this->documents[$id] ?? null;
    }

    /**
     * @since 0.1.0
     */
    public function ids(): array
    {
        return array_map(strval(...), array_keys($this->documents));
    }

    public function save(Document $document): void
    {
        $this->documents[$document->id] = $document;
    }

    /**
     * @since 0.1.0
     */
    public function remove(string $id): void
    {
        unset($this->documents[$id]);
    }
}
