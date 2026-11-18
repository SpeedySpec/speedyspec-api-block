<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Keeps block behaviours in memory, one per block type.
 *
 * @since 0.1.0
 */
class BlockBehaviourRegistry implements BlockBehaviourRegistryInterface
{
    /**
     * @var array<string, BlockBehaviourInterface>
     */
    private array $behaviours = [];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function set(BlockName $name, BlockBehaviourInterface $behaviour): void
    {
        $this->behaviours[$name->toString()] = $behaviour;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function get(BlockName $name): ?BlockBehaviourInterface
    {
        return $this->behaviours[$name->toString()] ?? null;
    }
}
