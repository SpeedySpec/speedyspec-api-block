<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;

/**
 * Holds the site's breakpoints, so every block and builder uses the same ones instead of hard-wiring its own.
 *
 * @since 0.1.0
 */
interface BreakpointRegistryInterface
{
    /**
     * Registers a breakpoint, replacing one already registered under the same name.
     *
     * @since 0.1.0
     */
    public function add(Breakpoint $breakpoint): void;

    /**
     * Removes the breakpoint registered under a name, if there is one.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Finds a breakpoint by name.
     *
     * @return Breakpoint|null
     *   Null when none is registered under the name.
     *
     * @since 0.1.0
     */
    public function get(string $name): ?Breakpoint;

    /**
     * Lists every breakpoint.
     *
     * @return list<Breakpoint>
     *   Narrowest first, the order their rules must be written in for wider ones to win.
     *
     * @since 0.1.0
     */
    public function all(): array;
}
