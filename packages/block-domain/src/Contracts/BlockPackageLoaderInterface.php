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
     * Loads one package, all or nothing.
     *
     * A package that throws while registering, or that claims a block type it may not, must leave the host as it
     * was: none of its block types, outputs, steps or other registrations may remain. Any other exception a
     * package or a registry raises is passed on unchanged.
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

    /**
     * Tells which loaded package added a block type, so a builder can group blocks by where they come from.
     *
     * @return string|null
     *   The package's name; null for a type the host registered directly or that is not registered.
     *
     * @since 0.1.0
     */
    public function packageOf(BlockName $type): ?string;

    /**
     * Gives a loaded package's title and description, so a builder can head a section with them.
     *
     * @return array{title: string, description: string}|null
     *   Null when no package of that name is loaded.
     *
     * @since 0.1.0
     */
    public function details(string $packageName): ?array;
}
