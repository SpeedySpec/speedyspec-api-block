<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Builder;

use SpeedySpec\Block\Domain\Contracts\AssetLocatorInterface;

/**
 * Serves the asset files of the packages a host loads, from one address per package.
 *
 * A host without a build step can hand this to the asset registry as its locator and answer the same addresses
 * with {@link PackageFiles::file()}. Only scripts and stylesheets inside a package's own directory are ever given,
 * so an address can never reach the host's code or another package's private files.
 *
 * @since 0.1.0
 */
class PackageFiles implements AssetLocatorInterface
{
    private const array TYPES = ['js' => 'text/javascript', 'mjs' => 'text/javascript', 'css' => 'text/css'];

    /**
     * @param array<string, string> $roots
     *   Each package's name, such as `speedyspec/block-builder`, and its directory.
     * @param string $prefix
     *   Where the files are served, starting and ending with a slash.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly array $roots,
        private readonly string $prefix = '/packages/',
    )
    {
    }

    public function url(string $package, string $path): string
    {
        return isset($this->roots[$package]) ? $this->prefix . $package . '/' . $path : '';
    }

    /**
     * Finds the file an address names.
     *
     * @return array{path: string, type: string}|null
     *   The file and its type, or null when the address names nothing this may serve.
     *
     * @since 0.1.0
     */
    public function file(string $urlPath): ?array
    {
        $path = rawurldecode($urlPath);

        if (!str_starts_with($path, $this->prefix) || str_contains($path, "\0"))
        {
            return null;
        }

        $parts = explode('/', substr($path, strlen($this->prefix)), 3);

        if (count($parts) < 3 || $parts[2] === '')
        {
            return null;
        }

        $root = $this->roots[$parts[0] . '/' . $parts[1]] ?? null;
        $base = $root === null ? false : realpath($root);
        $file = $base === false ? false : realpath($base . '/' . $parts[2]);
        $type = $file === false ? null : (self::TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null);

        // The real path, links followed, must still be inside the package's own directory.
        $inside = $file !== false && str_starts_with($file, $base . DIRECTORY_SEPARATOR);

        if (!$inside || $type === null || !is_file($file))
        {
            return null;
        }

        return ['path' => $file, 'type' => $type];
    }
}
