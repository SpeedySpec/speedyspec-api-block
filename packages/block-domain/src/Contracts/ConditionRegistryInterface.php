<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;

/**
 * Holds the conditions visibility rules may name.
 *
 * @since 0.1.0
 */
interface ConditionRegistryInterface
{
    /**
     * Registers a condition, replacing one already registered under the same name.
     *
     * @throws UnsupportedSchemaException
     *   When its arguments schema uses a keyword outside the supported subset.
     *
     * @since 0.1.0
     */
    public function add(ConditionInterface $condition): void;

    /**
     * Removes the condition registered under a name, if there is one.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Finds a condition by name.
     *
     * @return ConditionInterface|null
     *   Null when none is registered under the name.
     *
     * @since 0.1.0
     */
    public function get(string $name): ?ConditionInterface;

    /**
     * Lists every condition, so a builder can offer them.
     *
     * @return list<ConditionInterface>
     *   In registration order.
     *
     * @since 0.1.0
     */
    public function all(): array;
}
