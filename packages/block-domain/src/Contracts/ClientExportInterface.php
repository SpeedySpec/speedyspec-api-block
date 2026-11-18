<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Produces the copy of a document that may leave the server, so private settings never reach a visitor.
 *
 * Resolved blocks and documents carry private settings in both modes; together with the default view, this export
 * is what is safe to send to a visitor.
 *
 * The export removes private settings only. It carries every block as stored and does not run the steps that hide
 * blocks, such as visibility rules, because those need the context a page is resolved in. A host must not send
 * the export of a document whose rules hide content from the visitor; draw the document instead.
 *
 * @since 0.1.0
 */
interface ClientExportInterface
{
    /**
     * Copies the document for a surface; the source document is never changed.
     *
     * @param Document $document
     *   The stored document.
     * @param Mode $mode
     *   The surface the copy is for; the front surface must not receive private data.
     *
     * @since 0.1.0
     */
    public function export(Document $document, Mode $mode): Document;
}
