<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Signals a schema that uses a keyword outside the supported subset, so it is rejected at registration.
 *
 * @since 0.1.0
 */
class UnsupportedSchemaException extends InvalidArgumentException
{
}
