<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\InvalidAssetException;
use SpeedySpec\Block\Domain\ValueObjects\Asset;

/**
 * Holds the scripts and styles block outputs ask for by name, so a host can put them on the page in order.
 *
 * @since 0.1.0
 */
interface AssetRegistryInterface
{
    /**
     * Registers an asset, replacing one already registered under the same name.
     *
     * @since 0.1.0
     */
    public function add(Asset $asset): void;

    /**
     * Removes the asset registered under a name, if there is one.
     *
     * @since 0.1.0
     */
    public function remove(string $name): void;

    /**
     * Finds an asset by name.
     *
     * @return Asset|null
     *   Null when none is registered under the name.
     *
     * @since 0.1.0
     */
    public function get(string $name): ?Asset;

    /**
     * Puts the named assets, and everything they depend on, in the order a page must load them.
     *
     * @param list<string> $names
     *   Usually the asset names a render result lists.
     *
     * @return list<Asset>
     *   Dependencies before what needs them, each once; otherwise in the order first asked for.
     *
     * @throws InvalidAssetException
     *   When an asset is not registered, or assets depend on each other in a circle; the message names it.
     *
     * @since 0.1.0
     */
    public function resolve(array $names): array;
}
