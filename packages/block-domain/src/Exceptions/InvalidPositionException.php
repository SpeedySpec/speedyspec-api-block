<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Signals a position that cannot exist: a negative index, an empty parent id, or a place the tree does not have.
 *
 * @since 0.1.0
 */
class InvalidPositionException extends InvalidArgumentException
{
}
