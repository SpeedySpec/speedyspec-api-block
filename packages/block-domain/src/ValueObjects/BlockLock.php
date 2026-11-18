<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Entities\Block;

/**
 * Whether a block is kept from being moved or removed, read from its `core/lock` extension.
 *
 * A lock prevents accidents, not access: it is kept as an extension, so the document format does not change and
 * any builder shows it like any other decorator, and anyone who may edit the block may unlock it.
 *
 * @since 0.1.0
 */
class BlockLock
{
    public const string NAMESPACE = 'core/lock';

    /**
     * @since 0.1.0
     */
    public function __construct(
        public readonly bool $move = false,
        public readonly bool $remove = false,
    )
    {
    }

    /**
     * Reads a block's lock; only `true` locks, so a stray value never traps a block.
     *
     * @since 0.1.0
     */
    public static function of(Block $block): self
    {
        $lock = $block->extensions[self::NAMESPACE] ?? [];

        return new self(($lock['move'] ?? null) === true, ($lock['remove'] ?? null) === true);
    }

    /**
     * @since 0.1.0
     */
    public function isLocked(): bool
    {
        return $this->move || $this->remove;
    }

    /**
     * Describes the lock's settings for every block type, so builders draw them without knowing about locks.
     *
     * @since 0.1.0
     */
    public static function decorator(): Decorator
    {
        return new Decorator(self::NAMESPACE, [
            'type' => 'object',
            'properties' => [
                'move' => ['type' => 'boolean', 'default' => false, 'title' => 'Prevent moving'],
                'remove' => ['type' => 'boolean', 'default' => false, 'title' => 'Prevent removing'],
            ],
        ]);
    }
}
