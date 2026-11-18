<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Output;

use SpeedySpec\Block\Domain\Contracts\OutputEscaperInterface;
use SpeedySpec\Block\Domain\Contracts\OutputEscaperRegistryInterface;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedTargetException;

/**
 * Keeps escapers in memory, keyed by the target each one names.
 *
 * @since 0.1.0
 */
class OutputEscaperRegistry implements OutputEscaperRegistryInterface
{
    /**
     * @var array<string, OutputEscaperInterface>
     */
    private array $escapers = [];

    public function add(OutputEscaperInterface $escaper): void
    {
        $this->escapers[$escaper->target()] = $escaper;
    }

    public function get(string $target): OutputEscaperInterface
    {
        if (!isset($this->escapers[$target]))
        {
            throw new UnsupportedTargetException(sprintf('No output escaper is registered for the "%s" target.', $target));
        }

        return $this->escapers[$target];
    }

    public function has(string $target): bool
    {
        return isset($this->escapers[$target]);
    }
}
