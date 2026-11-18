<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP\WordPress;

use SpeedySpec\Block\Infra\WP\Ports\WpRendererInterface;

/**
 * Draws a dynamic block with WordPress's own renderer.
 *
 * @since 0.1.0
 */
class WordPressRenderer implements WpRendererInterface
{
    public function render(string $name, array $attributes, string $content): string
    {
        // The inner blocks are already drawn into the content, so the block is handed over with none.
        return (string) \render_block([
            'blockName' => $name,
            'attrs' => $attributes,
            'innerBlocks' => [],
            'innerHTML' => $content,
            'innerContent' => [$content],
        ]);
    }
}
