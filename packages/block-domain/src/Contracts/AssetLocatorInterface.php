<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * Turns a file inside a package into the URL a browser loads it from.
 *
 * Only the host knows where package files are served: from where Composer put them, from a build step's copies,
 * or from a CDN. No default is bound; a host that places file assets on a page must bind one.
 *
 * @since 0.1.0
 */
interface AssetLocatorInterface
{
    /**
     * Gives the URL for one package file.
     *
     * @param string $package
     *   The package's Composer name, such as `speedyspec/block-builder`.
     * @param string $path
     *   Relative to the package's directory; already checked to stay inside it.
     *
     * @since 0.1.0
     */
    public function url(string $package, string $path): string;
}
