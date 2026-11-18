<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Sees the whole document once, before any block is resolved, and adds to the context every block starts from.
 *
 * A block's behaviour sees only its own block and what its ancestors provide. Something that depends on the rest of
 * the page, such as a table of contents listing the page's headings, needs this step to gather it first. Registered
 * through the step registry like the other steps; a block reads a value only if its type `uses` the key.
 *
 * @since 0.1.0
 */
interface DocumentStepInterface
{
    /**
     * Gives the context the root blocks are resolved in.
     *
     * @param Context $context
     *   What the caller resolved the document with, plus what earlier document steps added.
     *
     * @since 0.1.0
     */
    public function context(Document $document, Context $context, Mode $mode): Context;
}
