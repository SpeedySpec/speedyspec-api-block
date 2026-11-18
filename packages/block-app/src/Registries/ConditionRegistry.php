<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use SpeedySpec\Block\Domain\Contracts\ConditionInterface;
use SpeedySpec\Block\Domain\Contracts\ConditionRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;

/**
 * Holds the conditions visibility rules may name, checking each one's arguments schema as it is added.
 *
 * @since 0.1.0
 */
class ConditionRegistry implements ConditionRegistryInterface
{
    /**
     * @var array<string, ConditionInterface>
     */
    private array $entries = [];

    /**
     * @since 0.1.0
     */
    public function __construct(private readonly SchemaValidatorInterface $validator)
    {
    }

    public function add(ConditionInterface $condition): void
    {
        $this->validator->assertSupported($condition->argumentsSchema());

        $this->entries[$condition->name()] = $condition;
    }

    public function remove(string $name): void
    {
        unset($this->entries[$name]);
    }

    public function get(string $name): ?ConditionInterface
    {
        return $this->entries[$name] ?? null;
    }

    public function all(): array
    {
        return array_values($this->entries);
    }
}
