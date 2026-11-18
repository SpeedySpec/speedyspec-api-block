<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Assets;

use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidAssetException;
use SpeedySpec\Block\Domain\ValueObjects\Asset;

/**
 * Keeps assets in memory and orders them by their dependencies.
 *
 * @since 0.1.0
 */
class AssetRegistry implements AssetRegistryInterface
{
    /**
     * @var array<string, Asset>
     */
    private array $assets = [];

    public function add(Asset $asset): void
    {
        $this->assets[$asset->name] = $asset;
    }

    public function remove(string $name): void
    {
        unset($this->assets[$name]);
    }

    public function get(string $name): ?Asset
    {
        return $this->assets[$name] ?? null;
    }

    public function resolve(array $names): array
    {
        $ordered = [];
        $done = [];

        foreach ($names as $name)
        {
            $this->visit((string) $name, [], $done, $ordered);
        }

        return $ordered;
    }

    /**
     * Places an asset after everything it depends on.
     *
     * @param array<string, true> $path
     *   The assets being placed further up, to catch a circle.
     * @param array<string, true> $done
     * @param list<Asset> $ordered
     *
     * @throws InvalidAssetException
     *
     * @since 0.1.0
     */
    private function visit(string $name, array $path, array &$done, array &$ordered): void
    {
        if (isset($done[$name]))
        {
            return;
        }

        if (isset($path[$name]))
        {
            throw new InvalidAssetException(sprintf('Asset "%s" depends on itself through others.', $name));
        }

        $asset = $this->assets[$name]
            ?? throw new InvalidAssetException(sprintf('Asset "%s" is not registered.', $name));
        $path[$name] = true;

        foreach ($asset->dependencies as $dependency)
        {
            $this->visit($dependency, $path, $done, $ordered);
        }

        $done[$name] = true;
        $ordered[] = $asset;
    }
}
