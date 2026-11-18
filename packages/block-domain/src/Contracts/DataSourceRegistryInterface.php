<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * Holds the registered data sources, looked up by the name bindings use.
 *
 * @since 0.1.0
 */
interface DataSourceRegistryInterface
{
    /**
     * Registers a source, replacing any source of the same name.
     *
     * @throws \SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException
     *   When a schema uses keywords outside the supported subset.
     *
     * @since 0.1.0
     */
    public function add(DataSourceInterface $source): void;

    /**
     * Unregisters a source; an unknown name is ignored.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Finds a source by name.
     *
     * @since 0.1.0
     */
    public function get(string $name): ?DataSourceInterface;
}
