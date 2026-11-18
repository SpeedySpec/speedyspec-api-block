<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Holds the behaviour of each block type, separate from the types so either can be replaced alone.
 *
 * @since 0.1.0
 */
interface BlockBehaviourRegistryInterface
{
    /**
     * Sets the behaviour for a type, replacing any earlier one.
     *
     * @since 0.1.0
     */
    public function set(BlockName $name, BlockBehaviourInterface $behaviour): void;

    /**
     * Finds the behaviour registered for a type.
     *
     * @since 0.1.0
     */
    public function get(BlockName $name): ?BlockBehaviourInterface;
}
