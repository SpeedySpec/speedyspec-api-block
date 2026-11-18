<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;

/**
 * Holds the site's design tokens: named values such as a brand colour or a spacing step.
 *
 * A setting refers to a token as `token:color.primary` rather than storing the value, so changing the token
 * changes every block that uses it.
 *
 * @since 0.1.0
 */
interface DesignTokenRegistryInterface
{
    /**
     * Sets a token, replacing its value if it is already set.
     *
     * @param string $name
     *   Lower-case segments joined by dots, such as `color.primary`.
     *
     * @throws InvalidStyleException
     *   When the name is malformed or the value is not safe to write into a stylesheet.
     *
     * @since 0.1.0
     */
    public function set(string $name, string $value): void;

    /**
     * Removes a token, if it is set.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Reads a token's value.
     *
     * @return string|null
     *   Null when the token is not set.
     *
     * @since 0.1.0
     */
    public function get(string $name): ?string;

    /**
     * Lists every token.
     *
     * @return array<string, string>
     *   Values by name, in the order the tokens were first set.
     *
     * @since 0.1.0
     */
    public function all(): array;
}
