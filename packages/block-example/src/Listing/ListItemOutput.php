<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Example\Listing;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

/**
 * Draws one list entry.
 *
 * @since 0.1.0
 */
class ListItemOutput implements BlockOutputInterface
{
    public function render(RenderInput $input): string
    {
        return sprintf(
            '<li%s>%s</li>',
            $input->escaper->attributes($input->attributes),
            $input->escaper->text((string) ($input->view->values['text'] ?? '')),
        );
    }
}
