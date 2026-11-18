<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Flow;

use SpeedySpec\Block\Domain\Contracts\ConditionInterface;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Holds when a field of the current item equals a given value, so a repeated block can differ from item to item.
 *
 * @since 0.1.0
 */
class CurrentItemIs implements ConditionInterface
{
    public const string NAME = 'core/current-item-is';

    public function name(): string
    {
        return self::NAME;
    }

    public function argumentsSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field'],
            'properties' => [
                'field' => ['type' => 'string', 'title' => 'Field'],
                'value' => [
                    'type' => ['string', 'integer', 'number', 'boolean', 'null'],
                    'default' => null,
                    'title' => 'Value',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function holds(array $arguments, Context $context, Mode $mode): bool
    {
        $current = $context->currentItem();

        if ($current === null)
        {
            return false;
        }

        [$found, $value] = ItemPath::read($current->item, (string) $arguments['field']);

        // Compared without type juggling: "0" and false must not be taken for each other in stored content.
        return $found && $value === ($arguments['value'] ?? null);
    }
}
