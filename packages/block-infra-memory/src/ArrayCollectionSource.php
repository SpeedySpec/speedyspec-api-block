<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\Contracts\CollectionSourceInterface;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * A collection of items given to it, for repeating blocks in tests and on hosts with fixed lists.
 *
 * @since 0.1.0
 */
class ArrayCollectionSource implements CollectionSourceInterface
{
    /**
     * @param list<mixed> $items
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly string $name,
        private readonly array $items,
    )
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function argumentsSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function items(array $arguments, Context $context, Mode $mode): array
    {
        return $this->items;
    }
}
