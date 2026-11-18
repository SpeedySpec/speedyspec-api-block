<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Says which parents a block type may sit under, so a type can restrict where it is placed.
 *
 * @since 0.1.0
 */
class ParentPolicy
{
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
     * @since 0.1.0
     */
    public static function fromArray(mixed $value): self
    {
        if (!is_array($value))
        {
            return self::any();
        }

        return self::only(...array_map(fn ($n): BlockName => BlockName::fromString((string) $n), array_values($value)));
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
