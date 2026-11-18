<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;

/**
 * Holds the registered decorators, so extensions can be looked up per block type.
 *
 * @since 0.1.0
 */
interface DecoratorRegistryInterface
{
    /**
     * Registers a decorator, replacing any decorator with the same namespace in place.
     *
     * @throws \SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException
     *   When a schema uses keywords outside the supported subset.
     *
     * @since 0.1.0
     */
    public function add(Decorator $decorator): void;

    /**
     * Unregisters a decorator; an unknown namespace is ignored.
     *
     * @since 0.1.0
     */
    public function remove(string $namespace): void;

    /**
     * Finds a decorator by namespace.
     *
     * @since 0.1.0
     */
    public function get(string $namespace): ?Decorator;

    /**
     * Lists the decorators that apply to a block type, in registration order.
     *
     * @return list<Decorator>
     *
     * @since 0.1.0
     */
    public function for(BlockName $type): array;
}
