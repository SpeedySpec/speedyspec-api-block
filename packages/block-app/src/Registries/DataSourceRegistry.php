<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;

/**
 * Keeps data sources in memory and refuses ones whose schemas are unsupported.
 *
 * @since 0.1.0
 */
class DataSourceRegistry implements DataSourceRegistryInterface
{
    /**
     * @var array<string, DataSourceInterface>
     */
    private array $sources = [];

    /**
     * Creates an empty registry.
     *
     * @param SchemaValidatorInterface $validator
     *   Checks schemas at registration.
     */
    public function __construct(private readonly SchemaValidatorInterface $validator)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function add(DataSourceInterface $source): void
    {
        $this->validator->assertSupported($source->argumentsSchema());
        $this->validator->assertSupported($source->valueSchema());

        $this->sources[$source->name()] = $source;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function remove(string $name): void
    {
        unset($this->sources[$name]);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function get(string $name): ?DataSourceInterface
    {
        return $this->sources[$name] ?? null;
    }
}
