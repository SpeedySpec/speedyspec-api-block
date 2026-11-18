<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Says which block types a block may contain, so tree edits can be checked against the type's declaration.
 *
 * @since 0.1.0
 */
class ChildrenPolicy
{
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
     * @since 0.1.0
     */
    public static function fromArray(mixed $value): self
    {
        if ($value === true)
        {
            return self::any();
        }

        if (!is_array($value) || $value === [])
        {
            return self::none();
        }

        return self::only(...array_map(fn ($n): BlockName => BlockName::fromString((string) $n), array_values($value)));
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
