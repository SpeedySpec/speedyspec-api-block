<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Signals a block name that is not a lower-case vendor/name pair.
 *
 * @since 0.1.0
 */
class InvalidBlockNameException extends RuntimeException
{
}
