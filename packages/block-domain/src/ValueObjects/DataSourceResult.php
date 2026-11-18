<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * What a data source produced, distinguishing "no value" from a value that happens to be null.
 *
 * @since 0.1.0
 */
class DataSourceResult
{
    private function __construct(
        private readonly bool $hasValue,
        private readonly mixed $value,
    )
    {
    }

    /**
     * Wraps a produced value, which may itself be null.
     *
     * @since 0.1.0
     */
    public static function value(mixed $value): self
    {
        return new self(true, $value);
    }

    /**
     * Signals that the source had nothing to give, so a binding can fall back.
     *
     * @since 0.1.0
     */
    public static function none(): self
    {
        return new self(false, null);
    }

    /**
     * Tells whether the source produced a value.
     *
     * @since 0.1.0
     */
    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    /**
     * Reads the value; null when the source produced none.
     *
     * @since 0.1.0
     */
    public function get(): mixed
    {
        return $this->value;
    }
}
