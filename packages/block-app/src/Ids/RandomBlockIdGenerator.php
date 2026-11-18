<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Ids;

use SpeedySpec\Block\Domain\Contracts\BlockIdGeneratorInterface;

/**
 * Generates ids from random bytes, which needs no shared counter so editors can create ids independently.
 *
 * @since 0.1.0
 */
class RandomBlockIdGenerator implements BlockIdGeneratorInterface
{
    /**
     * Generates 16 lower-case hexadecimal characters.
     *
     * @since 0.1.0
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(8));
    }
}
