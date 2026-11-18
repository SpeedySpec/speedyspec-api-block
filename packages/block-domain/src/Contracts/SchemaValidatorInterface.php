<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;
use SpeedySpec\Block\Domain\ValueObjects\ValidationResult;

/**
 * Checks values against the supported JSON Schema subset, so block settings are validated without a
 * full JSON Schema engine.
 *
 * @since 0.1.0
 */
interface SchemaValidatorInterface
{
    /**
     * Rejects a schema that uses keywords outside the supported subset.
     *
     * @param array<string, mixed> $schema
     *   The schema to check.
     * @param bool $allowPrivate
     *   Whether `x-private` is honoured; it is allowed on top-level properties only.
     *
     * @throws UnsupportedSchemaException
     *   Naming the first unsupported keyword and its dotted path.
     *
     * @since 0.1.0
     */
    public function assertSupported(array $schema, bool $allowPrivate = false): void;

    /**
     * Validates a value against a schema, collecting every failure.
     *
     * @param mixed $value
     *   The value to check.
     * @param array<string, mixed> $schema
     *   The schema to check against.
     *
     * @since 0.1.0
     */
    public function validate(mixed $value, array $schema): ValidationResult;

    /**
     * Fills missing object properties from their declared `default`, recursively through `properties`.
     *
     * Present keys are never overwritten.
     *
     * @param array<array-key, mixed> $value
     *   The object value to complete.
     * @param array<string, mixed> $schema
     *   The schema declaring the defaults.
     *
     * @return array<array-key, mixed>
     *
     * @since 0.1.0
     */
    public function withDefaults(array $value, array $schema): array;

    /**
     * Lists the top-level property names marked `x-private: true`.
     *
     * @param array<string, mixed> $schema
     *   The settings schema.
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    public function privateProperties(array $schema): array;

    /**
     * Returns the schema at a property path, or null when the path is not declared.
     *
     * @param array<string, mixed> $schema
     *   The root schema.
     * @param list<string> $segments
     *   Property names from the root, for example `['style', 'color']`.
     *
     * @return array<string, mixed>|null
     *
     * @since 0.1.0
     */
    public function subschema(array $schema, array $segments): ?array;
}
