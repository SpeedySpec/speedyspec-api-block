<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Media\Domain;

/**
 * What an allowed provider's content is embedded from, and where a visitor can open it instead.
 *
 * @since 0.1.0
 */
class Embed
{
    /**
     * @param string $provider
     *   Such as `youtube`.
     * @param string $src
     *   The provider's own embed address, built from the parts of the link that were checked, never copied from it.
     * @param string $link
     *   The content on the provider's site, for visitors who cannot or will not load the embed.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $src,
        public readonly string $link,
    )
    {
    }
}
