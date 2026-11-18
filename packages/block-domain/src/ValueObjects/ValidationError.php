<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One failed schema rule, located by path so callers can point at the offending value.
 *
 * @since 0.1.0
 */
class ValidationError
{
    /**
     * @param string $path
     *   Dot-separated location from the root; empty for the root, `items.0.label` for nested values.
     * @param string $keyword
     *   The schema keyword that failed, for example `required` or `maxLength`.
     * @param string $message
     *   A human-readable explanation.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $path,
        public readonly string $keyword,
        public readonly string $message,
    ) {
    }
}
