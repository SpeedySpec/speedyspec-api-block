<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * The outcome of validating a value, so callers get every failure at once instead of the first exception.
 *
 * @since 0.1.0
 */
class ValidationResult
{
    /**
     * Holds the failures; use valid() or invalid() to build one.
     *
     * @param list<ValidationError> $errors
     *   The failures found.
     *
     * @since 0.1.0
     */
    private function __construct(private readonly array $errors)
    {
    }

    /**
     * Creates a result with no errors.
     *
     * @since 0.1.0
     */
    public static function valid(): self
    {
        return new self([]);
    }

    /**
     * Creates a result carrying the given errors; calling it with no errors yields a valid result.
     *
     * @param ValidationError ...$errors
     *   The failures found.
     *
     * @since 0.1.0
     */
    public static function invalid(ValidationError ...$errors): self
    {
        return new self(array_values($errors));
    }

    /**
     * Tells whether the value satisfied the schema.
     *
     * @since 0.1.0
     */
    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * Lists every failure found.
     *
     * @return list<ValidationError>
     *
     * @since 0.1.0
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
