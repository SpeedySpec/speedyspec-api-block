<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Entities;

/**
 * A page, post or template: a tree of blocks with an identity and a serialisation format version.
 *
 * @since 0.1.0
 */
class Document
{
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly BlockTree $tree,
        public readonly int $formatVersion = 1,
    ) {
    }

    /**
     * Copies the document with a different tree.
     *
     * @since 0.1.0
     */
    public function withTree(BlockTree $tree): self
    {
        return new self($this->id, $this->kind, $tree, $this->formatVersion);
    }
}
