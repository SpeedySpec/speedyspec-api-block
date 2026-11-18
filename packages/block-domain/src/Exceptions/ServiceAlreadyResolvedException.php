<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Signals a binding change after the contract was resolved, when the cached instance is the single source of truth.
 *
 * @since 0.1.0
 */
class ServiceAlreadyResolvedException extends RuntimeException
{
}
