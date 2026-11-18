<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Infra\WP\Ports\WpRendererInterface;

/**
 * Draws a WordPress block: its saved markup around its drawn children, handed to WordPress when it is dynamic.
 *
 * The saved markup is written as it is, exactly as WordPress writes post content; it was cleaned when the post
 * was saved, and escaping it here would show the markup as text.
 *
 * Children are drawn together where the block's first inner block was saved, followed by whatever markup the
 * block had saved between its inner blocks, which is normally only space.
 *
 * @since 0.1.0
 */
class WpBlockOutput implements BlockOutputInterface
{
    /**
     * @param bool $dynamic
     *   Whether WordPress draws the type with a render callback.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly WpRendererInterface $renderer,
        private readonly bool $dynamic,
    )
    {
    }

    public function render(RenderInput $input): string
    {
        $attributes = $input->view->values;
        $pieces = is_array($attributes[WpMarkup::CONTENT] ?? null) ? $attributes[WpMarkup::CONTENT] : [];
        unset($attributes[WpMarkup::CONTENT]);
        $children = $input->children();
        $content = '';
        $placed = false;

        foreach ($pieces as $piece)
        {
            if (is_string($piece))
            {
                $content .= $piece;
            }
            elseif (!$placed)
            {
                $content .= $children;
                $placed = true;
            }
        }

        if (!$placed)
        {
            $content .= $children;
        }

        return $this->dynamic
            ? $this->renderer->render($input->type->name->toString(), $attributes, $content)
            : $content;
    }
}
