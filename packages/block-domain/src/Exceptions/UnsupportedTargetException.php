<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use LogicException;

/**
 * Signals a request for an output target the host never configured, which is a setup error and not bad content.
 *
 * @since 0.1.0
 */
class UnsupportedTargetException extends LogicException
{
}
