<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * What drawing a document produced: the output, what went wrong, and what the page needs to load.
 *
 * @since 0.1.0
 */
class RenderResult
{
    /**
     * Keeps the resolved document beside the output so a caller can inspect any block without resolving again.
     *
     * @param list<Issue> $issues
     *   Every issue from resolving, followed by every issue from drawing, in tree order.
     * @param list<string> $assets
     *   Unique asset names the drawn blocks declared, in order of first appearance.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $output,
        public readonly ResolvedDocument $resolved,
        public readonly array $issues,
        public readonly array $assets,
    )
    {
    }

    /**
     * Tells whether anything went wrong while resolving or drawing.
     *
     * @since 0.1.0
     */
    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }
}
