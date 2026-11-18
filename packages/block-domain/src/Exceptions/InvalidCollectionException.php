<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Signals a collection name a record store cannot use safely as a directory, table key or prefix.
 *
 * @since 0.1.0
 */
class InvalidCollectionException extends InvalidArgumentException
{
}
