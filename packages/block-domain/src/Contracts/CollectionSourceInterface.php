<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * A named supplier of a list of items for a repeating block, such as recent posts or the rows of a table.
 *
 * A data source supplies one value for one setting; a collection source supplies the items a block repeats its
 * children over. Each item becomes the current item for one pass, where bindings and conditions can read it.
 *
 * @since 0.1.0
 */
interface CollectionSourceInterface
{
    /**
     * Names the source as lower-case `vendor/name`.
     *
     * @since 0.1.0
     */
    public function name(): string;

    /**
     * Describes the arguments a repeating block may pass, such as a filter or an order.
     *
     * @return array<string, mixed>
     *   A schema in the supported subset.
     *
     * @since 0.1.0
     */
    public function argumentsSchema(): array;

    /**
     * Supplies the items to repeat over.
     *
     * @param array<string, mixed> $arguments
     *   Already checked against the arguments schema.
     * @param Context $context
     *   The context the repeating block inherits; inside another repeating block it carries that block's
     *   current item, so a nested collection can depend on it.
     *
     * @return list<mixed>
     *   Items as data, never markup; an item is usually a map of field names to values.
     *
     * @since 0.1.0
     */
    public function items(array $arguments, Context $context, Mode $mode): array;
}
