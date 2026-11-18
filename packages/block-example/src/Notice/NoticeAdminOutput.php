<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Example\Notice;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

/**
 * Draws a notice for an editor: the notice as a visitor sees it, followed by its internal note.
 *
 * @since 0.1.0
 */
class NoticeAdminOutput implements BlockOutputInterface
{
    /**
     * Reuses the visitor's output so the two can never drift apart.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly BlockOutputInterface $front)
    {
    }

    public function render(RenderInput $input): string
    {
        $note = (string) ($input->view->values['internalNote'] ?? '');
        $html = $this->front->render($input);

        if ($note === '')
        {
            return $html;
        }

        return $html . sprintf('<p class="notice__note">%s</p>', $input->escaper->text($note));
    }
}
