<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

/**
 * Keeps block types in memory and refuses ones whose schemas or examples are broken, so bad types fail at
 * registration instead of at render time.
 *
 * @since 0.1.0
 */
class BlockTypeRegistry implements BlockTypeRegistryInterface
{
    /**
     * @var array<string, BlockType>
     */
    private array $types = [];

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
    public function add(BlockType $type): void
    {
        $this->validator->assertSupported($type->settingsSchema, allowPrivate: true);

        if ($type->viewSchema !== null)
        {
            $this->validator->assertSupported($type->viewSchema);
        }

        foreach ($type->examples as $index => $example)
        {
            $result = $this->validator->validate(
                $this->validator->withDefaults($example, $type->settingsSchema),
                $type->settingsSchema,
            );

            if (!$result->isValid())
            {
                $errors = $result->errors();

                throw new InvalidBlockTypeException(sprintf(
                    'Block type "%s" example %d does not match its settings schema: %s',
                    $type->name->toString(),
                    $index,
                    $errors === [] ? 'it is invalid.' : $errors[0]->message,
                ));
            }
        }

        $this->types[$type->name->toString()] = $type;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function remove(BlockName $name): void
    {
        unset($this->types[$name->toString()]);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function has(BlockName $name): bool
    {
        return isset($this->types[$name->toString()]);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function get(BlockName $name): ?BlockType
    {
        return $this->types[$name->toString()] ?? null;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function all(): array
    {
        return array_values($this->types);
    }
}
