<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidSettingPathException;

/**
 * Addresses one setting of a block or of a decorator's extension, so bindings can target nested values.
 *
 * `key.sub` addresses a block setting; `@vendor/decorator:key.sub` addresses a decorator setting.
 *
 * @since 0.1.0
 */
class SettingPath
{
    /**
     * @param list<string> $segments
     */
    private function __construct(private readonly ?string $namespace, private readonly array $segments)
    {
    }

    /**
     * Parses a setting path.
     *
     * @param string $path
     *   The raw path.
     *
     * @throws InvalidSettingPathException
     *   When a segment is empty or the namespace is malformed.
     *
     * @since 0.1.0
     */
    public static function fromString(string $path): self
    {
        $namespace = null;
        $rest = $path;

        if (str_starts_with($path, '@'))
        {
            $colon = strpos($path, ':');

            if ($colon === false)
            {
                throw new InvalidSettingPathException(sprintf('"%s" is missing the ":" after its namespace.', $path));
            }

            $namespace = substr($path, 1, $colon - 1);
            $rest = substr($path, $colon + 1);

            if (preg_match('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*\z/', $namespace) !== 1)
            {
                throw new InvalidSettingPathException(sprintf('"%s" has a malformed namespace.', $path));
            }
        }

        $segments = explode('.', $rest);

        foreach ($segments as $segment)
        {
            if (preg_match('/^[A-Za-z0-9_-]+\z/', $segment) !== 1)
            {
                throw new InvalidSettingPathException(sprintf('"%s" has an empty or malformed segment.', $path));
            }
        }

        return new self($namespace, $segments);
    }

    /**
     * Names the decorator the path belongs to, or null for a block's own setting.
     *
     * @since 0.1.0
     */
    public function namespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Lists the keys to follow from the settings root.
     *
     * @return list<string>
     *
     * @since 0.1.0
     */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * Formats the path back to its string syntax.
     *
     * @since 0.1.0
     */
    public function toString(): string
    {
        $path = implode('.', $this->segments);

        return $this->namespace === null ? $path : '@' . $this->namespace . ':' . $path;
    }
}
