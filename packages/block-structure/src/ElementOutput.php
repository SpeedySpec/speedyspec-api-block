<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Structure;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

/**
 * Draws one element from a fixed list of tags, holding its escaped text and then its children.
 *
 * The tag is checked against the list again here, because the output must stay safe even for a block whose
 * settings were stored without going through the editor.
 *
 * @since 0.1.0
 */
class ElementOutput implements BlockOutputInterface
{
    /**
     * @param list<string> $tags
     *   The tags this output may draw; the first is used when the stored one is not in the list.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly array $tags)
    {
    }

    public function render(RenderInput $input): string
    {
        $values = $input->view->values;
        $tag = in_array($values['tag'] ?? null, $this->tags, true) ? $values['tag'] : $this->tags[0];
        $label = (string) ($values['label'] ?? '');
        $attributes = $label === '' ? $input->attributes : $input->attributes->withAttribute('aria-label', $label);

        return sprintf(
            '<%1$s%2$s>%3$s%4$s</%1$s>',
            $tag,
            $input->escaper->attributes($attributes),
            $input->escaper->text((string) ($values['text'] ?? '')),
            $input->children(),
        );
    }
}
