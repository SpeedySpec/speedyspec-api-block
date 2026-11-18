<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

/**
 * Draws one block for one output target and mode.
 *
 * The package that adds a block type supplies its output. An output must write every value through the input's
 * escaper and apply the input's attribute bag to its root element; it must not emit a view value as markup.
 *
 * @since 0.1.0
 */
interface BlockOutputInterface
{
    /**
     * Draws the block described by the input.
     *
     * @return string
     *   The block's output, with its children placed inside it.
     *
     * @since 0.1.0
     */
    public function render(RenderInput $input): string;
}
