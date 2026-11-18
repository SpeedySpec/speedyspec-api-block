<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use SpeedySpec\Block\Domain\Contracts\CollectionSourceInterface;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;

/**
 * Holds the collection sources repeating blocks may name, checking each one's arguments schema as it is added.
 *
 * @since 0.1.0
 */
class CollectionSourceRegistry implements CollectionSourceRegistryInterface
{
    /**
     * @var array<string, CollectionSourceInterface>
     */
    private array $entries = [];

    /**
     * @since 0.1.0
     */
    public function __construct(private readonly SchemaValidatorInterface $validator)
    {
    }

    public function add(CollectionSourceInterface $source): void
    {
        $this->validator->assertSupported($source->argumentsSchema());

        $this->entries[$source->name()] = $source;
    }

    public function remove(string $name): void
    {
        unset($this->entries[$name]);
    }

    public function get(string $name): ?CollectionSourceInterface
    {
        return $this->entries[$name] ?? null;
    }

    public function all(): array
    {
        return array_values($this->entries);
    }
}
