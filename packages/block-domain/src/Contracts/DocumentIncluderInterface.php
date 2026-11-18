<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;

/**
 * Draws one stored document inside another, such as a header or a reusable section, without ever looping.
 *
 * What an included document reports cannot travel back through the block that includes it, because a block's
 * output is only markup. So the includer keeps the issues and asset names of everything it has drawn; the host
 * reads them after drawing a page and calls clear().
 *
 * @since 0.1.0
 */
interface DocumentIncluderInterface
{
    /**
     * Draws a stored document for the block that includes it.
     *
     * @param string $blockId
     *   The including block, named in any issue recorded about the inclusion itself.
     * @param Context $context
     *   The including block's context, so the included document sees what its new surroundings provide.
     *
     * @return string
     *   The document's output; empty when it is not stored, when it is already being drawn further up (a
     *   loop), or when the page has included more than the implementation allows.
     *
     * @since 0.1.0
     */
    public function include(string $documentId, string $blockId, RenderRequest $request, Context $context): string;

    /**
     * Lists the issues of included documents and of inclusions that were stopped, since the last clear().
     *
     * @return list<Issue>
     *
     * @since 0.1.0
     */
    public function issues(): array;

    /**
     * Lists the asset names blocks in included documents need, each once, since the last clear().
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    public function assets(): array;

    /**
     * Forgets the collected issues and assets, so the next page starts empty.
     *
     * @since 0.1.0
     */
    public function clear(): void;
}
