<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Example\Listing;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

/**
 * Draws a list as a numbered or bulleted element holding its items.
 *
 * @since 0.1.0
 */
class ListOutput implements BlockOutputInterface
{
    public function render(RenderInput $input): string
    {
        $element = ($input->view->values['ordered'] ?? false) === true ? 'ol' : 'ul';
        $label = (string) ($input->view->values['label'] ?? '');
        $attributes = $label === '' ? $input->attributes : $input->attributes->withAttribute('aria-label', $label);

        return sprintf(
            '<%1$s%2$s>%3$s</%1$s>',
            $element,
            $input->escaper->attributes($attributes),
            $input->children(),
        );
    }
}
