<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;

/**
 * A set of style declarations for one block, at every width or from one breakpoint upward.
 *
 * The rule checks itself: a property or value that could end its declaration, open another rule, or load
 * anything from elsewhere cannot be constructed, so no collector or output can write one into a stylesheet.
 *
 * @since 0.1.0
 */
class StyleRule
{
    private const array FUNCTIONS = ['var', 'calc', 'min', 'max', 'clamp', 'rgb', 'rgba', 'hsl', 'hsla'];

    /**
     * @param array<string, string> $declarations
     *   Maps a lower-case property name to its value.
     * @param string|null $breakpoint
     *   The breakpoint the declarations apply from; null for every width.
     *
     * @throws InvalidStyleException
     *   When there are no declarations, or a property, value or breakpoint name is not safe.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly array $declarations,
        public readonly ?string $breakpoint = null,
    )
    {
        if ($declarations === [])
        {
            throw new InvalidStyleException('A style rule needs at least one declaration.');
        }

        if ($breakpoint !== null && !Breakpoint::isName($breakpoint))
        {
            throw new InvalidStyleException(sprintf('"%s" is not a usable breakpoint name.', $breakpoint));
        }

        foreach ($declarations as $property => $value)
        {
            if (!is_string($property) || preg_match('/^-{0,2}[a-z][a-z0-9-]*\z/', $property) !== 1)
            {
                throw new InvalidStyleException(sprintf('"%s" is not a safe style property.', (string) $property));
            }

            self::assertValue($value, $property);
        }
    }

    /**
     * Checks a value is safe to write after a property name in a stylesheet.
     *
     * Only letters, digits, spaces and a few symbols are allowed, and only the listed functions, which rules out
     * ending the declaration, comments, escapes, quotes and loading a URL.
     *
     * @param string $subject
     *   Names what the value is for, in the error message.
     *
     * @throws InvalidStyleException
     *   When the value is not text, is empty, is over 200 characters, or holds anything outside the allowed set.
     *
     * @since 0.1.0
     */
    public static function assertValue(mixed $value, string $subject): void
    {
        $safe = is_string($value)
            && $value !== ''
            && strlen($value) <= 200
            && preg_match('/^[A-Za-z0-9 .,%#()+*\/_-]+\z/', $value) === 1
            && !str_contains($value, '/*')
            && !str_contains($value, '*/');

        if ($safe)
        {
            preg_match_all('/([A-Za-z_-][A-Za-z0-9_-]*)\s*\(/', $value, $found);

            foreach ($found[1] as $function)
            {
                $safe = $safe && in_array(strtolower($function), self::FUNCTIONS, true);
            }
        }

        if (!$safe)
        {
            throw new InvalidStyleException(sprintf('The value given for "%s" is not a safe style value.', $subject));
        }
    }
}
