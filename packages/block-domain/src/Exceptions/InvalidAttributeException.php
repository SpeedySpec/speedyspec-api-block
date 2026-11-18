<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Signals a class, attribute, style or id that could break out of its element or run script.
 *
 * @since 0.1.0
 */
class InvalidAttributeException extends InvalidArgumentException
{
}
