<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;

/**
 * Holds the collection sources repeating blocks may name.
 *
 * @since 0.1.0
 */
interface CollectionSourceRegistryInterface
{
    /**
     * Registers a collection source, replacing one already registered under the same name.
     *
     * @throws UnsupportedSchemaException
     *   When its arguments schema uses a keyword outside the supported subset.
     *
     * @since 0.1.0
     */
    public function add(CollectionSourceInterface $source): void;

    /**
     * Removes the collection source registered under a name, if there is one.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Finds a collection source by name.
     *
     * @return CollectionSourceInterface|null
     *   Null when none is registered under the name.
     *
     * @since 0.1.0
     */
    public function get(string $name): ?CollectionSourceInterface;

    /**
     * Lists every collection source, so a builder can offer them.
     *
     * @return list<CollectionSourceInterface>
     *   In registration order.
     *
     * @since 0.1.0
     */
    public function all(): array;
}
