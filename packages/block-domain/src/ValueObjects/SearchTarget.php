<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Where a site's search form sends what was typed.
 *
 * @since 0.1.0
 */
class SearchTarget
{
    /**
     * @param string $url
     *   The address the form is sent to.
     * @param string $parameter
     *   The name the search text is sent under, such as `q` or WordPress's `s`.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $url,
        public readonly string $parameter,
    )
    {
    }
}
