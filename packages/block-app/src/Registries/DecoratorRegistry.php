<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;

/**
 * Keeps decorators in memory and refuses ones whose schemas are unsupported.
 *
 * @since 0.1.0
 */
class DecoratorRegistry implements DecoratorRegistryInterface
{
    /**
     * @var array<string, Decorator>
     */
    private array $decorators = [];

    public function __construct(private readonly SchemaValidatorInterface $validator)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function add(Decorator $decorator): void
    {
        $this->validator->assertSupported($decorator->settingsSchema);

        if ($decorator->viewSchema !== null)
        {
            $this->validator->assertSupported($decorator->viewSchema);
        }

        $this->decorators[$decorator->namespace] = $decorator;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function remove(string $namespace): void
    {
        unset($this->decorators[$namespace]);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function get(string $namespace): ?Decorator
    {
        return $this->decorators[$namespace] ?? null;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function for(BlockName $type): array
    {
        return array_values(array_filter(
            $this->decorators,
            static fn (Decorator $decorator): bool => $decorator->appliesTo($type),
        ));
    }
}
