<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Building;

use SpeedySpec\Block\Domain\Contracts\BlockPreviewInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockPreview;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedDocument;

/**
 * Previews a block by resolving its whole document and drawing only the block's own subtree.
 *
 * @since 0.1.0
 */
class BlockPreviewer implements BlockPreviewInterface
{
    /**
     * Reuses the resolver and renderer, so a preview is drawn exactly as the full document would draw the block.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly DocumentResolverInterface $resolver,
        private readonly DocumentRendererInterface $renderer,
    )
    {
    }

    public function preview(
        Document $document,
        string $blockId,
        RenderRequest $request,
        ?Context $context = null,
    ): ?BlockPreview
    {
        $stored = $document->tree->find($blockId);

        if ($stored === null)
        {
            return null;
        }

        $resolved = $this->resolver->resolve($document, $request->mode, $context);
        $block = $resolved->find($blockId);

        $ids = array_fill_keys((new BlockTree($stored))->ids(), true);
        $issues = array_values(array_filter(
            $resolved->issues,
            static fn (Issue $issue): bool => isset($ids[$issue->blockId]),
        ));

        if ($block === null)
        {
            // Left out of the resolved tree, so there is nothing to draw; the reason, if it is the block's own,
            // is among its issues.
            return new BlockPreview($blockId, null, '', $issues, []);
        }
        $result = $this->renderer->renderResolved(
            new ResolvedDocument($document, $request->mode, [$block], $issues),
            $request,
        );

        return new BlockPreview($blockId, $block, $result->output, $result->issues, $result->assets);
    }
}
