<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Signals that storage could not be read or written, or holds a record that cannot be understood.
 *
 * @since 0.1.0
 */
class StorageException extends RuntimeException
{
}
