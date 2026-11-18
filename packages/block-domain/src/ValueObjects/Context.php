<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * The values a block shares with its descendants, plus the repeater item currently in scope.
 *
 * Immutable; every change returns a new context so siblings never see each other's values.
 *
 * @since 0.1.0
 */
class Context
{
    /**
     * @param array<string, mixed> $values
     *   Maps a namespaced key to its value.
     */
    private function __construct(
        private readonly array $values,
        private readonly ?CurrentItem $currentItem,
    )
    {
    }

    /**
     * Creates a context with no values and no current item.
     *
     * @since 0.1.0
     */
    public static function empty(): self
    {
        return new self([], null);
    }

    /**
     * Copies the context with one value set or replaced.
     *
     * @since 0.1.0
     */
    public function with(string $key, mixed $value): self
    {
        return new self([...$this->values, $key => $value], $this->currentItem);
    }

    /**
     * Tells whether a key is present, even when its value is null.
     *
     * @since 0.1.0
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Reads a value; an absent key yields null.
     *
     * @since 0.1.0
     */
    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * Copies the context keeping only the listed keys, so a block sees just what it declared in `uses`.
     *
     * The current item is always kept.
     *
     * @param list<string> $keys
     *   The keys to keep.
     *
     * @since 0.1.0
     */
    public function only(array $keys): self
    {
        return new self(array_intersect_key($this->values, array_flip($keys)), $this->currentItem);
    }

    /**
     * Copies the context with the current item set or cleared.
     *
     * @since 0.1.0
     */
    public function withCurrentItem(?CurrentItem $item): self
    {
        return new self($this->values, $item);
    }

    /**
     * Gets the repeater item in scope, if any.
     *
     * @since 0.1.0
     */
    public function currentItem(): ?CurrentItem
    {
        return $this->currentItem;
    }

    /**
     * Lists every value by key, excluding the current item.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function all(): array
    {
        return $this->values;
    }
}
