<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;

/**
 * Says which parents a block type may sit under, so a type can restrict where it is placed.
 *
 * @since 0.1.0
 */
class ParentPolicy
{
    /**
     * Describes the accepted serialised forms, for error messages.
     */
    private const SHAPE = 'true, null or a list of block names';

    /**
     * @param bool|list<string> $rule
     *   True for any, or the allowed parent names.
     */
    private function __construct(private readonly bool|array $rule)
    {
    }

    /**
     * Creates a policy that allows any parent, including the document root.
     *
     * @since 0.1.0
     */
    public static function any(): self
    {
        return new self(true);
    }

    /**
     * Creates a policy that allows only the listed parent types, and never the document root.
     *
     * @param BlockName ...$names
     *   The permitted parent types.
     *
     * @since 0.1.0
     */
    public static function only(BlockName ...$names): self
    {
        return new self(array_values(array_map(fn (BlockName $n): string => $n->toString(), $names)));
    }

    /**
     * Decides whether the given parent is permitted.
     *
     * @param BlockName|null $parent
     *   The parent type, or null for the document root.
     *
     * @since 0.1.0
     */
    public function allows(?BlockName $parent): bool
    {
        if (!is_array($this->rule))
        {
            return true;
        }

        return $parent !== null && in_array($parent->toString(), $this->rule, true);
    }

    /**
     * Builds a policy from its serialised form: null or true is any, a list is only.
     *
     * @param mixed $value
     *   The serialised policy.
     *
     * @throws InvalidBlockTypeException
     *   When the value is not true, null or a list of block names, or holds an invalid name.
     *
     * @since 0.1.0
     */
    public static function fromArray(mixed $value): self
    {
        if ($value === true || $value === null)
        {
            return self::any();
        }

        return self::only(...self::names($value, 'parents'));
    }

    /**
     * Reads a serialised list of block names, refusing anything else so a bad type definition fails at load time.
     *
     * @return list<BlockName>
     *
     * @throws InvalidBlockTypeException
     */
    private static function names(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value))
        {
            throw new InvalidBlockTypeException(sprintf('The "%s" policy must be %s.', $field, self::SHAPE));
        }

        $names = [];

        foreach ($value as $name)
        {
            if (!is_string($name))
            {
                throw new InvalidBlockTypeException(sprintf('The "%s" policy must be %s.', $field, self::SHAPE));
            }

            try
            {
                $names[] = BlockName::fromString($name);
            }
            catch (InvalidBlockNameException $e)
            {
                throw new InvalidBlockTypeException(
                    sprintf('The "%s" policy holds an invalid block name: %s', $field, $e->getMessage()),
                    0,
                    $e,
                );
            }
        }

        return $names;
    }

    /**
     * Serialises the policy as true or a list of block type names.
     *
     * @return bool|list<string>
     *
     * @since 0.1.0
     */
    public function toArray(): bool|array
    {
        return $this->rule;
    }
}
