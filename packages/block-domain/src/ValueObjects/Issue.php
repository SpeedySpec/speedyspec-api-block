<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One problem found while resolving a block, recorded instead of thrown so the rest of the tree still resolves.
 *
 * @since 0.1.0
 */
class Issue
{
    /**
     * Records one problem.
     *
     * @param string $blockId
     *   The block the problem belongs to.
     * @param IssueKind $kind
     *   What went wrong.
     * @param string $message
     *   A human-readable explanation.
     * @param string|null $path
     *   The setting path involved, such as `label` or `@acme/vis:hide`, or null when no single setting is at fault.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $blockId,
        public readonly IssueKind $kind,
        public readonly string $message,
        public readonly ?string $path = null,
    )
    {
    }
}
