<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;

/**
 * Says which block types a block may contain, so tree edits can be checked against the type's declaration.
 *
 * @since 0.1.0
 */
class ChildrenPolicy
{
    /**
     * Describes the accepted serialised forms, for error messages.
     */
    private const SHAPE = 'true, false, null or a list of block names';

    /**
     * @param bool|list<string> $rule
     *   True for any, false for none, or the allowed child names.
     */
    private function __construct(private readonly bool|array $rule)
    {
    }

    /**
     * Creates a policy that allows no children.
     *
     * @since 0.1.0
     */
    public static function none(): self
    {
        return new self(false);
    }

    /**
     * Creates a policy that allows any child.
     *
     * @since 0.1.0
     */
    public static function any(): self
    {
        return new self(true);
    }

    /**
     * Creates a policy that allows only the listed child types.
     *
     * @param BlockName ...$names
     *   The permitted child types.
     *
     * @since 0.1.0
     */
    public static function only(BlockName ...$names): self
    {
        return new self(array_values(array_map(fn (BlockName $n): string => $n->toString(), $names)));
    }

    /**
     * Decides whether a child of the given type is permitted.
     *
     * @param BlockName $child
     *   The type of the prospective child.
     *
     * @since 0.1.0
     */
    public function allows(BlockName $child): bool
    {
        return is_array($this->rule) ? in_array($child->toString(), $this->rule, true) : $this->rule;
    }

    /**
     * Builds a policy from its serialised form: false, null or an empty list is none, true is any.
     *
     * @param mixed $value
     *   The serialised policy.
     *
     * @throws InvalidBlockTypeException
     *   When the value is not true, false, null or a list of block names, or holds an invalid name.
     *
     * @since 0.1.0
     */
    public static function fromArray(mixed $value): self
    {
        if ($value === true)
        {
            return self::any();
        }

        if ($value === false || $value === null || $value === [])
        {
            return self::none();
        }

        return self::only(...self::names($value, 'children'));
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
     * Serialises the policy as a boolean or a list of block type names.
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
