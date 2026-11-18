<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\BlockPackageException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Loads block packages into a host and remembers what each one added.
 *
 * @since 0.1.0
 */
interface BlockPackageLoaderInterface
{
    /**
     * Loads one package.
     *
     * @throws BlockPackageException
     *   When the package's name is invalid or already loaded, or it claims a block type it may not.
     *
     * @since 0.1.0
     */
    public function load(BlockPackageInterface $package): void;

    /**
     * Lists the names of the loaded packages, in load order.
     *
     * @return list<string>
     *   Package names.
     *
     * @since 0.1.0
     */
    public function loaded(): array;

    /**
     * Lists the block types a package added, in the order it added them.
     *
     * @return list<BlockName>
     *   Empty for a package that added none or was never loaded.
     *
     * @since 0.1.0
     */
    public function typesOf(string $packageName): array;
}
