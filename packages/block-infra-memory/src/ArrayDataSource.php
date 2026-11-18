<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Memory;

use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Serves values from a plain array keyed by the binding's "key" argument, for tests and fixed content.
 *
 * @since 0.1.0
 */
class ArrayDataSource implements DataSourceInterface
{
    /**
     * @param string $name
     *   The source name used in bindings.
     * @param array<string, mixed> $values
     *   The initial values by key.
     * @param array<string, mixed> $valueSchema
     *   The schema of the values; empty means any value.
     */
    public function __construct(
        private readonly string $name,
        private array $values = [],
        private readonly array $valueSchema = [],
    )
    {
    }

    /**
     * Adds or replaces the value served under a key.
     *
     * @param string $key
     *   The key bindings pass as their "key" argument.
     * @param mixed $value
     *   The value to serve.
     *
     * @since 0.1.0
     */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /**
     * @since 0.1.0
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @since 0.1.0
     */
    public function argumentsSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['key'],
            'properties' => ['key' => ['type' => 'string']],
        ];
    }

    /**
     * @since 0.1.0
     */
    public function valueSchema(): array
    {
        return $this->valueSchema;
    }

    /**
     * Ignores context and mode: the same key always yields the same value.
     *
     * @since 0.1.0
     */
    public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult
    {
        $key = $arguments['key'] ?? null;

        if (!is_string($key) || !array_key_exists($key, $this->values))
        {
            return DataSourceResult::none();
        }

        return DataSourceResult::value($this->values[$key]);
    }
}
