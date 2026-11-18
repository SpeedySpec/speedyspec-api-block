<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidCollectionException;

/**
 * The one rule for record collection names, shared so every record store accepts and refuses the same names.
 *
 * @since 0.1.0
 */
class CollectionName
{
    /**
     * Checks a collection name is lower-case letters, digits and hyphens, starting with a letter, at most 64 long.
     *
     * The rule is narrow on purpose: a store may use the name as a directory or part of a key without escaping it.
     *
     * @throws InvalidCollectionException
     *   When the name does not fit the rule.
     *
     * @since 0.1.0
     */
    public static function assert(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,63}\z/', $name) !== 1)
        {
            throw new InvalidCollectionException(sprintf(
                '"%s" is not a valid collection name; expected lower-case letters, digits and hyphens.',
                $name,
            ));
        }
    }
}
