<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Declares an extension that adds its own settings to blocks of other types, such as visibility rules.
 *
 * @since 0.1.0
 */
class Decorator
{
    /**
     * @param string $namespace
     *   A `vendor/name` key, validated like a block name.
     * @param array<string, mixed> $settingsSchema
     *   Schema for the extension's settings.
     * @param list<string>|null $appliesTo
     *   Block type names it extends, or null for every type.
     * @param array<string, mixed>|null $viewSchema
     *   Schema for the extension's resolved view.
     *
     * @throws \SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException
     *   When the namespace is not a valid `vendor/name`.
     */
    public function __construct(
        public readonly string $namespace,
        public readonly array $settingsSchema,
        public readonly ?array $appliesTo = null,
        public readonly ?array $viewSchema = null,
    )
    {
        BlockName::fromString($namespace);
    }

    /**
     * Decides whether the decorator extends blocks of the given type.
     *
     * @param BlockName $type
     *   The block type to test.
     *
     * @since 0.1.0
     */
    public function appliesTo(BlockName $type): bool
    {
        return $this->appliesTo === null || in_array($type->toString(), $this->appliesTo, true);
    }
}
