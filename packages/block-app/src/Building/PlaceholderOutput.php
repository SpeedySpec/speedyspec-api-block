<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Building;

use SpeedySpec\Block\Domain\Contracts\OutputEscaperInterface;
use SpeedySpec\Block\Domain\Contracts\UnresolvedBlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;

/**
 * Draws an empty element naming the block, leaving what it looks like and says to the builder.
 *
 * @since 0.1.0
 */
class PlaceholderOutput implements UnresolvedBlockOutputInterface
{
    public function render(ResolvedBlock $block, RenderRequest $request, OutputEscaperInterface $escaper): string
    {
        $bag = AttributeBag::empty()
            ->withClass('block-placeholder')
            ->withAttribute('data-block-id', $block->block->id)
            ->withAttribute('data-block-type', $block->block->type->toString());

        return sprintf('<div%s></div>', $escaper->attributes($bag));
    }
}
