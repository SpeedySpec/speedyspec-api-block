<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One step of the path from the top of a site to a page.
 *
 * @since 0.1.0
 */
class Crumb
{
    /**
     * @param string $url
     *   Where the step leads; empty for the page itself, which is not linked to.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $label,
        public readonly string $url = '',
    )
    {
    }
}
