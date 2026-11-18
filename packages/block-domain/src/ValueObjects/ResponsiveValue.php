<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * The one shape a setting takes when it may differ by screen width.
 *
 * Stored either as a single value, which applies at every width, or as a map with an optional `base` and one
 * entry per breakpoint name: `{"base": "1rem", "tablet": "2rem"}`. Every block and builder reads and writes this
 * shape, so a breakpoint added by the host works for all of them.
 *
 * @since 0.1.0
 */
class ResponsiveValue
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @since 0.1.0
     */
    private function __construct(
        private readonly mixed $base,
        private readonly array $overrides,
    )
    {
    }

    /**
     * Reads a stored setting, whichever of the two forms it is in.
     *
     * A map is taken as responsive; anything else, a list included, is the base value. Entries that are null or
     * whose key could not name a breakpoint are ignored.
     *
     * @since 0.1.0
     */
    public static function from(mixed $stored): self
    {
        if (!is_array($stored) || ($stored !== [] && array_is_list($stored)))
        {
            return new self($stored, []);
        }

        $overrides = [];

        foreach ($stored as $name => $value)
        {
            if ($value !== null && is_string($name) && Breakpoint::isName($name))
            {
                $overrides[$name] = $value;
            }
        }

        return new self($stored[Breakpoint::BASE] ?? null, $overrides);
    }

    /**
     * Gets the value that applies at every width, or null when only overrides are stored.
     *
     * @since 0.1.0
     */
    public function base(): mixed
    {
        return $this->base;
    }

    /**
     * Gets the override stored for a breakpoint, or null when there is none.
     *
     * @since 0.1.0
     */
    public function at(string $breakpoint): mixed
    {
        return $this->overrides[$breakpoint] ?? null;
    }

    /**
     * Lists the overrides by breakpoint name, in stored order.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function overrides(): array
    {
        return $this->overrides;
    }

    /**
     * Tells whether nothing is stored at any width.
     *
     * @since 0.1.0
     */
    public function isEmpty(): bool
    {
        return $this->base === null && $this->overrides === [];
    }
}
