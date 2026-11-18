<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Supplies values that bindings can read into block settings, such as a post title or a query result.
 *
 * @since 0.1.0
 */
interface DataSourceInterface
{
    /**
     * Identifies the source in bindings.
     *
     * @since 0.1.0
     */
    public function name(): string;

    /**
     * Describes the arguments a binding passes to the source.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function argumentsSchema(): array;

    /**
     * Describes the shape of the value the source produces.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function valueSchema(): array;

    /**
     * Produces the value for a binding.
     *
     * @param array<string, mixed> $arguments
     *   The binding's arguments.
     * @param Context $context
     *   The context inherited from ancestors.
     * @param Mode $mode
     *   Where the value is resolved.
     *
     * @since 0.1.0
     */
    public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult;
}
