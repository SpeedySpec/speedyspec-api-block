<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * The single entry point of a block package.
 *
 * A block package carries block types, their behaviour and their output, and nothing about any builder. It depends
 * only on the contracts package, so the same package works under any host and any builder.
 *
 * @since 0.1.0
 */
interface BlockPackageInterface
{
    /**
     * Names the package as lower-case `vendor/package`; block types it adds must use the same vendor.
     *
     * @since 0.1.0
     */
    public function name(): string;

    /**
     * Registers everything the package offers through the registrar it is handed.
     *
     * @since 0.1.0
     */
    public function register(BlockPackageRegistrarInterface $registrar): void;
}
