<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * Holds the template libraries a builder can offer.
 *
 * @since 0.1.0
 */
interface BlockTemplateLibraryRegistryInterface
{
    /**
     * Registers a library, replacing one already registered under the same name.
     *
     * @since 0.1.0
     */
    public function add(BlockTemplateLibraryInterface $library): void;

    /**
     * Removes the library registered under a name, if there is one.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Finds a library by name.
     *
     * @return BlockTemplateLibraryInterface|null
     *   Null when none is registered under the name.
     *
     * @since 0.1.0
     */
    public function get(string $name): ?BlockTemplateLibraryInterface;

    /**
     * Lists every library.
     *
     * @return list<BlockTemplateLibraryInterface>
     *   In registration order.
     *
     * @since 0.1.0
     */
    public function all(): array;
}
