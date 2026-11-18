<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Entities;

use InvalidArgumentException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

/**
 * One node of a document: a typed block with settings, decorator extensions, bindings and children.
 *
 * Immutable; every change returns a new block so earlier versions stay valid.
 *
 * @since 0.1.0
 */
class Block
{
    /**
     * @param array<string, mixed> $settings
     *   The block's own settings, stored as given.
     * @param array<string, array<string, mixed>> $extensions
     *   Maps a decorator namespace to its settings.
     * @param array<string, Binding> $bindings
     *   Maps a setting-path string to its binding.
     * @param list<Block> $children
     *   The nested blocks.
     *
     * @throws InvalidArgumentException
     *   When the id is empty or a child is not a block.
     */
    public function __construct(
        public readonly string $id,
        public readonly BlockName $type,
        public readonly int $typeVersion = 1,
        public readonly array $settings = [],
        public readonly array $extensions = [],
        public readonly array $bindings = [],
        public readonly array $children = [],
    ) {
        if ($id === '')
        {
            throw new InvalidArgumentException('A block id must not be empty.');
        }

        foreach ($children as $child)
        {
            if (!$child instanceof self)
            {
                throw new InvalidArgumentException('Block children must be Block instances.');
            }
        }
    }

    /**
     * Copies the block under a new id.
     *
     * @since 0.1.0
     */
    public function withId(string $id): self
    {
        return $this->copy(id: $id);
    }

    /**
     * Copies the block recording a different type version.
     *
     * @since 0.1.0
     */
    public function withTypeVersion(int $typeVersion): self
    {
        return $this->copy(typeVersion: $typeVersion);
    }

    /**
     * Copies the block with its settings replaced.
     *
     * @param array<string, mixed> $settings
     *   The new settings.
     *
     * @since 0.1.0
     */
    public function withSettings(array $settings): self
    {
        return $this->copy(settings: $settings);
    }

    /**
     * Copies the block with one decorator's extension settings set or replaced.
     *
     * @param array<string, mixed> $settings
     *   The extension settings.
     *
     * @since 0.1.0
     */
    public function withExtension(string $namespace, array $settings): self
    {
        return $this->copy(extensions: [...$this->extensions, $namespace => $settings]);
    }

    /**
     * Copies the block with a binding set; an existing binding for the path is replaced in place.
     *
     * @since 0.1.0
     */
    public function withBinding(SettingPath $path, Binding $binding): self
    {
        return $this->copy(bindings: [...$this->bindings, $path->toString() => $binding]);
    }

    /**
     * Copies the block without the binding for the path.
     *
     * @since 0.1.0
     */
    public function withoutBinding(SettingPath $path): self
    {
        $bindings = $this->bindings;
        unset($bindings[$path->toString()]);

        return $this->copy(bindings: $bindings);
    }

    /**
     * Copies the block with its whole child list replaced.
     *
     * @since 0.1.0
     */
    public function withChildren(self ...$children): self
    {
        return $this->copy(children: array_values($children));
    }

    private function copy(
        ?string $id = null,
        ?int $typeVersion = null,
        ?array $settings = null,
        ?array $extensions = null,
        ?array $bindings = null,
        ?array $children = null,
    ): self {
        return new self(
            $id ?? $this->id,
            $this->type,
            $typeVersion ?? $this->typeVersion,
            $settings ?? $this->settings,
            $extensions ?? $this->extensions,
            $bindings ?? $this->bindings,
            $children ?? $this->children,
        );
    }
}
