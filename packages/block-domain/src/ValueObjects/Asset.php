<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidAssetException;

/**
 * A script or style a block's output needs on the page, by name, with what it must load after.
 *
 * Its source is inline content, a URL, or a file inside a package. A package knows only its own files' paths, so
 * for a file the host decides the URL, through {@link \SpeedySpec\Block\Domain\Contracts\AssetLocatorInterface}.
 * Each check here exists so that no asset can point outside the package it ships in, or at a script URL.
 *
 * @since 0.1.0
 */
class Asset
{
    private const string NAME = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*\z/';

    /**
     * @param 'inline'|'url'|'file' $source
     * @param list<string> $dependencies
     *   Names of assets that must load first.
     *
     * @since 0.1.0
     */
    private function __construct(
        public readonly string $name,
        public readonly AssetKind $kind,
        public readonly string $source,
        public readonly string $content,
        public readonly string $url,
        public readonly string $package,
        public readonly string $path,
        public readonly array $dependencies,
    )
    {
        self::assertName($name, 'asset name');

        foreach ($dependencies as $dependency)
        {
            self::assertName($dependency, 'dependency');

            if ($dependency === $name)
            {
                throw new InvalidAssetException(sprintf('Asset "%s" cannot depend on itself.', $name));
            }
        }
    }

    /**
     * Describes an asset written into the page as it is.
     *
     * @param list<string> $dependencies
     *
     * @throws InvalidAssetException
     *
     * @since 0.1.0
     */
    public static function inline(string $name, AssetKind $kind, string $content, array $dependencies = []): self
    {
        return new self($name, $kind, 'inline', $content, '', '', '', array_values($dependencies));
    }

    /**
     * Describes an asset loaded from a URL: `http` or `https`, or a path on the same site.
     *
     * @param list<string> $dependencies
     *
     * @throws InvalidAssetException
     *   When the URL is any other scheme, or starts with `//`, which would load from another site.
     *
     * @since 0.1.0
     */
    public static function url(string $name, AssetKind $kind, string $url, array $dependencies = []): self
    {
        $local = str_starts_with($url, '/') && !str_starts_with($url, '//');

        if (!$local && preg_match('#^https?://[^/\s]#i', $url) !== 1)
        {
            throw new InvalidAssetException(sprintf('Asset "%s" has a URL that cannot be loaded safely.', $name));
        }

        return new self($name, $kind, 'url', '', $url, '', '', array_values($dependencies));
    }

    /**
     * Describes an asset that is a file inside a package.
     *
     * @param string $path
     *   Relative to the package's directory, with forward slashes.
     * @param list<string> $dependencies
     *
     * @throws InvalidAssetException
     *   When the package name is malformed, or the path could leave the package's directory.
     *
     * @since 0.1.0
     */
    public static function file(
        string $name,
        AssetKind $kind,
        string $package,
        string $path,
        array $dependencies = [],
    ): self
    {
        self::assertName($package, 'package name');

        $segments = explode('/', $path);
        $unsafe = preg_match('#^[A-Za-z0-9._/-]+\z#', $path) !== 1
            || array_intersect($segments, ['', '.', '..']) !== [];

        if ($unsafe)
        {
            throw new InvalidAssetException(sprintf('Asset "%s" has a path that could leave its package.', $name));
        }

        return new self($name, $kind, 'file', '', '', $package, $path, array_values($dependencies));
    }

    private static function assertName(string $name, string $what): void
    {
        if (preg_match(self::NAME, $name) !== 1)
        {
            throw new InvalidAssetException(sprintf('"%s" is not a valid %s; expected "vendor/name".', $name, $what));
        }
    }
}
