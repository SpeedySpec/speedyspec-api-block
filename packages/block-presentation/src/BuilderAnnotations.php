<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Presentation;

use SpeedySpec\Block\Domain\Contracts\RenderStepInterface;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;

/**
 * Names each block on its element in an editor's drawing, so a builder can tell which block was clicked.
 *
 * A visitor's page is never annotated. A block whose output does not write its attribute bag gets no name on the
 * page; a builder must still let it be selected some other way, such as from an outline.
 *
 * @since 0.1.0
 */
class BuilderAnnotations implements RenderStepInterface
{
    public const string ATTRIBUTE = 'data-block-id';

    public function attributes(AttributeBag $bag, ResolvedBlock $block, RenderRequest $request): AttributeBag
    {
        if ($request->mode !== Mode::Admin || $request->target !== 'html')
        {
            return $bag;
        }

        return $bag->withAttribute(self::ATTRIBUTE, $block->block->id);
    }
}
