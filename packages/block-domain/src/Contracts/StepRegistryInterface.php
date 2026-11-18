<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * Holds the steps that hook into resolving, grouped by the step interface they implement.
 *
 * @since 0.1.0
 */
interface StepRegistryInterface
{
    /**
     * Registers a step under the interface it implements.
     *
     * @param class-string $stepInterface
     *   The step interface, such as {@link GateStepInterface}.
     * @param object $step
     *   The step; it must implement the interface.
     * @param int $priority
     *   Lower runs first; steps of equal priority run in the order they were added.
     *
     * @throws \InvalidArgumentException
     *   When the step does not implement the interface.
     *
     * @since 0.1.0
     */
    public function add(string $stepInterface, object $step, int $priority = 10): void;

    /**
     * Lists the steps registered under an interface in the order they run.
     *
     * @param class-string $stepInterface
     *   The step interface.
     *
     * @return list<object>
     *
     * @since 0.1.0
     */
    public function for(string $stepInterface): array;
}
