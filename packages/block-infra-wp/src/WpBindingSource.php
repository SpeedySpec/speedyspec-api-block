<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Infra\WP\Ports\WpBindingsInterface;

/**
 * Offers one WordPress block bindings source as a data source, under the same name.
 *
 * @since 0.1.0
 */
class WpBindingSource implements DataSourceInterface
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly WpBindingsInterface $bindings,
        private readonly string $name,
    )
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function argumentsSchema(): array
    {
        // WordPress sources do not declare what arguments they take.
        return ['type' => 'object'];
    }

    public function valueSchema(): array
    {
        return [];
    }

    public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult
    {
        $value = $this->bindings->value($this->name, $arguments, $context->all());

        return $value === null ? DataSourceResult::none() : DataSourceResult::value($value);
    }
}
