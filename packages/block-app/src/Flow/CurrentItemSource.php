<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Flow;

use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Supplies a field of the item a repeating block is currently on, so a repeated block can show it.
 *
 * @since 0.1.0
 */
class CurrentItemSource implements DataSourceInterface
{
    public const string NAME = 'core/current-item';

    public function name(): string
    {
        return self::NAME;
    }

    public function argumentsSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'field' => [
                    'type' => 'string',
                    'default' => '',
                    'title' => 'Field',
                    'description' => 'A dotted path into the item, such as "address.city". Empty for the item itself.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function valueSchema(): array
    {
        return [];
    }

    public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult
    {
        $current = $context->currentItem();

        if ($current === null)
        {
            return DataSourceResult::none();
        }

        [$found, $value] = ItemPath::read($current->item, (string) ($arguments['field'] ?? ''));

        return $found ? DataSourceResult::value($value) : DataSourceResult::none();
    }
}
