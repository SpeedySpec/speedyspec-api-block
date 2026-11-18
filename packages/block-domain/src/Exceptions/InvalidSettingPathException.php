<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Signals a setting path that is empty or malformed.
 *
 * @since 0.1.0
 */
class InvalidSettingPathException extends RuntimeException
{
}
