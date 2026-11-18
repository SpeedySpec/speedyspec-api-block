<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

/**
 * Holds the registered block types, so a host can swap how types are stored without touching behaviours.
 *
 * @since 0.1.0
 */
interface BlockTypeRegistryInterface
{
    /**
     * Registers a block type, replacing any type of the same name.
     *
     * @throws \SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException
     *   When a schema uses keywords outside the supported subset.
     * @throws \SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException
     *   When an example does not satisfy the settings schema.
     *
     * @since 0.1.0
     */
    public function add(BlockType $type): void;

    /**
     * Unregisters a block type; an unknown name is ignored.
     *
     * @since 0.1.0
     */
    public function remove(BlockName $name): void;

    /**
     * Tells whether a type is registered under the name.
     *
     * @since 0.1.0
     */
    public function has(BlockName $name): bool;

    /**
     * Finds a registered type.
     *
     * @since 0.1.0
     */
    public function get(BlockName $name): ?BlockType;

    /**
     * Lists every registered type in registration order.
     *
     * @return list<BlockType>
     *
     * @since 0.1.0
     */
    public function all(): array;
}
