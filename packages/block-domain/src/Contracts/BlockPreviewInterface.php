<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockPreview;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;

/**
 * Draws one block of a document, so a builder can refresh what changed without redrawing the page.
 *
 * @since 0.1.0
 */
interface BlockPreviewInterface
{
    /**
     * Draws a block and its descendants as they appear inside their document.
     *
     * The whole document is resolved first, so the block gets the context its ancestors provide; only the block
     * and its descendants are drawn.
     *
     * @param Context|null $context
     *   The context the document itself is resolved in.
     *
     * @return BlockPreview|null
     *   Null when the document has no block with that id.
     *
     * @since 0.1.0
     */
    public function preview(
        Document $document,
        string $blockId,
        RenderRequest $request,
        ?Context $context = null,
    ): ?BlockPreview;
}
