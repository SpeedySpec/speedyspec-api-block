<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;

/**
 * A named screen width from which a responsive value's override applies.
 *
 * Breakpoints are mobile-first: a value's base applies at every width, and each override applies from its
 * breakpoint's width upward.
 *
 * @since 0.1.0
 */
class Breakpoint
{
    public const string BASE = 'base';

    /**
     * @param string $name
     *   Lower-case letters, digits and hyphens; `base` is reserved for the value that applies everywhere.
     * @param int $minWidth
     *   The width in pixels the breakpoint starts at.
     *
     * @throws InvalidStyleException
     *   When the name is malformed or reserved, or the width is not positive.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly string $name,
        public readonly int $minWidth,
    )
    {
        if (!self::isName($name))
        {
            throw new InvalidStyleException(sprintf('"%s" is not a usable breakpoint name.', $name));
        }

        if ($minWidth < 1)
        {
            throw new InvalidStyleException(sprintf('Breakpoint "%s" needs a width of at least 1 pixel.', $name));
        }
    }

    /**
     * Tells whether a string could name a breakpoint, so stored keys are checked by one rule everywhere.
     *
     * @since 0.1.0
     */
    public static function isName(string $name): bool
    {
        return $name !== self::BASE && preg_match('/^[a-z][a-z0-9-]{0,31}\z/', $name) === 1;
    }
}
