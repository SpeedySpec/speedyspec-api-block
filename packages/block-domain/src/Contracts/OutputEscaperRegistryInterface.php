<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\UnsupportedTargetException;

/**
 * Holds one escaper per output target, so a host can add a target or replace an escaper without touching blocks.
 *
 * @since 0.1.0
 */
interface OutputEscaperRegistryInterface
{
    /**
     * Registers an escaper, replacing one already registered for the same target.
     *
     * @since 0.1.0
     */
    public function add(OutputEscaperInterface $escaper): void;

    /**
     * Finds the escaper for a target.
     *
     * @throws UnsupportedTargetException
     *   When no escaper is registered for the target.
     *
     * @since 0.1.0
     */
    public function get(string $target): OutputEscaperInterface;

    /**
     * Tells whether a target has an escaper.
     *
     * @since 0.1.0
     */
    public function has(string $target): bool;
}
