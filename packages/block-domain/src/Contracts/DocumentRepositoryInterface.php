<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Document;

/**
 * Stores documents by id so hosts choose where block content lives.
 *
 * @since 0.1.0
 */
interface DocumentRepositoryInterface
{
    /**
     * Loads a document, or null when none is stored under the id.
     *
     * @param string $id
     *   The document id.
     *
     * @since 0.1.0
     */
    public function find(string $id): ?Document;

    /**
     * Lists the ids of every stored document, so a builder can offer them to open.
     *
     * @return list<string>
     *   Ids in the order the documents were first saved.
     *
     * @since 0.1.0
     */
    public function ids(): array;

    /**
     * Stores a document under its id, replacing any previous one.
     *
     * @param Document $document
     *   The document to store.
     *
     * @since 0.1.0
     */
    public function save(Document $document): void;

    /**
     * Deletes a document; an unknown id is not an error.
     *
     * @param string $id
     *   The document id.
     *
     * @since 0.1.0
     */
    public function remove(string $id): void;
}
