<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Signals block type data that lacks a required field or has an invalid one.
 *
 * @since 0.1.0
 */
class InvalidBlockTypeException extends RuntimeException
{
}
