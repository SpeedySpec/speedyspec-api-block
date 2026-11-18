<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Presentation;

use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\Breakpoint;

/**
 * Keeps the site's breakpoints in memory, starting from the ones it is given.
 *
 * @since 0.1.0
 */
class BreakpointRegistry implements BreakpointRegistryInterface
{
    /**
     * @var array<string, Breakpoint>
     */
    private array $breakpoints = [];

    /**
     * @since 0.1.0
     */
    public function __construct(Breakpoint ...$breakpoints)
    {
        foreach ($breakpoints as $breakpoint)
        {
            $this->add($breakpoint);
        }
    }

    public function add(Breakpoint $breakpoint): void
    {
        $this->breakpoints[$breakpoint->name] = $breakpoint;
    }

    public function remove(string $name): void
    {
        unset($this->breakpoints[$name]);
    }

    public function get(string $name): ?Breakpoint
    {
        return $this->breakpoints[$name] ?? null;
    }

    public function all(): array
    {
        $breakpoints = array_values($this->breakpoints);

        usort(
            $breakpoints,
            static fn (Breakpoint $a, Breakpoint $b): int => [$a->minWidth, $a->name] <=> [$b->minWidth, $b->name],
        );

        return $breakpoints;
    }
}
