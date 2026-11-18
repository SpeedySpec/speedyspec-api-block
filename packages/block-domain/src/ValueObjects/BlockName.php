<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException;

/**
 * A validated `vendor/name` block identifier, so no other code has to re-check the format.
 *
 * @since 0.1.0
 */
class BlockName
{
    private function __construct(private readonly string $value)
    {
    }

    /**
     * Parses a block name, rejecting anything that is not lower-case `vendor/name`.
     *
     * @param string $value
     *   The raw name, for example `speedyspec/text-field`.
     *
     * @throws InvalidBlockNameException
     *   When the value does not match the format.
     *
     * @since 0.1.0
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*\z/', $value) !== 1)
        {
            throw new InvalidBlockNameException(
                sprintf('"%s" is not a valid block name; expected lower-case "vendor/name".', $value)
            );
        }

        return new self($value);
    }

    /**
     * Renders the full `vendor/name` string.
     *
     * @since 0.1.0
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Extracts the part before the slash.
     *
     * @since 0.1.0
     */
    public function vendor(): string
    {
        return explode('/', $this->value, 2)[0];
    }

    /**
     * Extracts the part after the slash.
     *
     * @since 0.1.0
     */
    public function name(): string
    {
        return explode('/', $this->value, 2)[1];
    }

    /**
     * Compares by value, since instances are not interned.
     *
     * @param self $other
     *   The name to compare with.
     *
     * @since 0.1.0
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
