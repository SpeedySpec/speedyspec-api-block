<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * Reduces formatted text to the small HTML subset every editor writes and every output may print.
 *
 * Formatted text is a setting whose schema is `"type": "string"` with `"x-control": "rich-text"`. What survives is
 * `strong`, `em`, `s`, `code`, `sub`, `sup`, `mark`, `br`, and `a` with an `href` that is `http`, `https`, `mailto`,
 * `tel` or a path on the same site. Any other element is removed and its text kept; any other attribute, and
 * comments, are removed. The value is cleaned when it is stored and again when it is drawn, because stored content
 * may predate the rule or arrive by another route.
 *
 * @since 0.1.0
 */
interface RichTextSanitizerInterface
{
    /**
     * Cleans one value; it never throws, whatever it is given.
     *
     * @since 0.1.0
     */
    public function clean(string $html): string;
}
