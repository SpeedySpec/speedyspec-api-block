<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Signals that a contract was requested or decorated before any implementation was bound to it.
 *
 * @since 0.1.0
 */
class ServiceNotRegisteredException extends RuntimeException
{
}
