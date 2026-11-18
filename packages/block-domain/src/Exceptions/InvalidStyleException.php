<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Signals a style name or value that is not safe to write into a stylesheet.
 *
 * @since 0.1.0
 */
class InvalidStyleException extends InvalidArgumentException
{
}
