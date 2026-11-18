<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Presentation;

use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidStyleException;
use SpeedySpec\Block\Domain\ValueObjects\StyleRule;

/**
 * Keeps the site's design tokens in memory.
 *
 * @since 0.1.0
 */
class DesignTokenRegistry implements DesignTokenRegistryInterface
{
    public const string NAME_PATTERN = '[a-z][a-z0-9-]*(\.[a-z0-9-]+)*';

    /**
     * @var array<string, string>
     */
    private array $tokens = [];

    public function set(string $name, string $value): void
    {
        self::assertToken($name, $value);

        $this->tokens[$name] = $value;
    }

    /**
     * Checks a token the way every registry must, so a token can never write outside its custom property.
     *
     * @throws InvalidStyleException
     *   When the name is malformed or the value could break out of the stylesheet.
     *
     * @since 0.1.0
     */
    public static function assertToken(string $name, string $value): void
    {
        if (preg_match('/^' . self::NAME_PATTERN . '\z/', $name) !== 1)
        {
            throw new InvalidStyleException(sprintf('"%s" is not a usable token name.', $name));
        }

        StyleRule::assertValue($value, $name);
    }

    public function remove(string $name): void
    {
        unset($this->tokens[$name]);
    }

    public function get(string $name): ?string
    {
        return $this->tokens[$name] ?? null;
    }

    public function all(): array
    {
        return $this->tokens;
    }

    /**
     * Names the custom property a token is written as, such as `--color-primary` for `color.primary`.
     *
     * @since 0.1.0
     */
    public static function property(string $name): string
    {
        return '--' . str_replace('.', '-', $name);
    }
}
