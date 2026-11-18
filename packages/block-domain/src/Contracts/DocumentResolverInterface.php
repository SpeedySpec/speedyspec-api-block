<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;

/**
 * Turns a stored document into every block's final settings, view and context, without drawing anything.
 *
 * Renderers and form handlers both build on the result, so they read the same final values.
 *
 * @since 0.1.0
 */
interface DocumentResolverInterface
{
    /**
     * Resolves a whole document; bad content is recorded as issues and never thrown.
     *
     * @param Document $document
     *   The stored document.
     * @param Mode $mode
     *   Where the document is resolved.
     * @param Context|null $context
     *   The context the root blocks receive; null for an empty one.
     *
     * @since 0.1.0
     */
    public function resolve(Document $document, Mode $mode, ?Context $context = null): ResolvedDocument;
}
