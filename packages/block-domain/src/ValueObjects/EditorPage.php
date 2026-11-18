<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * What an editor gives a host to place on its admin screen.
 *
 * @since 0.1.0
 */
class EditorPage
{
    /**
     * @param list<string> $assets
     *   Names of the editor's scripts and styles, which the host resolves through the asset registry and locator
     *   like any other assets.
     * @param string $markup
     *   Where the editor appears; every value in it already escaped.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly array $assets,
        public readonly string $markup,
    )
    {
    }
}
