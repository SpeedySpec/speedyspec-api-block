<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\InvalidCollectionException;
use SpeedySpec\Block\Domain\Exceptions\StorageException;

/**
 * Keeps named text records, so a repository can be written once and stored anywhere.
 *
 * This is the only thing a host implements to put documents and saved templates in its own storage: files, a
 * database table, a key-value service. The repositories turn documents and templates into text and back; a record
 * store never looks inside a value.
 *
 * A collection groups one kind of record, such as `documents` or `templates`; its name follows
 * CollectionName::assert(). A key is any string, compared exactly: a store must not fold case, trim, or treat any
 * character as special.
 *
 * A value is text: valid UTF-8 with no NUL byte, which is what the serializers write. A store may refuse or need
 * not preserve anything else, so that a database's text column is a fair place to keep one.
 *
 * @since 0.1.0
 */
interface RecordStoreInterface
{
    /**
     * Reads a record.
     *
     * @return string|null
     *   The value exactly as it was put; null when the collection has no record under the key.
     *
     * @throws InvalidCollectionException
     *   When the collection name is malformed.
     * @throws StorageException
     *   When storage cannot be read.
     *
     * @since 0.1.0
     */
    public function get(string $collection, string $key): ?string;

    /**
     * Writes a record, replacing one already under the key without changing its place in keys().
     *
     * @throws InvalidCollectionException
     *   When the collection name is malformed.
     * @throws StorageException
     *   When storage cannot be written.
     *
     * @since 0.1.0
     */
    public function put(string $collection, string $key, string $value): void;

    /**
     * Removes a record; an unknown key is not an error.
     *
     * @throws InvalidCollectionException
     *   When the collection name is malformed.
     * @throws StorageException
     *   When storage cannot be written.
     *
     * @since 0.1.0
     */
    public function delete(string $collection, string $key): void;

    /**
     * Lists the keys of a collection.
     *
     * @return list<string>
     *   Keys in the order they were first put; a key deleted and put again goes to the end.
     *
     * @throws InvalidCollectionException
     *   When the collection name is malformed.
     * @throws StorageException
     *   When storage cannot be read.
     *
     * @since 0.1.0
     */
    public function keys(string $collection): array;
}
