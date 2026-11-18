<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Holds one output per block type, mode and target, so a single block's output can be replaced on its own.
 *
 * @since 0.1.0
 */
interface BlockOutputRegistryInterface
{
    /**
     * Registers an output, replacing one already set for the same block type, mode and target.
     *
     * @since 0.1.0
     */
    public function set(
        BlockName $type,
        BlockOutputInterface $output,
        Mode $mode = Mode::Front,
        string $target = 'html',
    ): void;

    /**
     * Finds the output to draw a block type with.
     *
     * In admin mode a block type with no admin output is drawn with its front output. The reverse never happens:
     * an admin output may show things a visitor must not see.
     *
     * @return BlockOutputInterface|null
     *   Null when the block type has no usable output for the mode and target.
     *
     * @since 0.1.0
     */
    public function get(BlockName $type, Mode $mode, string $target = 'html'): ?BlockOutputInterface;

    /**
     * Tells whether an output is set for exactly this block type, mode and target, without falling back.
     *
     * @since 0.1.0
     */
    public function has(BlockName $type, Mode $mode, string $target = 'html'): bool;

    /**
     * Removes the output set for exactly this block type, mode and target, if there is one.
     *
     * @since 0.1.0
     */
    public function remove(BlockName $type, Mode $mode, string $target = 'html'): void;
}
