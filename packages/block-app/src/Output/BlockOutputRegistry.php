<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Output;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Keeps block outputs in memory, keyed by target, mode and block type.
 *
 * @since 0.1.0
 */
class BlockOutputRegistry implements BlockOutputRegistryInterface
{
    /**
     * @var array<string, array<string, array<string, BlockOutputInterface>>>
     */
    private array $outputs = [];

    public function set(
        BlockName $type,
        BlockOutputInterface $output,
        Mode $mode = Mode::Front,
        string $target = 'html',
    ): void
    {
        $this->outputs[$target][$mode->value][$type->toString()] = $output;
    }

    public function get(BlockName $type, Mode $mode, string $target = 'html'): ?BlockOutputInterface
    {
        $name = $type->toString();
        $exact = $this->outputs[$target][$mode->value][$name] ?? null;

        if ($exact !== null || $mode === Mode::Front)
        {
            return $exact;
        }

        return $this->outputs[$target][Mode::Front->value][$name] ?? null;
    }

    public function has(BlockName $type, Mode $mode, string $target = 'html'): bool
    {
        return isset($this->outputs[$target][$mode->value][$type->toString()]);
    }

    public function remove(BlockName $type, Mode $mode, string $target = 'html'): void
    {
        unset($this->outputs[$target][$mode->value][$type->toString()]);
    }
}
