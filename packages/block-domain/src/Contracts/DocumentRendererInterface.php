<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use InvalidArgumentException;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedTargetException;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\RenderResult;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;

/**
 * Draws a document for an output target and mode.
 *
 * Drawing never fails because of bad content: a block that cannot be drawn is reported as an issue and the rest of
 * the document is still drawn.
 *
 * @since 0.1.0
 */
interface DocumentRendererInterface
{
    /**
     * Resolves a document in the request's mode and draws it.
     *
     * @param Context|null $context
     *   Values the host makes available to every block; null means none.
     *
     * @throws UnsupportedTargetException
     *   When the host has no escaper for the request's target.
     *
     * @since 0.1.0
     */
    public function render(Document $document, RenderRequest $request, ?Context $context = null): RenderResult;

    /**
     * Draws a document that was already resolved, so a caller that needs both does the resolving once.
     *
     * @throws InvalidArgumentException
     *   When the document was resolved in a different mode than the request asks for.
     * @throws UnsupportedTargetException
     *   When the host has no escaper for the request's target.
     *
     * @since 0.1.0
     */
    public function renderResolved(ResolvedDocument $resolved, RenderRequest $request): RenderResult;
}
