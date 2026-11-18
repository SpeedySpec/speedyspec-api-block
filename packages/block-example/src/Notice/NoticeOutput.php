<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Example\Notice;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

/**
 * Draws a notice for a visitor.
 *
 * The private internal note is not in the view a visitor's output receives, so it cannot be drawn here by mistake.
 *
 * @since 0.1.0
 */
class NoticeOutput implements BlockOutputInterface
{
    public function render(RenderInput $input): string
    {
        $tone = ($input->view->values['tone'] ?? 'info') === 'warning' ? 'warning' : 'info';
        $attributes = AttributeBag::empty()->withClass('notice', 'notice--' . $tone)->merge($input->attributes);

        return sprintf(
            '<div%s>%s</div>',
            $input->escaper->attributes($attributes),
            $input->escaper->text((string) ($input->view->values['text'] ?? '')),
        );
    }
}
