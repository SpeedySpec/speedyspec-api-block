<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\Ports;

/**
 * What the adapter needs from WordPress to draw a block whose markup is produced by a render callback.
 *
 * @since 0.1.0
 */
interface WpRendererInterface
{
    /**
     * Draws a dynamic block.
     *
     * @param array<string, mixed> $attributes
     * @param string $content
     *   The block's saved markup with its inner blocks already drawn.
     *
     * @since 0.1.0
     */
    public function render(string $name, array $attributes, string $content): string;
}
