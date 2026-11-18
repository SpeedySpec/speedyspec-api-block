<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

/**
 * Gathers the style rules blocks ask for while a page is drawn, and writes them as one stylesheet.
 *
 * Styles that differ by screen width cannot be written on an element, so they are collected here and the block
 * is given a class instead. The host reads css() after drawing and places it in the page.
 *
 * @since 0.1.0
 */
interface StyleCollectorInterface
{
    /**
     * Records the rules for one block and names the class that carries them.
     *
     * Equal sets of rules get the same class, so blocks styled alike share one rule in the stylesheet.
     *
     * @return string
     *   The class to put on the block's element; empty when no rules are given.
     *
     * @since 0.1.0
     */
    public function add(StyleRule ...$rules): string;

    /**
     * Writes the stylesheet for everything collected since the last clear().
     *
     * @return string
     *   The design tokens as custom properties, then rules for every width, then one media query per
     *   breakpoint, narrowest first. Rules for a breakpoint that is not registered are left out.
     *
     * @since 0.1.0
     */
    public function css(): string;

    /**
     * Forgets the collected rules, so the next page starts empty.
     *
     * @since 0.1.0
     */
    public function clear(): void;
}
