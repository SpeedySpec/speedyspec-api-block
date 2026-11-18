<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;

/**
 * Makes a value safe for one output target, so a block's output needs nothing but the contracts to escape correctly.
 *
 * Settings and bound values are data, never markup. A block's output writes every value through one of these
 * methods, choosing the method by where the value lands.
 *
 * @since 0.1.0
 */
interface OutputEscaperInterface
{
    /**
     * Names the output target this escaper serves, for example `html`.
     *
     * @since 0.1.0
     */
    public function target(): string;

    /**
     * Escapes a value placed between elements.
     *
     * @since 0.1.0
     */
    public function text(string $value): string;

    /**
     * Escapes a value placed inside a quoted attribute.
     *
     * @since 0.1.0
     */
    public function attribute(string $value): string;

    /**
     * Escapes a link placed inside a quoted attribute, giving an empty string for a scheme that could run script.
     *
     * @since 0.1.0
     */
    public function url(string $value): string;

    /**
     * Draws an attribute bag for a block's root element.
     *
     * @return string
     *   An empty string for an empty bag; otherwise the attributes, starting with a space.
     *
     * @since 0.1.0
     */
    public function attributes(AttributeBag $bag): string;

    /**
     * Writes formatted text for the target, so an output never prints a stored value's markup unchecked.
     *
     * For html this is the value cleaned to the allowed subset; another target writes the text without markup.
     *
     * @since 0.1.0
     */
    public function richText(string $value): string;
}
