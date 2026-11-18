<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Editor-only information about a resolved block, kept apart from the view so it never reaches the front end.
 *
 * @since 0.1.0
 */
class AdminContext
{
    /**
     * @param array<string, mixed> $values
     *   Extra values for the editor to show.
     * @param list<mixed> $issues
     *   The problems found while resolving the block.
     * @param array<string, array{source: string, used: string}> $bindings
     *   Maps a setting-path string to the data source and which value won:
     *   `source`, `fallback`, `stored`, `default` or `none`.
     * @param bool $hiddenOnFront
     *   Whether a decorator hides the block on the front end, so the editor can still show it.
     */
    public function __construct(
        public readonly array $values = [],
        public readonly array $issues = [],
        public readonly array $bindings = [],
        public readonly bool $hiddenOnFront = false,
    )
    {
    }

    /**
     * Copies the context changing only the named fields.
     *
     * @since 0.1.0
     */
    public function with(
        ?array $values = null,
        ?array $issues = null,
        ?array $bindings = null,
        ?bool $hiddenOnFront = null,
    ): self
    {
        return new self(
            $values ?? $this->values,
            $issues ?? $this->issues,
            $bindings ?? $this->bindings,
            $hiddenOnFront ?? $this->hiddenOnFront,
        );
    }
}
