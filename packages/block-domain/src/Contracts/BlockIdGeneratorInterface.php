<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * Creates block ids, so hosts can plug in their own scheme and tests can make ids predictable.
 *
 * @since 0.1.0
 */
interface BlockIdGeneratorInterface
{
    /**
     * Generates a new id.
     *
     * @since 0.1.0
     */
    public function generate(): string;
}
