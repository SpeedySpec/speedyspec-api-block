<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * The resolved, render-ready form of a block: its view values and decorator extensions.
 *
 * @since 0.1.0
 */
class BlockView
{
    /**
     * Builds a view from its parts.
     *
     * @param array<string, mixed> $values
     *   The block's own view values.
     * @param array<string, array<string, mixed>> $extensions
     *   Maps a decorator namespace to its view values.
     * @param AdminContext|null $admin
     *   Editor values a behaviour hands over in the admin; the resolver moves them to {@link ResolvedBlock::$admin},
     *   so a view stored on a resolved block always has none.
     * @param list<CurrentItem>|null $childPasses
     *   Null means the children are produced once; otherwise one pass per item.
     */
    public function __construct(
        public readonly array $values,
        public readonly array $extensions = [],
        public readonly ?AdminContext $admin = null,
        public readonly ?array $childPasses = null,
    )
    {
    }

    /**
     * Copies the view with one decorator's values set or replaced.
     *
     * @param array<string, mixed> $values
     *   The decorator's view values.
     *
     * @since 0.1.0
     */
    public function withExtension(string $namespace, array $values): self
    {
        $extensions = $this->extensions;
        $extensions[$namespace] = $values;

        return new self($this->values, $extensions, $this->admin, $this->childPasses);
    }

    /**
     * Copies the view with its editor information set or cleared.
     *
     * @since 0.1.0
     */
    public function withAdmin(?AdminContext $admin): self
    {
        return new self($this->values, $this->extensions, $admin, $this->childPasses);
    }
}
