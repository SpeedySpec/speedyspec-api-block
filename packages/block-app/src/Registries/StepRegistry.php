<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use InvalidArgumentException;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;

/**
 * Keeps resolving steps in memory, grouped by step interface and ordered by priority.
 *
 * @since 0.1.0
 */
class StepRegistry implements StepRegistryInterface
{
    /**
     * @var array<string, list<array{int, object}>>
     */
    private array $steps = [];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function add(string $stepInterface, object $step, int $priority = 10): void
    {
        if (!$step instanceof $stepInterface)
        {
            throw new InvalidArgumentException(
                sprintf('A %s step must implement %s.', $step::class, $stepInterface)
            );
        }

        $this->steps[$stepInterface][] = [$priority, $step];
    }

    /**
     * {@inheritDoc}
     *
     * Sorting is stable, so steps of equal priority keep their insertion order.
     *
     * @since 0.1.0
     */
    public function for(string $stepInterface): array
    {
        $steps = $this->steps[$stepInterface] ?? [];
        usort($steps, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(static fn (array $entry): object => $entry[1], $steps);
    }
}
