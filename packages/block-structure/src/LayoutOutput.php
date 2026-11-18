<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Structure;

use Closure;
use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

/**
 * Draws a layout block as a plain element whose arrangement comes from collected style rules.
 *
 * Layout that changes with screen width cannot be written on the element, so the block's rules go to the
 * collector and the element gets the class they are filed under.
 *
 * @since 0.1.0
 */
class LayoutOutput implements BlockOutputInterface
{
    /**
     * @param Closure(array<string, mixed>): list<StyleRule> $rules
     *   Builds the layout rules from the block's view values.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly StyleCollectorInterface $styles,
        private readonly Closure $rules,
    )
    {
    }

    public function render(RenderInput $input): string
    {
        $class = $this->styles->add(...($this->rules)($input->view->values));
        $attributes = $class === '' ? $input->attributes : $input->attributes->withClass($class);

        return sprintf('<div%s>%s</div>', $input->escaper->attributes($attributes), $input->children());
    }
}
